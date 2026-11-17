<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\Page;
use App\Models\PosOrder;
use App\Models\SyncRun;
use App\Services\KpiReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public static function lightPagesQuery()
    {
        return Page::where('active', true)
            ->selectRaw("pages.id, pages.fb_page_id, pages.name, pages.product, pages.team, pages.active, (pages.botcake_token is not null and pages.botcake_token != '') as has_botcake, (pages.pancake_token is not null and pages.pancake_token != '') as has_pancake")
            ->orderBy('pages.id');
    }

    private function baseContext(Request $request): array
    {
        $tz = config('kpi.timezone');
        /** @var \App\Models\User|null $me */
        $me = $request->user();
        $isAdmin = ! $me || $me->isAdmin();

        $allPages = Cache::remember('kpi:all_pages', 900, fn () => self::lightPagesQuery()->get());

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

        $pageId = $request->input('page', 'all');
        if ($pageId !== 'all' && ! $pages->contains('fb_page_id', $pageId)) {
            $pageId = 'all';
        }
        $from = $request->input('from', now($tz)->toDateString());
        $to = $request->input('to', $from);

        $report = new KpiReport($pageId, $from, $to, $pages);
        $ids = array_map('strval', $report->pageIds);

        $syncMeta = Cache::remember('kpi:sync_meta', 180, function () {
            return [
                'lastSync' => SyncRun::where('status', 'ok')->latest('finished_at')->first(),
                'syncRunning' => SyncRun::where('status', 'running')->where('started_at', '>', now()->subMinutes(15))->exists(),
                'lastError' => SyncRun::whereIn('id', SyncRun::selectRaw('max(id)')->groupBy('task'))
                    ->where('status', 'error')->latest('started_at')->first(),
            ];
        });

        $scopeHash = md5(implode(',', $ids));
        $dataSince = ! empty($ids)
            ? Cache::remember("kpi:since:{$scopeHash}", 900, fn () => PosOrder::whereIn('page_id', $ids)->min('ordered_at'))
            : null;

        return [
            $report,
            $syncMeta + [
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
            ],
        ];
    }

    /** Cached server-side Facebook Page profile picture proxy so browser adblockers never block avatars. */
    public function pageAvatar(string $pageId)
    {
        $cleanId = preg_replace('/[^0-9A-Za-z_-]/', '', $pageId);
        if ($cleanId === '') {
            abort(404);
        }

        $dir = public_path('avatars');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = "{$dir}/{$cleanId}.jpg";

        if (! is_file($file) || filemtime($file) < time() - 86400 * 7) {
            try {
                $res = Http::timeout(8)->get("https://graph.facebook.com/{$cleanId}/picture?type=normal");
                if ($res->successful() && str_starts_with((string) $res->header('Content-Type'), 'image/')) {
                    file_put_contents($file, $res->body());
                }
            } catch (\Throwable) {
            }
        }

        if (! is_file($file)) {
            abort(404);
        }

        return response()->file($file, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Sidebar 1: Overall Executive Dashboard */
    public function index(Request $request)
    {
        [$report, $shared] = $this->baseContext($request);
        $ids = array_map('strval', $report->pageIds);
        $ver = Cache::get('kpi:ver', '1');
        $cacheKey = "kpi:dash:{$ver}:".md5(implode(',', $ids)."|{$report->from}|{$report->to}");

        $payload = Cache::remember($cacheKey, 900, function () use ($report, $shared, $ids) {
            $sales = $report->salesBySource();
            $broadcasts = $report->broadcasts(false);

            return [
                'sales' => $sales,
                'pageBreakdown' => $report->pageBreakdown($shared['pages']),
                'broadcasts' => $broadcasts,
                'sequencesCount' => ! empty($ids) ? \App\Models\Sequence::whereIn('page_id', $ids)->where('published_steps', '>', 0)->count() : 0,
                'ai' => $report->botcakeAi(),
                'timeline' => $report->timeline(),
                'comparison' => $report->comparison($shared['dataSince'], $sales, $broadcasts),
                'recentOrders' => $report->orderList(['valid' => 1], 50),
                'scheduledCount' => ! empty($ids) ? Broadcast::whereIn('page_id', $ids)->where('status', 'SCHEDULED')->count() : 0,
            ];
        });

        return view('dashboard', $shared + $payload);
    }

    /** Sidebar 2: Utility Broadcasts Workspace */
    public function broadcastsPage(Request $request)
    {
        [$report, $shared] = $this->baseContext($request);
        $ids = array_map('strval', $report->pageIds);
        $ver = Cache::get('kpi:ver', '1');
        $cacheKey = "kpi:bc:{$ver}:".md5(implode(',', $ids)."|{$report->from}|{$report->to}");

        $payload = Cache::remember($cacheKey, 900, function () use ($report, $ids) {
            $broadcasts = $report->broadcasts(true);

            return [
                'broadcasts' => $broadcasts,
                'segments' => $report->broadcastSegments($broadcasts),
                'scheduledCount' => ! empty($ids) ? Broadcast::whereIn('page_id', $ids)->where('status', 'SCHEDULED')->count() : 0,
            ];
        });

        return view('broadcasts', $shared + $payload);
    }

    /** Sidebar 3: Sequences & Drop-Off Funnel Workspace */
    public function sequencesPage(Request $request)
    {
        [$report, $shared] = $this->baseContext($request);
        $ids = array_map('strval', $report->pageIds);
        $ver = Cache::get('kpi:ver', '1');
        $cacheKey = "kpi:seq:{$ver}:".md5(implode(',', $ids)."|{$report->from}|{$report->to}");

        $payload = Cache::remember($cacheKey, 900, fn () => [
            'sequences' => $report->sequences(),
            'sequenceOrders' => $report->orderList(['source_type' => 'sequence', 'valid' => 1], 30),
        ]);

        return view('sequences', $shared + $payload);
    }

    /** Sidebar 4: Botcake AI & Agent Handoff Workspace */
    public function botcakeAiPage(Request $request)
    {
        [$report, $shared] = $this->baseContext($request);
        $ids = array_map('strval', $report->pageIds);
        $ver = Cache::get('kpi:ver', '1');
        $cacheKey = "kpi:ai:{$ver}:".md5(implode(',', $ids)."|{$report->from}|{$report->to}");

        $payload = Cache::remember($cacheKey, 900, fn () => [
            'sales' => $report->salesBySource(),
            'ai' => $report->botcakeAi(),
            'timeline' => $report->timeline(),
            'aiOrders' => $report->orderList(['source_type' => 'botcake_ai', 'valid' => 1], 250),
            'agentOrders' => $report->orderList(['source_type' => 'agent', 'valid' => 1], 50),
        ]);

        return view('botcake-ai', $shared + $payload);
    }

    /** Sidebar 5: POS Orders Ledger */
    public function orders(Request $request)
    {
        [$report, $shared] = $this->baseContext($request);
        $filters = $request->only(['source_type', 'source_id', 'assisted', 'valid']);
        $ids = array_map('strval', $report->pageIds);
        $ver = Cache::get('kpi:ver', '1');
        $cacheKey = "kpi:ord:{$ver}:".md5(implode(',', $ids)."|{$report->from}|{$report->to}|".json_encode($filters));

        $orders = Cache::remember($cacheKey, 900, fn () => $report->orderList($filters, 250));

        return view('orders', $shared + [
            'filters' => $filters,
            'orders' => $orders,
        ]);
    }

    /** Sidebar 6: Connected Pages & API Tokens */
    public function settings(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        [, $shared] = $this->baseContext($request);
        $uid = $request->user()?->id ?? 0;

        $posShops = collect();
        try {
            $posShops = Cache::remember('kpi:pos_shops_list', 900, fn () => \App\Models\PosShop::where('active', true)->orderBy('id')->get());
        } catch (\Throwable) {
        }

        return view('settings', $shared + [
            'scanResult' => Cache::get("kpi:scan:{$uid}"),
            'memberAccounts' => Cache::remember('kpi:member_accounts', 900, fn () => \App\Models\User::where('role', 'member')->orderBy('name')->get(['id', 'name', 'email'])),
            'posShops' => $posShops,
        ]);
    }

    public function addPosShop(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $apiKey = trim((string) $request->input('api_key'));
        if ($apiKey === '') {
            return back()->with('status', 'Please enter a Pancake POS API Key.');
        }

        $res = Http::acceptJson()->timeout(20)->get(config('kpi.pos.base_url').'/shops', ['api_key' => $apiKey]);
        $shops = $res->json('shops') ?? [];
        if (! $res->successful() || empty($shops)) {
            return back()->withErrors(['api_key' => 'Invalid POS API Key or no shops found on pos.pages.fm.']);
        }

        $savedNames = [];
        $posPageNames = [];
        foreach ($shops as $s) {
            $sid = (string) ($s['id'] ?? '');
            if ($sid === '') {
                continue;
            }
            $sname = trim((string) ($s['name'] ?? "Shop {$sid}"));
            $pList = $s['pages'] ?? [];
            \App\Models\PosShop::updateOrCreate(
                ['shop_id' => $sid],
                [
                    'name' => $sname,
                    'api_key' => $apiKey,
                    'pages_count' => count($pList),
                    'active' => true,
                ]
            );
            $savedNames[] = "{$sname} ({$sid} · ".count($pList).' pages)';
            foreach ($pList as $sp) {
                if (! empty($sp['id']) && ! empty($sp['name'])) {
                    $posPageNames[(string) $sp['id']] = trim((string) $sp['name']);
                }
            }
        }

        // Auto-fill real Page Names on any tracked pages that previously had placeholder names
        foreach (Page::where('active', true)->get() as $p) {
            if (isset($posPageNames[$p->fb_page_id]) && (str_starts_with($p->name, 'FB Page ') || str_starts_with($p->name, 'Page '))) {
                $p->name = $posPageNames[$p->fb_page_id];
                if (! $p->product || str_starts_with($p->product, 'FB Page ')) {
                    $p->product = $p->name;
                }
                $p->save();
            }
        }

        \App\Services\PosClient::directory(true);
        Cache::flush();
        $this->triggerBackgroundSync();

        return back()->with('status', 'Connected POS Shop: '.implode(', ', $savedNames).'. Background sync started!');
    }

    public function deletePosShop(Request $request, \App\Models\PosShop $posShop)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $name = $posShop->name;
        $posShop->delete();
        \App\Services\PosClient::directory(true);
        Cache::flush();

        return back()->with('status', "Removed POS Shop \"{$name}\".");
    }

    public function addPage(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $bt = trim((string) $request->input('botcake_token'));
        $pt = trim((string) $request->input('pancake_token'));
        $fbPageId = trim((string) $request->input('fb_page_id'));

        foreach ([$bt, $pt] as $tok) {
            if ($fbPageId === '' && substr_count($tok, '.') === 2) {
                $payload = json_decode((string) base64_decode(strtr(explode('.', $tok)[1], '-_', '+/')), true);
                if (! empty($payload['id'])) {
                    $fbPageId = (string) $payload['id'];
                }
            }
        }

        if ($fbPageId === '') {
            return back()->with('status', 'Please enter a Facebook Page ID or paste a valid Botcake / Pancake page token.');
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            $posDir = \App\Services\PosClient::directory();
            if (! empty($posDir['id_'.$fbPageId]['page_name'])) {
                $name = $posDir['id_'.$fbPageId]['page_name'];
            }
        }
        if ($name === '') {
            $name = "Page {$fbPageId}";
        }

        $page = Page::firstOrNew(['fb_page_id' => $fbPageId]);
        $page->name = $name;
        $page->product = trim((string) $request->input('product')) ?: ($page->product ?: 'Brusko Capsule');
        $team = trim((string) $request->input('team'));
        if ($team !== '') {
            $page->team = $team;
        }
        if ($bt !== '') {
            $page->botcake_token = $bt;
        }
        if ($pt !== '') {
            $page->pancake_token = $pt;
        }
        $page->active = true;
        $page->save();

        Cache::flush();
        $this->triggerBackgroundSync($fbPageId);

        return back()->with('status', "Added/updated {$page->name} ({$fbPageId}) and started syncing Yesterday & Today!");
    }

    public function deletePage(Request $request, Page $page)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $name = $page->name;
        $page->users()->detach();
        $page->delete();
        Cache::flush();

        return back()->with('status', "Removed \"{$name}\" from Active Pages.");
    }

    public function saveTokens(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $botcakeInput = $request->input('botcake_token', []);
        $pancakeInput = $request->input('pancake_token', []);
        $teamInput = $request->input('page_team', []);
        $productInput = $request->input('page_product', []);
        $updated = 0;

        foreach (Page::where('active', true)->get() as $page) {
            $dirty = false;
            $bt = trim((string) ($botcakeInput[$page->fb_page_id] ?? ''));
            $pt = trim((string) ($pancakeInput[$page->fb_page_id] ?? ''));
            if ($bt !== '') {
                $page->botcake_token = $bt;
                $dirty = true;
            }
            if ($pt !== '') {
                $page->pancake_token = $pt;
                $dirty = true;
            }
            if (array_key_exists($page->fb_page_id, $teamInput)) {
                $newTeam = trim((string) $teamInput[$page->fb_page_id]) ?: null;
                if ($page->team !== $newTeam) {
                    $page->team = $newTeam;
                    $dirty = true;
                }
            }
            if (array_key_exists($page->fb_page_id, $productInput)) {
                $newProd = trim((string) $productInput[$page->fb_page_id]) ?: null;
                if ($page->product !== $newProd) {
                    $page->product = $newProd;
                    $dirty = true;
                }
            }
            if ($dirty) {
                $page->save();
                $updated++;
            }
        }

        if ($updated > 0) {
            Cache::flush();
            $this->triggerBackgroundSync();
        }

        return back()->with('status', $updated > 0
            ? "Updated {$updated} page(s) and started background sync."
            : 'No changes entered.');
    }

    public function bulkFetchTokens(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $messages = [];
        $pages = Page::where('active', true)->get()->keyBy(fn ($p) => 'id_'.$p->fb_page_id);

        $botcakeUserToken = trim((string) $request->input('botcake_user_token'));
        if ($botcakeUserToken !== '') {
            $matched = 0;
            for ($p = 1; $p <= 5; $p++) {
                $res = Http::withHeaders(['user-access-token' => $botcakeUserToken])
                    ->acceptJson()->timeout(30)
                    ->post(config('kpi.botcake.base_url')."/integration_page/list_access_token?page={$p}");
                $json = $res->json();
                $list = $json['response'] ?? $json['data'] ?? (is_array($json) && array_is_list($json) ? $json : []);
                if (! $res->successful() || empty($list)) {
                    if ($p === 1 && ! $res->successful()) {
                        $messages[] = "Botcake bulk fetch failed (HTTP {$res->status()}).";
                    }
                    break;
                }
                foreach ($list as $item) {
                    $pid = 'id_'.($item['page_id'] ?? '');
                    if (isset($pages[$pid]) && ! empty($item['public_token'])) {
                        $pages[$pid]->botcake_token = $item['public_token'];
                        $pages[$pid]->save();
                        $matched++;
                    }
                }
                if (count($list) < 200) {
                    break;
                }
            }
            $messages[] = "Botcake: matched {$matched} of {$pages->count()} tracked pages.";
        }

        $pancakeUserToken = trim((string) $request->input('pancake_user_token'));
        $allowGenerate = $request->boolean('generate_pancake_tokens');
        if ($pancakeUserToken !== '') {
            $matched = 0;
            $res = Http::acceptJson()->timeout(30)->get('https://pages.fm/api/v1/pages', ['access_token' => $pancakeUserToken]);
            if ($res->successful()) {
                $categorized = $res->json('categorized') ?? [];
                $rawPages = array_merge(
                    $res->json('pages') ?? [],
                    $categorized['activated'] ?? [],
                    $categorized['inactivated'] ?? []
                );
                $byId = [];
                foreach ($rawPages as $rp) {
                    if (! empty($rp['id'])) {
                        $byId['id_'.$rp['id']] = $rp;
                    }
                }
                foreach ($pages as $key => $pageModel) {
                    $pid = (string) $pageModel->fb_page_id;
                    $existingTok = $byId[$key]['settings']['page_access_token'] ?? ($byId[$key]['page_access_token'] ?? null);
                    if ($existingTok) {
                        $pageModel->pancake_token = $existingTok;
                        $pageModel->save();
                        $matched++;
                    } elseif ($allowGenerate && isset($byId[$key])) {
                        $gen = Http::acceptJson()->timeout(20)->post("https://pages.fm/api/v1/pages/{$pid}/generate_page_access_token?access_token=".urlencode($pancakeUserToken));
                        $newTok = $gen->json('page_access_token') ?? null;
                        if ($newTok) {
                            $pageModel->pancake_token = $newTok;
                            $pageModel->save();
                            $matched++;
                        }
                    }
                }
                $messages[] = "Pancake: matched {$matched} of {$pages->count()} tracked pages.";
            } else {
                $messages[] = "Pancake bulk fetch failed (HTTP {$res->status()}).";
            }
        }

        if ($messages) {
            Cache::flush();
            $this->triggerBackgroundSync();
        }

        return back()->with('status', $messages ? implode(' ', $messages).' Background sync started.' : 'Enter a Botcake or Pancake user token first.');
    }

    /** Scan Personal Botcake + Pancake Account Tokens and list ALL pages on that account so Admin can pick which to import. */
    public function scanPersonalTokens(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $uid = $request->user()->id;

        $botcakeUserToken = trim((string) $request->input('botcake_user_token'));
        $pancakeUserToken = trim((string) $request->input('pancake_user_token'));

        if ($botcakeUserToken === '' && $pancakeUserToken === '') {
            return back()->with('status', 'Please paste a Botcake Personal Token and/or Pancake Personal Token first.');
        }

        $warnings = [];
        $ownerName = '';

        // 1) Fetch POS shop page directory across ALL connected POS shops (KGOLD MAIN + KGOLD - HERBAL + future shops)
        $posNames = [];
        foreach (\App\Services\PosClient::directory(true) as $k => $entry) {
            if (! empty($entry['page_name'])) {
                $posNames[$k] = $entry['page_name'];
            }
        }

        // 2) Scan Botcake personal token
        $bcMap = [];
        if ($botcakeUserToken !== '') {
            for ($p = 1; $p <= 5; $p++) {
                $res = Http::withHeaders(['user-access-token' => $botcakeUserToken])
                    ->acceptJson()->timeout(25)
                    ->post(config('kpi.botcake.base_url')."/integration_page/list_access_token?page={$p}");
                $json = $res->json();
                $list = $json['response'] ?? $json['data'] ?? (is_array($json) && array_is_list($json) ? $json : []);
                if (! $res->successful() || empty($list)) {
                    if ($p === 1 && (! $res->successful() || ($json['success'] ?? true) === false)) {
                        $warnings[] = 'Botcake Personal Token was rejected — double-check the copied token.';
                    }
                    break;
                }
                foreach ($list as $item) {
                    $pid = (string) ($item['page_id'] ?? '');
                    if ($pid !== '' && ! empty($item['public_token'])) {
                        $bcMap['id_'.$pid] = [
                            'fb_page_id' => $pid,
                            'name' => trim((string) ($item['page_name'] ?? $item['name'] ?? '')),
                            'botcake_token' => $item['public_token'],
                        ];
                    }
                }
                if (count($list) < 200) {
                    break;
                }
            }
        }

        // 3) Scan Pancake personal token (auto-healing a trailing 1-char copy typo if needed)
        $pcMap = [];
        $validPancakeUserToken = '';
        if ($pancakeUserToken !== '') {
            if (substr_count($pancakeUserToken, '.') === 2) {
                $jwtPayload = json_decode((string) base64_decode(strtr(explode('.', $pancakeUserToken)[1], '-_', '+/')), true);
                $ownerName = trim((string) ($jwtPayload['name'] ?? $jwtPayload['fb_name'] ?? ''));
            }

            $candidates = [$pancakeUserToken];
            if (strlen($pancakeUserToken) > 100) {
                $candidates[] = substr($pancakeUserToken, 0, -1);
            }

            $pcOk = false;
            foreach ($candidates as $idx => $candTok) {
                $res = Http::acceptJson()->timeout(25)->get('https://pages.fm/api/v1/pages', ['access_token' => $candTok]);
                $cat = $res->json('categorized') ?? [];
                $rawPages = array_merge($res->json('pages') ?? [], $cat['activated'] ?? [], $cat['inactivated'] ?? []);
                if ($res->successful() && ($res->json('success') !== false) && count($rawPages) > 0) {
                    $pcOk = true;
                    $validPancakeUserToken = $candTok;
                    if ($idx === 1) {
                        $warnings[] = 'Auto-fixed 1 extra character at the end of your Pancake Personal Token.';
                    }
                    foreach ($rawPages as $rp) {
                        $pid = (string) ($rp['id'] ?? '');
                        if ($pid === '') {
                            continue;
                        }
                        $pcMap['id_'.$pid] = [
                            'fb_page_id' => $pid,
                            'name' => trim((string) ($rp['name'] ?? '')),
                            'pancake_token' => $rp['settings']['page_access_token'] ?? ($rp['page_access_token'] ?? null),
                        ];
                    }
                    break;
                }
            }
            if (! $pcOk) {
                $warnings[] = 'Pancake Personal Token returned 0 pages (check if an extra character was copied at the end).';
            }
        }

        // 4) Merge discovered pages
        $existing = Page::all()->keyBy(fn ($p) => 'id_'.$p->fb_page_id);
        $allKeys = array_unique(array_merge(array_keys($bcMap), array_keys($pcMap)));
        $discovered = [];

        foreach ($allKeys as $key) {
            $pid = $bcMap[$key]['fb_page_id'] ?? $pcMap[$key]['fb_page_id'];
            $ex = $existing[$key] ?? null;
            $name = $pcMap[$key]['name'] ?? '';
            if ($name === '') {
                $name = $bcMap[$key]['name'] ?? '';
            }
            if ($name === '' && ! empty($posNames[$key])) {
                $name = $posNames[$key];
            }
            if ($name === '' && $ex) {
                $name = $ex->name;
            }
            if ($name === '') {
                $name = "FB Page {$pid}";
            }

            $hasBc = ! empty($bcMap[$key]['botcake_token']) || ! empty($ex?->botcake_token);
            $hasPcTok = ! empty($pcMap[$key]['pancake_token']) || ! empty($ex?->pancake_token);
            $inPcAccount = isset($pcMap[$key]);
            $inPos = isset($posNames[$key]);
            $isActive = (bool) ($ex?->active ?? false);

            $score = ($isActive ? 0 : 10) + ($hasBc ? 4 : 0) + (($hasPcTok || $inPcAccount) ? 4 : 0) + ($inPos ? 2 : 0);

            $discovered[$key] = [
                'fb_page_id' => $pid,
                'name' => $name,
                'already_active' => $isActive,
                'has_botcake' => ! empty($bcMap[$key]['botcake_token']),
                'has_pancake_token' => ! empty($pcMap[$key]['pancake_token']),
                'can_generate_pancake' => $inPcAccount && empty($pcMap[$key]['pancake_token']),
                'in_pos' => $inPos,
                'botcake_token' => $bcMap[$key]['botcake_token'] ?? null,
                'pancake_token' => $pcMap[$key]['pancake_token'] ?? null,
                'score' => $score,
            ];
        }

        uasort($discovered, fn ($a, $b) => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

        Cache::put("kpi:scan:{$uid}", [
            'owner_name' => $ownerName,
            'pancake_user_token' => $validPancakeUserToken,
            'botcake_count' => count($bcMap),
            'pancake_count' => count($pcMap),
            'warnings' => $warnings,
            'pages' => $discovered,
        ], 1200);

        $summary = 'Scanned '.count($discovered).' page(s) ('.count($bcMap).' Botcake, '.count($pcMap).' Pancake). Select the pages to add or update below.';
        if ($warnings) {
            $summary .= ' '.implode(' ', $warnings);
        }

        return redirect()->to(route('settings').'#scanned-pages')->with('status', $summary);
    }

    /** Import or update only the pages ticked from the Personal Token scan result. */
    public function importScannedPages(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $uid = $request->user()->id;
        $scan = Cache::get("kpi:scan:{$uid}");
        if (! $scan || empty($scan['pages'])) {
            return back()->with('status', 'Scan session expired — please click Scan Account Pages again.');
        }

        $selectedIds = array_map('strval', (array) $request->input('selected_pages', []));
        if (empty($selectedIds)) {
            return back()->with('status', 'Please check at least one page to import or update.');
        }

        $allowGenerate = $request->boolean('generate_pancake_tokens', true);
        $team = trim((string) $request->input('team'));
        $product = trim((string) $request->input('product'));
        $assignUserId = (int) $request->input('assign_user_id', 0);
        $pancakeUserToken = (string) ($scan['pancake_user_token'] ?? '');

        $importedDbIds = [];
        $addedCount = 0;
        $updatedCount = 0;

        foreach ($selectedIds as $pid) {
            $key = 'id_'.$pid;
            if (! isset($scan['pages'][$key])) {
                continue;
            }
            $info = $scan['pages'][$key];
            $page = Page::firstOrNew(['fb_page_id' => $pid]);
            $isNew = ! $page->exists || ! $page->active;

            if (! $page->exists || str_starts_with((string) $page->name, 'Page ') || str_starts_with((string) $page->name, 'FB Page ')) {
                $page->name = $info['name'];
            } elseif (! empty($info['name']) && ! str_starts_with($info['name'], 'FB Page ')) {
                $page->name = $info['name'];
            }

            if ($team !== '') {
                $page->team = $team;
            }
            if ($product !== '') {
                $page->product = $product;
            } elseif (! $page->product) {
                $page->product = $info['name'];
            }

            if (! empty($info['botcake_token'])) {
                $page->botcake_token = $info['botcake_token'];
            }

            $pcTok = $info['pancake_token'] ?? null;
            if (! $pcTok && $allowGenerate && ! empty($info['can_generate_pancake']) && $pancakeUserToken !== '') {
                try {
                    $gen = Http::acceptJson()->timeout(15)->post("https://pages.fm/api/v1/pages/{$pid}/generate_page_access_token?access_token=".urlencode($pancakeUserToken));
                    $pcTok = $gen->json('page_access_token') ?? $gen->json('settings.page_access_token') ?? $gen->json('data.page_access_token') ?? null;
                } catch (\Throwable) {
                }
            }
            if (! empty($pcTok)) {
                $page->pancake_token = $pcTok;
            }

            $page->active = true;
            $page->save();
            $importedDbIds[] = $page->id;

            // Warm local avatar file asynchronously-safe if missing
            $avFile = public_path("avatars/{$pid}.jpg");
            if (! is_file($avFile)) {
                try {
                    $avRes = Http::timeout(4)->get("https://graph.facebook.com/{$pid}/picture?type=normal");
                    if ($avRes->successful() && str_starts_with((string) $avRes->header('Content-Type'), 'image/')) {
                        @file_put_contents($avFile, $avRes->body());
                    }
                } catch (\Throwable) {
                }
            }

            if ($isNew) {
                $addedCount++;
            } else {
                $updatedCount++;
            }
        }

        if ($assignUserId > 0 && ! empty($importedDbIds)) {
            $member = \App\Models\User::find($assignUserId);
            if ($member && ! $member->isAdmin()) {
                $member->pages()->syncWithoutDetaching($importedDbIds);
            }
        }

        Cache::forget("kpi:scan:{$uid}");
        Cache::flush();
        if (count($selectedIds) === 1) {
            $this->triggerBackgroundSync($selectedIds[0]);
        } else {
            $this->triggerBackgroundSync();
        }

        return redirect()->route('settings')->with('status', "Connected {$addedCount} new page(s) and updated {$updatedCount} existing page(s)! Background sync started for Yesterday & Today.");
    }

    public function exportOrders(Request $request): StreamedResponse
    {
        [$report] = $this->baseContext($request);
        $pageId = $report->pageId;
        $from = $report->from;
        $to = $report->to;
        $filters = $request->only(['source_type', 'source_id', 'assisted', 'valid']);
        $orders = $report->orderList($filters, 1000);
        $labels = PosOrder::SOURCES + ['pending' => 'POS Only (needs Pancake token)'];

        return response()->streamDownload(function () use ($orders, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Order ID', 'Page', 'Ordered At (Manila)', 'Customer', 'Phone', 'PSID', 'Items', 'Amount (PHP)', 'Status', 'Valid', 'Attributed Source', 'Touchpoint Name', 'Assisted By', 'Closed By']);
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->id,
                    $o->page_name ?? $o->page_id,
                    kpi_time($o->ordered_at, 'Y-m-d H:i:s'),
                    $o->customer_name,
                    $o->phone,
                    $o->psid,
                    $o->items_summary,
                    $o->total,
                    $o->status_name,
                    $o->is_valid ? 'Yes' : 'No',
                    $labels[$o->source_type ?? 'pending'] ?? 'POS Only',
                    $o->source_name,
                    $o->assisted_name,
                    $o->closed_by,
                ]);
            }
            fclose($out);
        }, "pos-orders-{$pageId}-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportBroadcasts(Request $request): StreamedResponse
    {
        [$report] = $this->baseContext($request);
        $pageId = $report->pageId;
        $from = $report->from;
        $to = $report->to;
        $broadcasts = $report->broadcasts(false);

        return response()->streamDownload(function () use ($broadcasts) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Broadcast ID', 'Page', 'Segment', 'Broadcast Name', 'Sent At (Manila)', 'Method', 'Status', 'Targeted', 'Delivered', 'Success Rate %', 'Seen', 'Clicked', 'Failed', 'Blocked (#551)', 'API Limit (#613)', 'Failed Reason', 'Phone Left', 'Lead Rate %', 'Credited Orders', 'Sales (PHP)', 'Broadcast CVR %', 'Assisted Orders']);
            foreach ($broadcasts as $b) {
                fputcsv($out, [
                    $b->id,
                    $b->page_name ?? $b->page_id,
                    $b->segment,
                    $b->name,
                    kpi_time($b->sent_at, 'Y-m-d H:i'),
                    $b->send_method,
                    $b->status,
                    $b->targeted,
                    $b->delivered,
                    $b->success_rate !== null ? round($b->success_rate * 100, 2) : '',
                    $b->seen,
                    $b->clicked,
                    $b->failed,
                    $b->blocked_count,
                    $b->api_limit_count,
                    $b->failed_reason,
                    $b->phone_count,
                    $b->lead_rate !== null ? round($b->lead_rate * 100, 2) : '',
                    $b->orders,
                    $b->sales,
                    $b->cvr !== null ? round($b->cvr * 100, 2) : '',
                    $b->assisted,
                ]);
            }
            fclose($out);
        }, "broadcasts-{$pageId}-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function sync(Request $request)
    {
        Cache::forget('kpi:sync_meta');
        $this->triggerBackgroundSync();

        return back()->with('status', 'Syncing yesterday & today across all tracked pages in the background — refresh in ~1 minute.');
    }

    private function triggerBackgroundSync(?string $onlyPageId = null): void
    {
        $artisan = base_path('artisan');
        $pageArg = $onlyPageId ? ' --page='.escapeshellarg($onlyPageId) : '';
        if (PHP_OS_FAMILY === 'Windows') {
            $php = PHP_BINARY;
            pclose(popen("start \"kpi-sync\" /B \"{$php}\" \"{$artisan}\" kpi:sync --days=2{$pageArg} > NUL 2>&1", 'r'));
        } else {
            $php = is_executable('/usr/local/bin/php') ? '/usr/local/bin/php' : 'php';
            exec("{$php} ".escapeshellarg($artisan)." kpi:sync --days=2{$pageArg} > /dev/null 2>&1 &");
        }
    }
}
