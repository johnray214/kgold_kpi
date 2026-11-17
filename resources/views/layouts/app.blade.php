<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'KGOLD AUTOMATION TEAM KPI' }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface text-ink">
    @php
        $tz = config('kpi.timezone');
        $today = now($tz)->toDateString();
        $yesterday = now($tz)->subDay()->toDateString();
        $navQuery = array_filter(['product' => $product ?? null, 'team' => $team ?? null, 'page' => $pageId, 'from' => $from, 'to' => $to], fn ($v) => $v !== null && $v !== '');
        $preset = fn ($f, $t) => request()->fullUrlWithQuery(['from' => $f, 'to' => $t]);
        $isPreset = fn ($f, $t) => $from === $f && $to === $t;
        $authUser = auth()->user();
        $isAdmin = ! $authUser || $authUser->isAdmin();
        $missingTokenCount = isset($pages) ? $pages->filter(fn ($p) => isset($p->has_botcake) ? (! $p->has_botcake || ! $p->has_pancake) : (empty($p->botcake_token) || empty($p->pancake_token)))->count() : 0;
        $selectedPageModel = isset($pages) && $pageId !== 'all' ? $pages->firstWhere('fb_page_id', (string) $pageId) : null;
        $userInitials = $authUser ? collect(preg_split('/\s+/', trim($authUser->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : 'AD';
        if (! isset($unansweredInboxBadge)) {
            $unansweredInboxBadge = 0;
            if ($authUser) {
                $scopedBadgePageIds = (isset($pageId) && $pageId !== 'all')
                    ? [(string) $pageId]
                    : (isset($pages)
                        ? $pages->pluck('fb_page_id')->map(fn ($id) => (string) $id)->values()->all()
                        : ($isAdmin ? [] : $authUser->pages()->where('pages.active', true)->pluck('pages.fb_page_id')->map(fn ($id) => (string) $id)->all()));
                $bFrom = $from ?? $today;
                $bTo = $to ?? $bFrom;
                $badgeScopeKey = 'kpi:inbox_badge:' . md5(($isAdmin ? 'adm:' : "u{$authUser->id}:") . "{$bFrom}:{$bTo}:" . implode(',', $scopedBadgePageIds));
                $unansweredInboxBadge = \Illuminate\Support\Facades\Cache::remember($badgeScopeKey, 20, function () use ($isAdmin, $scopedBadgePageIds, $bFrom, $bTo, $tz) {
                    if (! \Illuminate\Support\Facades\Schema::hasTable('inbox_conversations')) {
                        return 0;
                    }
                    try {
                        $sUtc = \Illuminate\Support\Carbon::parse($bFrom, $tz)->startOfDay()->utc();
                        $eUtc = \Illuminate\Support\Carbon::parse($bTo, $tz)->endOfDay()->utc();
                    } catch (\Throwable) {
                        $sUtc = now($tz)->startOfDay()->utc();
                        $eUtc = now($tz)->endOfDay()->utc();
                    }
                    $q = \App\Models\InboxConversation::where('last_sender_type', 'customer')
                        ->where('has_order_today', false)
                        ->whereBetween('last_message_at', [$sUtc, $eUtc]);
                    if (! empty($scopedBadgePageIds)) {
                        $q->whereIn('page_id', $scopedBadgePageIds);
                    } elseif (! $isAdmin) {
                        return 0;
                    }

                    return (int) $q->count();
                });
            }
        }
    @endphp

    {{-- Top gliding navigation progress bar --}}
    <div id="top-nav-progress" aria-hidden="true"
         class="fixed top-0 left-0 z-[60] h-[2.5px] w-0 bg-linear-to-r from-primary via-indigo-400 to-sky-400 opacity-0 transition-[width,opacity] duration-300 ease-out pointer-events-none"></div>

    {{-- Mobile sidebar backdrop --}}
    <div id="sidebar-backdrop" class="fixed inset-0 z-40 bg-slate-900/30 backdrop-blur-[1px] hidden lg:hidden"></div>

    <div class="min-h-dvh flex">
        {{-- Left Sidebar (Botcake Brand Theme) --}}
        <aside id="app-sidebar"
               class="fixed lg:sticky top-0 left-0 z-50 h-dvh w-64 shrink-0 bg-white border-r border-line flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200">
            {{-- Brand header --}}
            <div class="h-15 px-5 border-b border-line flex items-center justify-between shrink-0">
                <a href="{{ route('dashboard', $navQuery) }}" class="flex items-center gap-2.5 min-w-0 cursor-pointer">
                    <x-brand-logo size="size-8" iconSize="19"/>
                    <div class="leading-tight min-w-0">
                        <span class="font-extrabold tracking-tight text-[12.5px] text-ink block truncate">KGOLD AUTOMATION</span>
                        <span class="font-extrabold tracking-wider text-[11px] text-slate-500 block mt-0.5">TEAM <span class="text-primary">KPI</span></span>
                    </div>
                </a>
                <button id="close-sidebar" type="button" aria-label="Close navigation" class="lg:hidden p-1.5 rounded-lg text-muted hover:bg-slate-100 cursor-pointer">
                    <x-icon name="x" class="size-4"/>
                </button>
            </div>

            {{-- Grouped Navigation --}}
            <div class="p-3 space-y-4 flex-1 overflow-y-auto">
                {{-- 1. OVERVIEW -> Dashboard --}}
                <div>
                    <div class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Overview</div>
                    <nav class="space-y-0.5">
                        @php $dActive = request()->routeIs('dashboard'); @endphp
                        <a href="{{ route('dashboard', $navQuery) }}" class="{{ $dActive ? 'nav-item-active' : 'nav-item' }}">
                            <x-icon name="grid" class="size-[17px] shrink-0 {{ $dActive ? 'text-primary' : 'text-slate-400' }}"/>
                            <span class="flex-1 truncate">Dashboard</span>
                            @if ($dActive)<span class="size-1.5 rounded-full bg-primary"></span>@endif
                        </a>
                    </nav>
                </div>

                {{-- 2. ANALYTICS -> Broadcast, Sequence, Botcake AI, Inbox --}}
                <div>
                    <div class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Analytics</div>
                    <nav class="space-y-0.5">
                        @foreach ([
                            ['route' => 'broadcasts', 'label' => 'Broadcast', 'icon' => 'megaphone'],
                            ['route' => 'sequences', 'label' => 'Sequence', 'icon' => 'workflow'],
                            ['route' => 'botcake.ai', 'label' => 'Botcake AI', 'icon' => 'sparkles'],
                        ] as $item)
                            @php $active = request()->routeIs($item['route']); @endphp
                            <a href="{{ route($item['route'], $navQuery) }}" class="{{ $active ? 'nav-item-active' : 'nav-item' }}">
                                <x-icon :name="$item['icon']" class="size-[17px] shrink-0 {{ $active ? 'text-primary' : 'text-slate-400' }}"/>
                                <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                @if ($active)<span class="size-1.5 rounded-full bg-primary"></span>@endif
                            </a>
                        @endforeach

                        @php $inboxActive = request()->routeIs('inbox'); @endphp
                        <a href="{{ route('inbox', $navQuery) }}" class="{{ $inboxActive ? 'nav-item-active' : 'nav-item' }}">
                            <x-icon name="inbox" class="size-[17px] shrink-0 {{ $inboxActive ? 'text-primary' : 'text-slate-400' }}"/>
                            <span class="flex-1 truncate">Inbox Monitor</span>
                            @if ($unansweredInboxBadge > 0)
                                <span class="pill !px-1.5 !py-0 bg-red-100 text-bad num">{{ $unansweredInboxBadge }}</span>
                            @elseif ($inboxActive)
                                <span class="size-1.5 rounded-full bg-primary"></span>
                            @endif
                        </a>
                    </nav>
                </div>

                {{-- 3. POS -> POS Order --}}
                <div>
                    <div class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">POS</div>
                    <nav class="space-y-0.5">
                        @php $oActive = request()->routeIs('orders'); @endphp
                        <a href="{{ route('orders', $navQuery + ['valid' => 1]) }}" class="{{ $oActive ? 'nav-item-active' : 'nav-item' }}">
                            <x-icon name="cart" class="size-[17px] shrink-0 {{ $oActive ? 'text-primary' : 'text-slate-400' }}"/>
                            <span class="flex-1 truncate">POS Order</span>
                            @if ($oActive)<span class="size-1.5 rounded-full bg-primary"></span>@endif
                        </a>
                    </nav>
                </div>

                {{-- 4. WORKSPACE -> EOD Report, Accounts, Profile, Pages & Tokens --}}
                <div>
                    <div class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Workspace</div>
                    <nav class="space-y-0.5">
                        @php $eodActive = request()->routeIs('eod'); @endphp
                        <a href="{{ route('eod') }}" class="{{ $eodActive ? 'nav-item-active' : 'nav-item' }}">
                            <x-icon name="file-text" class="size-[17px] shrink-0 {{ $eodActive ? 'text-primary' : 'text-slate-400' }}"/>
                            <span class="flex-1 truncate">EOD Report</span>
                            @if ($eodActive)<span class="size-1.5 rounded-full bg-primary"></span>@endif
                        </a>

                        @if ($isAdmin)
                            @php $accActive = request()->routeIs('accounts'); @endphp
                            <a href="{{ route('accounts', $navQuery) }}" class="{{ $accActive ? 'nav-item-active' : 'nav-item' }}">
                                <x-icon name="users" class="size-[17px] shrink-0 {{ $accActive ? 'text-primary' : 'text-slate-400' }}"/>
                                <span class="flex-1 truncate">Accounts</span>
                                @if ($accActive)<span class="size-1.5 rounded-full bg-primary"></span>@endif
                            </a>
                        @endif

                        @php $profActive = request()->routeIs('profile'); @endphp
                        <a href="{{ route('profile', $navQuery) }}" class="{{ $profActive ? 'nav-item-active' : 'nav-item' }}">
                            <x-icon name="user" class="size-[17px] shrink-0 {{ $profActive ? 'text-primary' : 'text-slate-400' }}"/>
                            <span class="flex-1 truncate">Profile</span>
                            @if ($profActive)<span class="size-1.5 rounded-full bg-primary"></span>@endif
                        </a>

                        @if ($isAdmin)
                            @php $setActive = request()->routeIs('settings'); @endphp
                            <a href="{{ route('settings', $navQuery) }}" class="{{ $setActive ? 'nav-item-active' : 'nav-item' }}">
                                <x-icon name="settings" class="size-[17px] shrink-0 {{ $setActive ? 'text-primary' : 'text-slate-400' }}"/>
                                <span class="flex-1 truncate">Pages & Tokens</span>
                                @if ($missingTokenCount > 0)
                                    <span class="pill !px-1.5 !py-0 bg-amber-100 text-amber-800">{{ $missingTokenCount }}</span>
                                @endif
                            </a>
                        @endif
                    </nav>
                </div>

                {{-- 5. Collapsible All Pages Accordion (smooth CSS grid-row expand/collapse + rotating arrow) --}}
                @if (isset($pages) && $pages->isNotEmpty())
                    <div class="pt-1 border-t border-line/70">
                        <button type="button" id="sidebar-pages-toggle" aria-expanded="false"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-muted hover:bg-slate-50 hover:text-ink cursor-pointer transition-colors duration-150">
                            <span class="flex items-center gap-2">
                                <x-icon name="layers" class="size-4 text-slate-400"/>
                                <span>All Pages ({{ $pages->count() }})</span>
                            </span>
                            <x-icon name="chevron-down" id="sidebar-pages-chevron" class="size-4 text-slate-400 transition-transform duration-200 ease-out"/>
                        </button>

                        <div id="sidebar-pages-wrap" class="grid grid-rows-[0fr] opacity-0 transition-[grid-template-rows,opacity] duration-200 ease-out">
                            <div id="sidebar-pages-list" class="overflow-hidden">
                                <div class="mt-1 space-y-0.5 pl-1">
                                    <a href="{{ request()->fullUrlWithQuery(['page' => 'all']) }}" data-nav-link
                                       @class([
                                           'flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs cursor-pointer transition-colors duration-150',
                                           'bg-primary-soft text-primary font-semibold' => $pageId === 'all',
                                           'text-muted hover:bg-slate-50 hover:text-ink' => $pageId !== 'all',
                                       ])>
                                        <span class="size-6 rounded-lg bg-primary/10 text-primary font-bold text-[10px] grid place-items-center shrink-0">ALL</span>
                                        <span class="truncate flex-1">All {{ $pages->count() }} Pages</span>
                                    </a>
                                    @foreach ($pages as $sp)
                                        @php $pActive = (string) $pageId === (string) $sp->fb_page_id; @endphp
                                        <a href="{{ request()->fullUrlWithQuery(['page' => $sp->fb_page_id]) }}" data-nav-link
                                           @class([
                                               'flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs cursor-pointer transition-colors duration-150',
                                               'bg-primary-soft text-primary font-semibold' => $pActive,
                                               'text-muted hover:bg-slate-50 hover:text-ink' => ! $pActive,
                                           ]) title="{{ $sp->name }}">
                                            <x-page-avatar :id="$sp->fb_page_id" :name="$sp->name" size="size-6 !rounded-lg !text-[9px]"/>
                                            <span class="truncate flex-1">{{ $sp->name }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Account footer + Logout --}}
            <div class="p-3 border-t border-line bg-slate-50/50 space-y-2 shrink-0">
                @if ($authUser)
                    <div class="flex items-center justify-between gap-2 px-2 py-1">
                        <a href="{{ route('profile', $navQuery) }}" class="flex items-center gap-2.5 min-w-0 cursor-pointer group">
                            <span class="size-8 rounded-xl bg-primary-soft text-primary font-bold text-xs grid place-items-center shrink-0 ring-1 ring-primary/15">
                                {{ $userInitials }}
                            </span>
                            <div class="min-w-0 leading-tight">
                                <div class="text-xs font-semibold text-ink group-hover:text-primary truncate">{{ $authUser->name }}</div>
                                <div class="text-[10px] text-muted truncate">{{ ucfirst($authUser->role) }}</div>
                            </div>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" title="Sign out" aria-label="Sign out" class="p-1.5 rounded-lg text-muted hover:text-bad hover:bg-red-50 cursor-pointer transition-colors">
                                <x-icon name="log-out" class="size-4"/>
                            </button>
                        </form>
                    </div>
                @endif

                <div class="flex items-center justify-between text-[10px] text-muted px-2">
                    <span class="flex items-center gap-1.5">
                        @if (!empty($syncRunning))
                            <span class="size-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>Syncing…</span>
                        @else
                            <span class="size-1.5 rounded-full bg-good"></span>
                            <span>Synced {{ isset($lastSync) && $lastSync ? kpi_time($lastSync->finished_at, 'g:i A') : 'never' }}</span>
                        @endif
                    </span>
                    <span class="font-mono text-slate-400">PH</span>
                </div>
            </div>
        </aside>

        {{-- Main content column --}}
        <div class="flex-1 min-w-0 flex flex-col">
            {{-- Top bar with Custom Avatar Page Picker Dropdown --}}
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-xs border-b border-line">
                <div class="max-w-[1440px] mx-auto px-4 sm:px-6 py-2.5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <button id="open-sidebar" type="button" aria-label="Open navigation" class="lg:hidden p-1.5 rounded-lg border border-line text-ink hover:bg-slate-50 cursor-pointer">
                            <x-icon name="menu" class="size-4"/>
                        </button>

                        {{-- Custom Page Picker Popover WITH real Facebook Page pictures --}}
                        @if (isset($pages) && $pages->isNotEmpty())
                            <div class="relative" id="top-page-picker">
                                <button type="button" id="top-page-btn" aria-haspopup="listbox" aria-expanded="false"
                                        class="field flex items-center gap-2.5 pr-2.5 cursor-pointer min-w-56 max-w-72 justify-between">
                                    <span class="flex items-center gap-2 min-w-0">
                                        @if ($selectedPageModel)
                                            <x-page-avatar :id="$selectedPageModel->fb_page_id" :name="$selectedPageModel->name" size="size-5 !rounded-md !text-[8px]"/>
                                            <span class="truncate font-semibold text-ink">{{ $selectedPageModel->name }}</span>
                                        @else
                                            <span class="flex -space-x-1.5 shrink-0">
                                                @foreach ($pages->take(3) as $tp)
                                                    <x-page-avatar :id="$tp->fb_page_id" :name="$tp->name" size="size-5 !rounded-full !ring-1 !ring-white !text-[7px]"/>
                                                @endforeach
                                            </span>
                                            <span class="truncate font-semibold text-ink">All {{ $pages->count() }} Pages</span>
                                        @endif
                                    </span>
                                    <x-icon name="chevron-down" id="top-page-chevron" class="size-3.5 text-muted shrink-0 transition-transform duration-200 ease-out"/>
                                </button>

                                <div id="top-page-menu" role="listbox"
                                     class="opacity-0 scale-95 -translate-y-1 pointer-events-none origin-top-left transition-all duration-150 ease-out absolute left-0 mt-1.5 w-80 max-h-96 overflow-y-auto rounded-2xl bg-white border border-line shadow-xl p-1.5 z-50">
                                    @if ($pages->count() > 6)
                                        <div class="sticky top-0 bg-white pb-1.5 z-10">
                                            <div class="relative">
                                                <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                                                <input type="search" data-table-search="top-page-menu-list" placeholder="Search {{ $pages->count() }} pages…" aria-label="Filter pages" class="search-field w-full !min-h-[32px]">
                                            </div>
                                        </div>
                                    @endif
                                    <a href="{{ request()->fullUrlWithQuery(['page' => 'all']) }}"
                                       @class([
                                           'flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs cursor-pointer transition-colors',
                                           'bg-primary-soft text-primary font-semibold' => $pageId === 'all',
                                           'text-ink hover:bg-slate-50' => $pageId !== 'all',
                                       ])>
                                        <span class="flex -space-x-1.5 shrink-0">
                                            @foreach ($pages->take(3) as $tp)
                                                <x-page-avatar :id="$tp->fb_page_id" :name="$tp->name" size="size-5 !rounded-full !ring-1 !ring-white !text-[7px]"/>
                                            @endforeach
                                        </span>
                                        <span class="flex-1 truncate">All {{ $pages->count() }} Pages (Combined)</span>
                                        @if ($pageId === 'all')<x-icon name="check" class="size-3.5 text-primary"/>@endif
                                    </a>

                                    <div class="my-1 border-t border-line"></div>

                                    <div id="top-page-menu-list" class="space-y-0.5">
                                        @foreach ($pages as $p)
                                            @php $sel = (string) $pageId === (string) $p->fb_page_id; @endphp
                                            <a href="{{ request()->fullUrlWithQuery(['page' => $p->fb_page_id]) }}"
                                               data-search-item data-search="{{ strtolower($p->name.' '.$p->fb_page_id.' '.$p->team) }}"
                                               @class([
                                                   'flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs cursor-pointer transition-colors',
                                                   'bg-primary-soft text-primary font-semibold' => $sel,
                                                   'text-ink hover:bg-slate-50' => ! $sel,
                                               ])>
                                                <x-page-avatar :id="$p->fb_page_id" :name="$p->name" size="size-6 !rounded-lg !text-[9px]"/>
                                                <span class="flex-1 truncate">{{ $p->name }}</span>
                                                @if ($sel)<x-icon name="check" class="size-3.5 text-primary"/>@endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Product & Team Filters --}}
                        @if (isset($products) && $products->isNotEmpty())
                            <form method="GET" action="{{ url()->current() }}" class="inline-flex">
                                @foreach (request()->except(['product', 'page']) as $k => $v)
                                    @if (! is_array($v))
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach
                                <input type="hidden" name="page" value="all">
                                <select name="product" onchange="this.form.requestSubmit()" aria-label="Filter by Product"
                                        class="field !min-h-[36px] !text-xs font-medium cursor-pointer max-w-44 {{ !empty($product) ? '!border-primary !bg-primary-soft/40 !text-primary font-semibold' : '' }}">
                                    <option value="">All Products ({{ $products->count() }})</option>
                                    @foreach ($products as $prodOpt)
                                        <option value="{{ $prodOpt }}" @selected(($product ?? '') === $prodOpt)>{{ $prodOpt }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif

                        @if (isset($teams) && ($teams->isNotEmpty() || $isAdmin))
                            <form method="GET" action="{{ url()->current() }}" class="inline-flex">
                                @foreach (request()->except(['team', 'page']) as $k => $v)
                                    @if (! is_array($v))
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach
                                <input type="hidden" name="page" value="all">
                                <select name="team" onchange="this.form.requestSubmit()" aria-label="Filter by Team"
                                        class="field !min-h-[36px] !text-xs font-medium cursor-pointer max-w-44 {{ !empty($team) ? '!border-primary !bg-primary-soft/40 !text-primary font-semibold' : '' }}">
                                    <option value="">All Teams{{ $teams->isNotEmpty() ? ' ('.$teams->count().')' : '' }}</option>
                                    @foreach ($teams as $teamOpt)
                                        <option value="{{ $teamOpt }}" @selected(($team ?? '') === $teamOpt)>{{ $teamOpt }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif

                        {{-- Date filter form --}}
                        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center gap-2">
                            @foreach (request()->except(['from', 'to']) as $k => $v)
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endforeach

                            <div class="hidden sm:flex items-center gap-1.5 rounded-xl border border-line bg-slate-50/70 px-2.5 min-h-[36px] text-xs">
                                <x-icon name="calendar" class="size-3.5 text-muted"/>
                                <input type="date" name="from" value="{{ $from }}" aria-label="From date" class="bg-transparent num font-medium text-ink cursor-pointer focus:outline-none">
                                <span class="text-slate-300">–</span>
                                <input type="date" name="to" value="{{ $to }}" aria-label="To date" class="bg-transparent num font-medium text-ink cursor-pointer focus:outline-none">
                                <button type="submit" class="ml-1 text-[11px] font-semibold text-primary hover:underline cursor-pointer">Apply</button>
                            </div>

                            <div class="flex items-center rounded-xl bg-slate-100 p-0.5 text-xs border border-line">
                                @foreach ([
                                    ['Today', $today, $today],
                                    ['Yesterday', $yesterday, $yesterday],
                                    ['2D', $yesterday, $today],
                                ] as [$label, $f, $t])
                                    <a href="{{ $preset($f, $t) }}" @class([
                                        'px-2.5 py-1 rounded-lg cursor-pointer transition-all duration-150 whitespace-nowrap',
                                        'bg-white shadow-2xs font-semibold text-primary' => $isPreset($f, $t),
                                        'text-muted hover:text-ink font-medium' => ! $isPreset($f, $t),
                                    ])>{{ $label }}</a>
                                @endforeach
                            </div>
                        </form>
                    </div>

                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('sync') }}" data-sync-form>
                            @csrf
                            <button type="submit" class="btn-primary">
                                <x-icon name="refresh" @class(['size-3.5', 'animate-spin' => !empty($syncRunning)])/>
                                <span data-sync-label>{{ !empty($syncRunning) ? 'Syncing…' : 'Sync now' }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="max-w-[1440px] w-full mx-auto px-4 sm:px-6 py-5 space-y-5 flex-1">
                @if (session('status'))
                    <div id="status-toast" role="status" aria-live="polite"
                         class="card !border-emerald-200 !bg-emerald-50 px-4 py-3 text-xs font-medium text-emerald-900 flex items-center gap-2 transition-all duration-200">
                        <x-icon name="check" class="size-4 text-good shrink-0"/>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="card !border-red-200 !bg-red-50 px-4 py-3 text-xs font-medium text-bad space-y-1">
                        @foreach ($errors->all() as $err)
                            <div class="flex items-center gap-2"><x-icon name="alert" class="size-3.5 shrink-0"/><span>{{ $err }}</span></div>
                        @endforeach
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    {{-- Custom Delete Confirmation Modal (shared by Accounts & Active Pages) --}}
    <div id="confirm-delete-modal" role="dialog" aria-modal="true" aria-labelledby="cdm-title"
         class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-[2px] hidden place-items-center p-4">
        <div class="card w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-6 space-y-4">
                <div class="flex items-start gap-3.5">
                    <span class="size-11 rounded-2xl bg-red-50 text-bad ring-1 ring-red-100 grid place-items-center shrink-0">
                        <x-icon name="trash" class="size-5"/>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 id="cdm-title" class="text-[15px] font-bold text-ink">Confirm Delete</h2>
                        <div id="cdm-name" class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-lg bg-red-50/80 text-bad font-semibold text-xs break-all"></div>
                        <p id="cdm-desc" class="text-xs text-muted mt-2 leading-relaxed"></p>
                    </div>
                </div>
            </div>
            <form id="confirm-delete-form" method="POST" action="#"
                  class="px-6 py-3.5 bg-slate-50/80 border-t border-line flex items-center justify-end gap-2">
                @csrf
                @method('DELETE')
                <button type="button" data-close-modal="confirm-delete-modal" class="btn-ghost">Cancel</button>
                <button type="submit" data-loading-text="Deleting…"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-bad px-4 min-h-[36px] text-xs font-semibold text-white hover:bg-red-700 transition-colors cursor-pointer shadow-2xs">
                    <x-icon name="trash" class="size-3.5"/> Yes, Delete
                </button>
            </form>
        </div>
    </div>
</body>
</html>
