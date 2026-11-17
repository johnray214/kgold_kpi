<?php

namespace App\Http\Controllers;

use App\Models\InboxConversation;
use App\Models\Page;
use App\Models\PosOrder;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Sync\InboxSync;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $tz = config('kpi.timezone', 'Asia/Manila');
        /** @var User|null $me */
        $me = $request->user();
        $isAdmin = ! $me || $me->isAdmin();

        $allPages = Cache::remember('kpi:all_pages', 900, fn () => DashboardController::lightPagesQuery()->get());

        if ($isAdmin) {
            $allowedPages = $allPages;
        } else {
            $assignedIds = Cache::remember("kpi:user_pages:{$me->id}", 900, fn () => $me->pages()->pluck('pages.id')->all());
            $allowedPages = $allPages->whereIn('id', $assignedIds)->values();
        }

        $product = $request->input('product');
        $team = $request->input('team');

        $pages = $allowedPages
            ->when($product, fn ($c) => $c->where('product', $product))
            ->when($team, fn ($c) => $c->where('team', $team))
            ->values();

        if ($pages->isEmpty() && ($product || $team)) {
            $pages = $allowedPages;
        }

        $products = $allowedPages->pluck('product')->filter()->unique()->sort()->values();
        $teams = $allowedPages->pluck('team')->filter()->unique()->sort()->values();

        $pageId = (string) $request->input('page', 'all');
        if ($pageId !== 'all' && ! $pages->contains('fb_page_id', $pageId)) {
            $pageId = 'all';
        }

        $todayStr = now($tz)->toDateString();
        $from = $request->input('from', $todayStr);
        $to = $request->input('to', $from);

        $scopedPageIds = $pageId === 'all'
            ? $pages->pluck('fb_page_id')->map(fn ($id) => (string) $id)->values()->all()
            : [$pageId];

        // Map page_id -> page metadata + assigned member names
        $pageOwnerMap = Cache::remember('kpi:page_owners_map', 300, function () {
            $map = [];
            $members = User::where('role', 'member')->with('pages:id,fb_page_id')->orderBy('name')->get();
            foreach ($members as $m) {
                foreach ($m->pages as $mp) {
                    $map[(string) $mp->fb_page_id][] = $m->name;
                }
            }

            return $map;
        });

        $pageMeta = [];
        foreach ($allPages as $p) {
            $pid = (string) $p->fb_page_id;
            $owners = $pageOwnerMap[$pid] ?? [];
            $pageMeta[$pid] = [
                'name' => $p->name,
                'product' => $p->product,
                'team' => $p->team ?: (! empty($owners) ? implode(', ', $owners) : null),
            ];
        }

        $tab = (string) $request->input('tab', 'unanswered_no_order');
        $windowMode = (string) $request->input('window', ($from !== $todayStr || $to !== $todayStr) ? 'date' : '48h');

        $nowLocal = now($tz);
        $todayStartUtc = $nowLocal->copy()->startOfDay()->utc();
        if ($windowMode === 'today') {
            $rangeStartUtc = $todayStartUtc;
            $rangeEndUtc = $nowLocal->copy()->endOfDay()->utc();
        } elseif ($windowMode === 'date') {
            try {
                $rangeStartUtc = Carbon::parse($from, $tz)->startOfDay()->utc();
                $rangeEndUtc = Carbon::parse($to, $tz)->endOfDay()->utc();
            } catch (\Throwable) {
                $rangeStartUtc = $nowLocal->copy()->subHours(48)->utc();
                $rangeEndUtc = $nowLocal->copy()->endOfDay()->utc();
            }
        } else {
            // Default 48h active queue so late-night unanswered inquiries are never missed
            $rangeStartUtc = $nowLocal->copy()->subHours(48)->utc();
            $rangeEndUtc = $nowLocal->copy()->endOfDay()->utc();
        }

        $stats = [
            'unanswered_no_order' => 0,
            'unanswered_with_phone' => 0,
            'unread_unseen' => 0,
            'human_no_order' => 0,
            'human_today_all' => 0,
            'human_converted_today' => 0,
            'unanswered_has_order' => 0,
            'bot_no_order' => 0,
            'pages_with_unanswered' => 0,
            'last_inbox_sync' => null,
        ];

        $conversations = collect();
        $unansweredByPage = collect();

        if (! empty($scopedPageIds) && Schema::hasTable('inbox_conversations')) {
            $baseRows = InboxConversation::whereIn('page_id', $scopedPageIds)
                ->whereBetween('last_message_at', [$rangeStartUtc, $rangeEndUtc])
                ->orderByDesc('last_message_at')
                ->limit(1500)
                ->get();

            $unansweredNoOrder = $baseRows->filter(fn ($r) => $r->last_sender_type === 'customer' && ! $r->has_order_today);
            $unansweredHasOrder = $baseRows->filter(fn ($r) => $r->last_sender_type === 'customer' && $r->has_order_today);
            $humanNoOrder = $baseRows->filter(fn ($r) => $r->last_sender_type === 'human_agent' && ! $r->has_order_today);
            $humanAll = $baseRows->filter(fn ($r) => $r->last_sender_type === 'human_agent');
            $botNoOrder = $baseRows->filter(fn ($r) => in_array($r->last_sender_type, ['botcake_ai', 'bot'], true) && ! $r->has_order_today);

            // Count today's human-assisted chats strictly from 12:00 AM today for EOD alignment
            $humanTodayRows = InboxConversation::whereIn('page_id', $scopedPageIds)
                ->where('last_sender_type', 'human_agent')
                ->where('last_message_at', '>=', $todayStartUtc)
                ->get(['id', 'has_order_today']);

            $stats = [
                'unanswered_no_order' => $unansweredNoOrder->count(),
                'unanswered_with_phone' => $unansweredNoOrder->where('has_phone', true)->count(),
                'unread_unseen' => $unansweredNoOrder->where('seen', false)->count(),
                'human_no_order' => $humanNoOrder->count(),
                'human_today_all' => $humanTodayRows->count(),
                'human_converted_today' => $humanTodayRows->where('has_order_today', true)->count(),
                'unanswered_has_order' => $unansweredHasOrder->count(),
                'bot_no_order' => $botNoOrder->count(),
                'pages_with_unanswered' => $unansweredNoOrder->pluck('page_id')->unique()->count(),
                'last_inbox_sync' => InboxConversation::whereIn('page_id', $scopedPageIds)->max('synced_at'),
            ];

            $unansweredByPage = $unansweredNoOrder->groupBy('page_id')
                ->map(fn ($grp, $pid) => [
                    'page_id' => $pid,
                    'page_name' => $pageMeta[$pid]['name'] ?? ($grp->first()->page_name ?? $pid),
                    'team' => $pageMeta[$pid]['team'] ?? null,
                    'count' => $grp->count(),
                    'with_phone' => $grp->where('has_phone', true)->count(),
                ])
                ->sortByDesc('count')
                ->values();

            $conversations = match ($tab) {
                'human_no_order' => $humanNoOrder->values(),
                'human_all' => $humanAll->values(),
                'unanswered_has_order' => $unansweredHasOrder->values(),
                'bot_no_order' => $botNoOrder->values(),
                default => $unansweredNoOrder->values(),
            };
        }

        $syncMeta = Cache::remember('kpi:sync_meta', 180, fn () => [
            'lastSync' => SyncRun::where('status', 'ok')->latest('finished_at')->first(),
            'syncRunning' => SyncRun::where('status', 'running')->where('started_at', '>', now()->subMinutes(15))->exists(),
            'lastError' => null,
        ]);

        $scopeHash = md5(implode(',', $scopedPageIds));
        $dataSince = ! empty($scopedPageIds)
            ? Cache::remember("kpi:since:{$scopeHash}", 900, fn () => PosOrder::whereIn('page_id', $scopedPageIds)->min('ordered_at'))
            : null;

        return view('inbox', $syncMeta + [
            'pages' => $pages,
            'allPages' => $allPages,
            'products' => $products,
            'teams' => $teams,
            'product' => $product,
            'team' => $team,
            'pageId' => $pageId,
            'from' => $from,
            'to' => $to,
            'dataSince' => $dataSince,
            'tab' => $tab,
            'windowMode' => $windowMode,
            'stats' => $stats,
            'conversations' => $conversations,
            'unansweredByPage' => $unansweredByPage,
            'pageMeta' => $pageMeta,
        ]);
    }

    public function syncNow(Request $request)
    {
        /** @var User|null $me */
        $me = $request->user();
        $isAdmin = ! $me || $me->isAdmin();

        $query = Page::where('active', true)->whereNotNull('pancake_token')->where('pancake_token', '!=', '');
        if (! $isAdmin) {
            $assignedIds = $me->pages()->pluck('pages.id')->all();
            $query->whereIn('id', $assignedIds);
        }
        if ($request->filled('page') && $request->input('page') !== 'all') {
            $query->where('fb_page_id', (string) $request->input('page'));
        }

        $targetPages = $query->orderBy('id')->get();
        $res = (new InboxSync)->syncPages($targetPages);

        Cache::forget('kpi:inbox_badge');
        if ($me) {
            Cache::forget("kpi:inbox_badge:{$me->id}");
        }

        return back()->with(
            'status',
            "Live Inbox refreshed across {$res['pages_synced']} page(s) — {$res['unanswered']} unanswered chat(s) & {$res['human_replied']} manually assisted chat(s) updated."
        );
    }
}
