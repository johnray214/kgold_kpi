<?php

namespace App\Services\Sync;

use App\Models\InboxConversation;
use App\Models\Page;
use App\Models\PosOrder;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class InboxSync
{
    private const BOTCAKE_APP_ID = 556376998159104;

    /**
     * Pull latest Pancake INBOX conversations in parallel across given active pages,
     * classify unanswered (customer spoke last) vs human-replied vs bot/AI,
     * and cross-reference POS orders so we know which chats haven't ordered yet.
     *
     * @param  Collection<int, Page>|null  $pages
     * @return array{pages_synced:int, conversations_upserted:int, unanswered:int, human_replied:int}
     */
    public function syncPages(?Collection $pages = null): array
    {
        if (! Schema::hasTable('inbox_conversations')) {
            return ['pages_synced' => 0, 'conversations_upserted' => 0, 'unanswered' => 0, 'human_replied' => 0];
        }

        $targetPages = ($pages ?? Page::where('active', true)->orderBy('id')->get())
            ->filter(fn ($p) => ! empty($p->pancake_token))
            ->values();

        if ($targetPages->isEmpty()) {
            return ['pages_synced' => 0, 'conversations_upserted' => 0, 'unanswered' => 0, 'human_replied' => 0];
        }

        $tz = config('kpi.timezone', 'Asia/Manila');
        $todayStartUtc = now($tz)->startOfDay()->utc();
        $recentStartUtc = now($tz)->subDays(30)->startOfDay()->utc();
        $pageIds = $targetPages->pluck('fb_page_id')->map(fn ($id) => (string) $id)->all();

        // Preload today's and recent valid POS orders by conversation_id AND page_id:psid
        $todayConvIds = [];
        $todayPsidKeys = [];
        $anyConvIds = [];
        $anyPsidKeys = [];

        $recentOrders = PosOrder::whereIn('page_id', $pageIds)
            ->where('is_valid', true)
            ->where('ordered_at', '>=', $recentStartUtc)
            ->get(['page_id', 'conversation_id', 'psid', 'ordered_at']);

        foreach ($recentOrders as $ord) {
            $isToday = $ord->ordered_at && $ord->ordered_at->greaterThanOrEqualTo($todayStartUtc);
            if (! empty($ord->conversation_id)) {
                $anyConvIds[(string) $ord->conversation_id] = true;
                if ($isToday) {
                    $todayConvIds[(string) $ord->conversation_id] = true;
                }
            }
            if (! empty($ord->psid)) {
                $pk = $ord->page_id.':'.$ord->psid;
                $anyPsidKeys[$pk] = true;
                if ($isToday) {
                    $todayPsidKeys[$pk] = true;
                }
            }
        }

        $baseUrl = rtrim((string) config('kpi.pancake.base_url', 'https://pages.fm/api/public_api'), '/');
        $nowUtc = now()->utc();
        $rowsToUpsert = [];
        $pagesSynced = 0;
        $unansweredCount = 0;
        $humanRepliedCount = 0;

        // Fetch in parallel batches of 14 pages so 48 pages complete in ~3-4 seconds
        foreach ($targetPages->chunk(14) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch, $baseUrl) {
                foreach ($batch as $p) {
                    $url = "{$baseUrl}/v2/pages/{$p->fb_page_id}/conversations";
                    $pool->as((string) $p->fb_page_id)
                        ->acceptJson()
                        ->timeout(15)
                        ->get($url, [
                            'type' => 'INBOX',
                            'page_access_token' => $p->pancake_token,
                        ]);
                }
            });

            foreach ($batch as $page) {
                $pid = (string) $page->fb_page_id;
                $res = $responses[$pid] ?? null;
                if (! $res || $res instanceof \Throwable || ! $res->successful()) {
                    continue;
                }

                $pagesSynced++;
                $convos = $res->json('conversations') ?? [];
                if (! is_array($convos)) {
                    continue;
                }

                foreach ($convos as $c) {
                    $cid = trim((string) ($c['id'] ?? ''));
                    if ($cid === '') {
                        continue;
                    }

                    $custId = (string) ($c['from']['id'] ?? ($c['page_customer']['psid'] ?? ''));
                    $custName = trim((string) ($c['from']['name'] ?? ($c['page_customer']['name'] ?? 'Facebook User')));
                    if ($custName === '' || $custName === $pid) {
                        $custName = 'Facebook User';
                    }

                    $phones = $c['recent_phone_numbers'] ?? [];
                    $firstPhone = null;
                    if (is_array($phones) && ! empty($phones)) {
                        $first = reset($phones);
                        $firstPhone = is_array($first) ? ($first['phone_number'] ?? ($first['number'] ?? null)) : (string) $first;
                    }

                    $snippet = trim(preg_replace('/\s+/', ' ', (string) ($c['snippet'] ?? '')));
                    $seen = (bool) ($c['seen'] ?? false);
                    $msgCount = (int) ($c['message_count'] ?? 0);

                    [$senderType, $adminName] = $this->classifyLastSender($pid, $c, $snippet, $seen);

                    if ($senderType === 'customer') {
                        $unansweredCount++;
                    } elseif ($senderType === 'human_agent') {
                        $humanRepliedCount++;
                    }

                    $assignUsers = $c['current_assign_users'] ?? [];
                    $assignedNames = [];
                    if (is_array($assignUsers)) {
                        foreach ($assignUsers as $au) {
                            if (! empty($au['name'])) {
                                $assignedNames[] = $au['name'];
                            }
                        }
                    }

                    $cleanTags = [];
                    foreach (($c['tags'] ?? []) as $tg) {
                        if (! empty($tg['text'])) {
                            $cleanTags[] = [
                                'text' => (string) $tg['text'],
                                'color' => (string) ($tg['color'] ?? '#4B55F5'),
                            ];
                        }
                    }

                    $psidKey = $custId !== '' ? ($pid.':'.$custId) : '';
                    $hasOrderToday = isset($todayConvIds[$cid]) || ($psidKey !== '' && isset($todayPsidKeys[$psidKey]));
                    $hasAnyOrder = $hasOrderToday || isset($anyConvIds[$cid]) || ($psidKey !== '' && isset($anyPsidKeys[$psidKey]));

                    $updatedRaw = $c['updated_at'] ?? ($c['inserted_at'] ?? null);
                    try {
                        // Pancake timestamps in v2/conversations are UTC without offset
                        $lastMsgAt = $updatedRaw ? Carbon::parse($updatedRaw, 'UTC') : $nowUtc;
                    } catch (\Throwable) {
                        $lastMsgAt = $nowUtc;
                    }

                    $rowsToUpsert[] = [
                        'id' => substr($cid, 0, 120),
                        'page_id' => substr($pid, 0, 64),
                        'page_name' => substr((string) $page->name, 0, 191),
                        'customer_id' => $custId !== '' ? substr($custId, 0, 80) : null,
                        'customer_name' => substr($custName, 0, 191),
                        'has_phone' => ! empty($c['has_phone']) || ! empty($firstPhone),
                        'phone' => $firstPhone ? substr((string) $firstPhone, 0, 64) : null,
                        'snippet' => $snippet !== '' ? mb_substr($snippet, 0, 600) : null,
                        'seen' => $seen,
                        'message_count' => $msgCount,
                        'last_sender_type' => $senderType,
                        'last_admin_name' => $adminName ? substr($adminName, 0, 191) : null,
                        'assigned_to' => ! empty($assignedNames) ? substr(implode(', ', $assignedNames), 0, 191) : null,
                        'tags' => json_encode($cleanTags, JSON_UNESCAPED_UNICODE),
                        'has_order_today' => $hasOrderToday,
                        'has_any_order' => $hasAnyOrder,
                        'last_message_at' => $lastMsgAt->toDateTimeString(),
                        'synced_at' => $nowUtc->toDateTimeString(),
                        'created_at' => $nowUtc->toDateTimeString(),
                        'updated_at' => $nowUtc->toDateTimeString(),
                    ];
                }
            }
        }

        foreach (array_chunk($rowsToUpsert, 250) as $chunk) {
            InboxConversation::upsert(
                $chunk,
                ['id'],
                [
                    'page_name', 'customer_id', 'customer_name', 'has_phone', 'phone',
                    'snippet', 'seen', 'message_count', 'last_sender_type', 'last_admin_name',
                    'assigned_to', 'tags', 'has_order_today', 'has_any_order',
                    'last_message_at', 'synced_at', 'updated_at',
                ]
            );
        }

        // Prune stale conversations older than 7 days to keep queries instantaneous
        InboxConversation::where('last_message_at', '<', now()->subDays(7)->utc())->delete();

        return [
            'pages_synced' => $pagesSynced,
            'conversations_upserted' => count($rowsToUpsert),
            'unanswered' => $unansweredCount,
            'human_replied' => $humanRepliedCount,
        ];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function classifyLastSender(string $pageId, array $convo, string $snippet, bool $seen): array
    {
        $lsb = $convo['last_sent_by'] ?? null;
        $lsbId = is_array($lsb) ? trim((string) ($lsb['id'] ?? '')) : '';
        $admName = is_array($lsb) ? trim((string) ($lsb['admin_name'] ?? '')) : '';
        $appId = is_array($lsb) ? (int) ($lsb['app_id'] ?? 0) : 0;
        $aiGen = is_array($lsb) && ! empty($lsb['ai_generated']);

        // 1) Customer spoke last (last_sent_by.id is the customer's PSID, not the Page ID)
        if ($lsbId !== '' && $lsbId !== $pageId) {
            return ['customer', null];
        }

        if ($lsbId === '' && ! $seen && ! str_starts_with($snippet, '[Botcake') && ! str_starts_with($snippet, '[Template]')) {
            return ['customer', null];
        }

        // 2) Botcake AI spoke last
        if ($admName === config('kpi.attribution.ai_sender', 'BotcakeAI') || $aiGen || str_starts_with($snippet, '[Botcake AI]')) {
            return ['botcake_ai', 'Botcake AI'];
        }

        // 3) Automated Botcake flow / sequence / broadcast / POS automated message
        if (
            in_array($admName, ['Botcake', 'POS'], true)
            || $appId === self::BOTCAKE_APP_ID
            || str_starts_with($snippet, '[Botcake]')
            || str_starts_with($snippet, '[Template]')
        ) {
            return ['bot', $admName ?: 'Botcake'];
        }

        // 4) Named human teammate replied from Pancake / Botcake Livechat
        if ($admName !== '') {
            return ['human_agent', $admName];
        }

        // 5) Page reply without Botcake signature (e.g. human reply via Meta Business Suite)
        //    Ignore bare "[Attachment]" / "[Sticker]" / "[Photo]" without admin_name since Botcake image blocks often emit those.
        if ($snippet !== '' && ! in_array($snippet, ['[Attachment]', '[Sticker]', '[Photo]'], true)) {
            return ['human_agent', 'Page Admin'];
        }

        return ['bot', 'Botcake'];
    }
}
