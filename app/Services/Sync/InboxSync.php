<?php

namespace App\Services\Sync;

use App\Models\InboxConversation;
use App\Models\Page;
use App\Models\PosOrder;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class InboxSync
{
    private const BOTCAKE_APP_ID = 556376998159104;

    /**
     * Full parallel sync across given active pages:
     *  1) Pulls top-60 recent INBOX + top-60 unread_first INBOX per page so broadcast bursts never hide unread chats.
     *  2) Re-verifies any existing DB 'customer' (unanswered) rows that fell outside top-60 via /messages so replied chats disappear immediately.
     *  3) Sweeps POS orders across all recent inbox_conversations so new orders update has_order_today immediately.
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

        [$todayConvIds, $todayPsidKeys, $anyConvIds, $anyPsidKeys] = $this->loadOrderLookups($pageIds, $todayStartUtc, $recentStartUtc);

        $baseUrl = rtrim((string) config('kpi.pancake.base_url', 'https://pages.fm/api/public_api'), '/');
        $nowUtc = now()->utc();
        $rowsById = [];
        $pagesSynced = 0;

        // Stage 1: Fetch BOTH recent INBOX and unread_first INBOX in parallel batches of 10 pages (20 requests/batch)
        foreach ($targetPages->chunk(10) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch, $baseUrl) {
                foreach ($batch as $p) {
                    $url = "{$baseUrl}/v2/pages/{$p->fb_page_id}/conversations";
                    $pool->as("recent:{$p->fb_page_id}")
                        ->acceptJson()
                        ->timeout(15)
                        ->get($url, [
                            'type' => 'INBOX',
                            'page_access_token' => $p->pancake_token,
                        ]);
                    $pool->as("unread:{$p->fb_page_id}")
                        ->acceptJson()
                        ->timeout(15)
                        ->get($url, [
                            'type' => 'INBOX',
                            'unread_first' => 'true',
                            'page_access_token' => $p->pancake_token,
                        ]);
                }
            });

            foreach ($batch as $page) {
                $pid = (string) $page->fb_page_id;
                $resRecent = $responses["recent:{$pid}"] ?? null;
                $resUnread = $responses["unread:{$pid}"] ?? null;

                $mergedConvos = [];
                $ok = false;

                foreach ([$resUnread, $resRecent] as $res) {
                    if ($res && ! ($res instanceof \Throwable) && $res->successful()) {
                        $ok = true;
                        foreach (($res->json('conversations') ?? []) as $c) {
                            if (! empty($c['id'])) {
                                // resRecent runs second and overwrites if present in both
                                $mergedConvos[(string) $c['id']] = $c;
                            }
                        }
                    }
                }

                if (! $ok) {
                    continue;
                }
                $pagesSynced++;

                foreach ($mergedConvos as $cid => $c) {
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
                        $lastMsgAt = $updatedRaw ? Carbon::parse($updatedRaw, 'UTC') : $nowUtc;
                    } catch (\Throwable) {
                        $lastMsgAt = $nowUtc;
                    }

                    $rowsById[$cid] = [
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

        foreach (array_chunk(array_values($rowsById), 250) as $chunk) {
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

        // Stage 2: Re-verify ALL open 'customer' rows in DB (especially chats pushed outside top-60 by broadcasts!)
        $this->verifyOpenCustomerRows($targetPages, array_keys($rowsById), $todayConvIds, $todayPsidKeys);

        // Stage 3: Sweep POS order flags on existing DB rows for today so newly placed orders immediately clear "No Order Yet"
        if (! empty($todayConvIds)) {
            InboxConversation::whereIn('page_id', $pageIds)
                ->where('has_order_today', false)
                ->whereIn('id', array_slice(array_keys($todayConvIds), 0, 1000))
                ->update(['has_order_today' => true, 'has_any_order' => true]);
        }

        // Prune stale conversations older than 7 days
        InboxConversation::where('last_message_at', '<', now()->subDays(7)->utc())->delete();

        Cache::put('kpi:inbox_last_verified_ts', time(), 300);

        $unansweredFinal = InboxConversation::whereIn('page_id', $pageIds)
            ->where('last_sender_type', 'customer')
            ->where('has_order_today', false)
            ->where('last_message_at', '>=', now()->subHours(48)->utc())
            ->count();

        $humanFinal = InboxConversation::whereIn('page_id', $pageIds)
            ->where('last_sender_type', 'human_agent')
            ->where('last_message_at', '>=', $todayStartUtc)
            ->count();

        return [
            'pages_synced' => $pagesSynced,
            'conversations_upserted' => count($rowsById),
            'unanswered' => (int) $unansweredFinal,
            'human_replied' => (int) $humanFinal,
        ];
    }

    /**
     * Ultra-fast (< 1s) live check of currently open 'customer' rows in DB via /messages.
     * Called automatically when opening /inbox (F5 refresh) if > 25s since last verification,
     * so replying in Pancake and hitting browser refresh immediately removes replied chats!
     */
    public function quickVerifyScopedUnanswered(array $pageIds): void
    {
        if (empty($pageIds) || ! Schema::hasTable('inbox_conversations')) {
            return;
        }

        $pages = Page::where('active', true)
            ->whereIn('fb_page_id', $pageIds)
            ->whereNotNull('pancake_token')
            ->where('pancake_token', '!=', '')
            ->get();

        if ($pages->isEmpty()) {
            return;
        }

        $tz = config('kpi.timezone', 'Asia/Manila');
        $todayStartUtc = now($tz)->startOfDay()->utc();
        $recentStartUtc = now($tz)->subDays(7)->startOfDay()->utc();
        [$todayConvIds, $todayPsidKeys] = $this->loadOrderLookups($pageIds, $todayStartUtc, $recentStartUtc);

        // Verify ALL open 'customer' rows across these pages (pass empty $alreadySeenIds so every waiting row is checked live!)
        $this->verifyOpenCustomerRows($pages, [], $todayConvIds, $todayPsidKeys);
        Cache::put('kpi:inbox_last_verified_ts', time(), 300);
    }

    /**
     * Checks live /messages for open 'customer' conversations in parallel batches of 20.
     *
     * @param  Collection<int, Page>  $pages
     * @param  array<int, string>  $alreadySeenIds
     * @param  array<string, bool>  $todayConvIds
     * @param  array<string, bool>  $todayPsidKeys
     */
    private function verifyOpenCustomerRows(Collection $pages, array $alreadySeenIds, array $todayConvIds, array $todayPsidKeys): void
    {
        $pageMap = $pages->keyBy(fn ($p) => (string) $p->fb_page_id);
        $pageIds = $pageMap->keys()->map(fn ($k) => (string) $k)->all();
        if (empty($pageIds)) {
            return;
        }

        // Check ALL 'customer' rows not in $alreadySeenIds, PLUS any 'customer' row that was in $alreadySeenIds
        // where Pancake's conversation list sometimes lags on empty-text attachment replies!
        $openRows = InboxConversation::whereIn('page_id', $pageIds)
            ->where('last_sender_type', 'customer')
            ->where('last_message_at', '>=', now()->subHours(72)->utc())
            ->orderByDesc('last_message_at')
            ->limit(300)
            ->get();

        if ($openRows->isEmpty()) {
            return;
        }

        // Prioritize rows NOT seen in top-60 + rows in top-60 with older timestamps
        $seenLookup = array_fill_keys($alreadySeenIds, true);
        $toVerify = $openRows->filter(function ($r) use ($seenLookup) {
            if (! isset($seenLookup[$r->id])) {
                return true; // Pushed past top-60 by broadcasts (like Deo Tabornal Maglasang!) -> MUST check /messages
            }

            // Also verify top-60 rows so attachment-only bot replies or system call-permission notices are caught accurately
            return true;
        })->values();

        $baseUrl = rtrim((string) config('kpi.pancake.base_url', 'https://pages.fm/api/public_api'), '/');
        $nowUtc = now()->utc();

        foreach ($toVerify->chunk(20) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch, $pageMap, $baseUrl) {
                foreach ($batch as $row) {
                    $p = $pageMap[(string) $row->page_id] ?? null;
                    if (! $p) {
                        continue;
                    }
                    $pool->as((string) $row->id)
                        ->acceptJson()
                        ->timeout(12)
                        ->get("{$baseUrl}/v1/pages/{$row->page_id}/conversations/{$row->id}/messages", [
                            'page_access_token' => $p->pancake_token,
                        ]);
                }
            });

            foreach ($batch as $row) {
                $cid = (string) $row->id;
                $pid = (string) $row->page_id;
                $psidKey = $row->customer_id ? ($pid.':'.$row->customer_id) : '';
                $hasOrderToday = $row->has_order_today
                    || isset($todayConvIds[$cid])
                    || ($psidKey !== '' && isset($todayPsidKeys[$psidKey]));

                $res = $responses[$cid] ?? null;
                if (! $res || $res instanceof \Throwable || ! $res->successful()) {
                    if ($hasOrderToday !== $row->has_order_today) {
                        $row->update(['has_order_today' => true, 'has_any_order' => true, 'synced_at' => $nowUtc]);
                    }

                    continue;
                }

                $msgs = $res->json('messages') ?? [];
                if (! is_array($msgs) || empty($msgs)) {
                    continue;
                }

                // Walk backwards from newest message to skip automated Messenger call-permission system notices
                $effectiveMsg = null;
                for ($i = count($msgs) - 1; $i >= 0; $i--) {
                    $m = $msgs[$i];
                    $rawText = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($m['message'] ?? ''))));
                    if ($this->isSystemCallPermissionNotice($rawText)) {
                        continue;
                    }
                    $effectiveMsg = $m;
                    break;
                }

                if (! $effectiveMsg) {
                    // Entire thread is just a system call-permission notice -> not an unanswered question
                    $row->update([
                        'last_sender_type' => 'bot',
                        'last_admin_name' => 'System Notice',
                        'has_order_today' => $hasOrderToday,
                        'synced_at' => $nowUtc,
                    ]);

                    continue;
                }

                $from = $effectiveMsg['from'] ?? [];
                $fromId = trim((string) ($from['id'] ?? ''));
                $msgText = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($effectiveMsg['message'] ?? ''))));
                if ($msgText === '' && ! empty($effectiveMsg['attachments'])) {
                    $msgText = '[Attachment]';
                }

                try {
                    $msgAt = ! empty($effectiveMsg['inserted_at'])
                        ? Carbon::parse($effectiveMsg['inserted_at'], 'UTC')
                        : ($row->last_message_at ?? $nowUtc);
                } catch (\Throwable) {
                    $msgAt = $row->last_message_at ?? $nowUtc;
                }

                if ($fromId === $pid) {
                    // Page / Human Agent / Botcake replied!
                    [$newType, $admName] = $this->classifyFromMessageSender($from, $msgText);
                    $row->update([
                        'last_sender_type' => $newType,
                        'last_admin_name' => $admName,
                        'seen' => true,
                        'snippet' => $msgText !== '' ? mb_substr($msgText, 0, 600) : $row->snippet,
                        'last_message_at' => $msgAt,
                        'has_order_today' => $hasOrderToday,
                        'has_any_order' => $row->has_any_order || $hasOrderToday,
                        'synced_at' => $nowUtc,
                    ]);
                } else {
                    // Truly still waiting on a reply
                    $row->update([
                        'snippet' => $msgText !== '' ? mb_substr($msgText, 0, 600) : $row->snippet,
                        'last_message_at' => $msgAt,
                        'has_order_today' => $hasOrderToday,
                        'has_any_order' => $row->has_any_order || $hasOrderToday,
                        'synced_at' => $nowUtc,
                    ]);
                }
            }
        }
    }

    /**
     * @return array{0: array<string,bool>, 1: array<string,bool>, 2: array<string,bool>, 3: array<string,bool>}
     */
    private function loadOrderLookups(array $pageIds, Carbon $todayStartUtc, Carbon $recentStartUtc): array
    {
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

        return [$todayConvIds, $todayPsidKeys, $anyConvIds, $anyPsidKeys];
    }

    private function isSystemCallPermissionNotice(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        return (bool) preg_match('/^You can call .+ within the next \d+ days\.?$/i', $text)
            || (bool) preg_match('/replied to an ad\.?$/i', $text);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function classifyFromMessageSender(array $from, string $msgText): array
    {
        $admName = trim((string) ($from['admin_name'] ?? ''));
        $appId = (int) ($from['app_id'] ?? 0);
        $aiGen = ! empty($from['ai_generated']);

        if ($admName === config('kpi.attribution.ai_sender', 'BotcakeAI') || $aiGen) {
            return ['botcake_ai', 'Botcake AI'];
        }

        if (in_array($admName, ['Botcake', 'POS'], true) || $appId === self::BOTCAKE_APP_ID || ! empty($from['is_automated'])) {
            return ['bot', $admName ?: 'Botcake'];
        }

        if ($admName !== '') {
            return ['human_agent', $admName];
        }

        if ($msgText !== '' && ! in_array($msgText, ['[Attachment]', '[Sticker]', '[Photo]'], true)) {
            return ['human_agent', 'Page Admin'];
        }

        return ['bot', 'Botcake'];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function classifyLastSender(string $pageId, array $convo, string $snippet, bool $seen): array
    {
        if ($this->isSystemCallPermissionNotice($snippet)) {
            return ['bot', 'System Notice'];
        }

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

        // 5) Page reply without Botcake signature
        if ($snippet !== '' && ! in_array($snippet, ['[Attachment]', '[Sticker]', '[Photo]'], true)) {
            return ['human_agent', 'Page Admin'];
        }

        return ['bot', 'Botcake'];
    }
}
