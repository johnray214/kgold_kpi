@extends('layouts.app', ['title' => 'Inbox Monitor · KGOLD AUTOMATION TEAM KPI'])

@php
    $tz = config('kpi.timezone', 'Asia/Manila');
    $baseQ = array_filter([
        'product' => $product ?? null,
        'team' => $team ?? null,
        'page' => $pageId ?? null,
        'from' => $from ?? null,
        'to' => $to ?? null,
    ], fn ($v) => $v !== null && $v !== '');

    $tabs = [
        'unanswered_no_order' => [
            'label' => 'Unanswered · No Order Yet',
            'count' => $stats['unanswered_no_order'],
            'badge' => 'bg-red-100 text-bad',
        ],
        'human_no_order' => [
            'label' => 'Manually Replied · No Order Yet',
            'count' => $stats['human_no_order'],
            'badge' => 'bg-amber-100 text-amber-800',
        ],
        'human_all' => [
            'label' => 'All Manually Assisted',
            'count' => $stats['human_today_all'],
            'badge' => 'bg-primary-soft text-primary',
        ],
        'unanswered_has_order' => [
            'label' => 'Unanswered · Ordered Today',
            'count' => $stats['unanswered_has_order'],
            'badge' => 'bg-emerald-100 text-good',
        ],
        'bot_no_order' => [
            'label' => 'Bot / AI Replied · No Order Yet',
            'count' => $stats['bot_no_order'],
            'badge' => 'bg-slate-100 text-muted',
        ],
    ];
@endphp

@section('content')
    {{-- Header + Live Refresh Action --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-ink">Live Inbox · Unanswered & Manual Replies</h1>
                @if ($stats['last_inbox_sync'])
                    <span class="pill bg-slate-100 text-muted num">
                        Inbox synced {{ \Illuminate\Support\Carbon::parse($stats['last_inbox_sync'])->timezone($tz)->diffForHumans() }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-muted mt-0.5">
                See exact <b>Customer Names</b> &amp; <b>Facebook Pages</b> waiting for a reply (haven’t ordered yet) or assisted manually by your team — click <b>Reply in Pancake</b> to respond immediately.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('inbox.sync') }}">
                @csrf
                <button type="submit" data-loading-text="Pulling Live Chats…" class="btn-primary">
                    <x-icon name="refresh" class="size-3.5"/>
                    <span>Refresh Live Inbox</span>
                </button>
            </form>
        </div>
    </div>

    {{-- 4 Summary KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <a href="{{ route('inbox', array_merge($baseQ, ['tab' => 'unanswered_no_order'])) }}" class="card p-5 hover:border-primary/40 transition-colors cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Unanswered (No Order Yet)</span>
                <span class="size-8 rounded-xl bg-red-50 text-bad grid place-items-center">
                    <x-icon name="alert" class="size-4"/>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="num text-2xl font-extrabold text-bad">{{ kpi_num($stats['unanswered_no_order']) }}</span>
                <span class="text-xs text-muted">waiting for reply</span>
            </div>
            <div class="mt-1.5 text-[11px] text-muted flex items-center gap-2 num">
                @if ($stats['unanswered_with_phone'] > 0)
                    <span class="text-amber-700 font-semibold">{{ kpi_num($stats['unanswered_with_phone']) }} left phone #</span>
                    <span>·</span>
                @endif
                <span>{{ kpi_num($stats['unread_unseen']) }} unread in Pancake</span>
            </div>
        </a>

        <a href="{{ route('inbox', array_merge($baseQ, ['tab' => 'human_all'])) }}" class="card p-5 hover:border-primary/40 transition-colors cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Manually Assisted Today</span>
                <span class="size-8 rounded-xl bg-primary-soft text-primary grid place-items-center">
                    <x-icon name="user" class="size-4"/>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="num text-2xl font-extrabold text-ink">{{ kpi_num($stats['human_today_all']) }}</span>
                <span class="text-xs text-muted">human replies since 12 AM</span>
            </div>
            <div class="mt-1.5 text-[11px] text-muted num">
                <span class="text-good font-semibold">{{ kpi_num($stats['human_converted_today']) }} ordered today</span>
                · {{ kpi_num($stats['human_no_order']) }} still open
            </div>
        </a>

        <div class="card p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Pages Needing Reply</span>
                <span class="size-8 rounded-xl bg-amber-50 text-amber-700 grid place-items-center">
                    <x-icon name="layers" class="size-4"/>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="num text-2xl font-extrabold text-ink">{{ kpi_num($stats['pages_with_unanswered']) }}</span>
                <span class="text-xs text-muted">of {{ $pages->count() }} scoped page(s)</span>
            </div>
            <div class="mt-1.5 text-[11px] text-muted truncate">
                @if ($unansweredByPage->isNotEmpty())
                    Most waiting: <b class="text-ink">{{ $unansweredByPage->first()['page_name'] }}</b> ({{ $unansweredByPage->first()['count'] }})
                @else
                    All scoped pages caught up!
                @endif
            </div>
        </div>

        <a href="{{ route('eod') }}" class="card p-5 hover:border-primary/40 transition-colors cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">EOD Inbox Counters</span>
                <span class="size-8 rounded-xl bg-emerald-50 text-good grid place-items-center">
                    <x-icon name="file-text" class="size-4"/>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="num text-2xl font-extrabold text-primary">{{ kpi_num($stats['human_today_all']) }} / {{ kpi_num($stats['unanswered_no_order']) }}</span>
            </div>
            <div class="mt-1.5 text-[11px] text-primary font-semibold flex items-center gap-1">
                <span>Linked to EOD Assisted &amp; Pending</span>
                <x-icon name="arrow-up-right" class="size-3.5"/>
            </div>
        </a>
    </div>

    {{-- Quick Page Filter Strip for Pages with Unanswered Chats --}}
    @if ($pageId === 'all' && $unansweredByPage->isNotEmpty())
        <div class="card px-4 py-3 flex items-center gap-2 overflow-x-auto">
            <span class="text-[11px] font-bold uppercase tracking-wider text-muted shrink-0">Pages Waiting:</span>
            @foreach ($unansweredByPage->take(10) as $ubp)
                <a href="{{ route('inbox', array_merge($baseQ, ['page' => $ubp['page_id'], 'tab' => 'unanswered_no_order'])) }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border border-line bg-slate-50 hover:bg-primary-soft hover:border-primary/30 text-xs shrink-0 cursor-pointer transition-colors">
                    <x-page-avatar :id="$ubp['page_id']" :name="$ubp['page_name']" size="size-4 !rounded !text-[8px]"/>
                    <span class="font-medium text-ink max-w-36 truncate">{{ $ubp['page_name'] }}</span>
                    <span class="pill !px-1.5 !py-0 bg-red-100 text-bad num">{{ $ubp['count'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Main Conversations Table --}}
    <section class="card overflow-hidden">
        <div class="px-5 py-3 bg-slate-50/70 border-b border-line flex flex-wrap items-center justify-between gap-3">
            {{-- Filter Tabs --}}
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach ($tabs as $key => $meta)
                    @php $activeTab = $tab === $key; @endphp
                    <a href="{{ route('inbox', array_merge($baseQ, ['tab' => $key])) }}"
                       @class([
                           'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border cursor-pointer transition-colors',
                           'bg-primary text-white border-primary' => $activeTab,
                           'bg-white text-muted border-line hover:text-ink' => ! $activeTab,
                       ])>
                        <span>{{ $meta['label'] }}</span>
                        <span @class(['px-1.5 py-0.2 rounded-full text-[10px] num font-bold', 'bg-white/20 text-white' => $activeTab, $meta['badge'] => ! $activeTab])>
                            {{ kpi_num($meta['count']) }}
                        </span>
                    </a>
                @endforeach
            </div>

            {{-- Search --}}
            <div class="relative">
                <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                <input type="search" data-table-search="inbox-table" placeholder="Search customer, page, message, tag…" aria-label="Search inbox conversations" class="search-field w-64">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="inbox-table" data-sortable class="tbl">
                <thead>
                    <tr>
                        <th data-sort="text" class="!text-left">Customer Name ↕</th>
                        <th data-sort="text" class="!text-left">Facebook Page &amp; Team ↕</th>
                        <th class="!text-left">Last Message Preview</th>
                        <th class="!text-left">Chat Status &amp; Tags</th>
                        <th data-sort="num" class="!text-left">Waiting / Updated ↕</th>
                        <th class="!text-right">Manual Reply</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conversations as $c)
                        @php
                            $pInfo = $pageMeta[$c->page_id] ?? ['name' => $c->page_name, 'product' => null, 'team' => null];
                            $tagsList = is_array($c->tags) ? $c->tags : [];
                            $tagNames = collect($tagsList)->pluck('text')->implode(' ');
                            $searchHaystack = strtolower(implode(' ', [
                                $c->customer_name,
                                $c->customer_id,
                                $c->phone,
                                $pInfo['name'],
                                $pInfo['product'],
                                $pInfo['team'],
                                $c->snippet,
                                $c->last_admin_name,
                                $c->assigned_to,
                                $tagNames,
                            ]));
                            $pancakeUrl = "https://pancake.vn/{$c->page_id}?c_id=" . urlencode($c->id);
                            $msgTs = $c->last_message_at?->timestamp ?? 0;
                        @endphp
                        <tr data-sort-row data-search-item data-search="{{ $searchHaystack }}">
                            {{-- Customer Name + Phone/Unread Indicator --}}
                            <td class="!text-left align-top" data-val="{{ $c->customer_name }}">
                                <div class="flex items-start gap-2">
                                    @if (! $c->seen && $c->last_sender_type === 'customer')
                                        <span title="Unread in Pancake" class="mt-1.5 size-2 rounded-full bg-bad shrink-0"></span>
                                    @endif
                                    <div>
                                        <div class="font-bold text-ink leading-snug">{{ $c->customer_name }}</div>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                                            @if ($c->has_phone)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200/80 text-[10px] font-semibold num">
                                                    <x-icon name="phone" class="size-2.5"/>
                                                    {{ $c->phone ?: 'Left Phone #' }}
                                                </span>
                                            @endif
                                            @if ($c->has_order_today)
                                                <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-good text-[10px] font-semibold">Ordered Today ✓</span>
                                            @else
                                                <span class="text-[10px] text-muted">No order today</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Facebook Page + Product / Owner --}}
                            <td class="!text-left align-top max-w-60" data-val="{{ $pInfo['name'] }}">
                                <div class="flex items-start gap-2.5">
                                    <x-page-avatar :id="$c->page_id" :name="$pInfo['name']" size="size-7 !rounded-lg !text-[9px] mt-0.5"/>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink text-xs truncate" title="{{ $pInfo['name'] }}">{{ $pInfo['name'] }}</div>
                                        <div class="text-[11px] text-muted truncate">
                                            @if (!empty($pInfo['team']))
                                                <span class="font-medium text-slate-600">{{ $pInfo['team'] }}</span>
                                            @else
                                                <span>Unassigned</span>
                                            @endif
                                            @if (!empty($pInfo['product']))
                                                <span>· {{ $pInfo['product'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Last Message Snippet --}}
                            <td class="!text-left align-top max-w-md">
                                <div class="text-xs text-slate-800 line-clamp-2 leading-relaxed" title="{{ $c->snippet }}">
                                    {{ $c->snippet ?: '—' }}
                                </div>
                                <div class="text-[10px] text-muted mt-0.5 num">
                                    {{ kpi_num($c->message_count) }} msgs
                                    @if ($c->assigned_to)
                                        · Assigned: <b class="text-slate-600">{{ $c->assigned_to }}</b>
                                    @endif
                                </div>
                            </td>

                            {{-- Sender Status + Pancake Tags --}}
                            <td class="!text-left align-top">
                                <div class="flex flex-wrap items-center gap-1">
                                    @if ($c->last_sender_type === 'customer')
                                        @if (! $c->seen)
                                            <span class="pill bg-red-50 text-bad border border-red-200/80">Unread · Customer Waiting</span>
                                        @else
                                            <span class="pill bg-amber-50 text-amber-800 border border-amber-200/80">Seen · Needs Reply</span>
                                        @endif
                                    @elseif ($c->last_sender_type === 'human_agent')
                                        <span class="pill bg-primary-soft text-primary border border-indigo-200/70">
                                            Replied: {{ $c->last_admin_name ?: 'Agent' }}
                                        </span>
                                    @elseif ($c->last_sender_type === 'botcake_ai')
                                        <span class="pill bg-violet-50 text-violet-700 border border-violet-200/70">Botcake AI Replied</span>
                                    @else
                                        <span class="pill bg-slate-100 text-muted">Bot / Sequence</span>
                                    @endif
                                </div>

                                @if (!empty($tagsList))
                                    <div class="flex flex-wrap items-center gap-1 mt-1">
                                        @foreach (array_slice($tagsList, 0, 4) as $tg)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold text-white"
                                                  style="background-color: {{ $tg['color'] ?? '#4B55F5' }}">
                                                {{ $tg['text'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            {{-- Waiting / Updated Time --}}
                            <td class="!text-left align-top whitespace-nowrap text-xs" data-val="{{ $msgTs }}">
                                @if ($c->last_message_at)
                                    <div class="font-semibold text-ink num">{{ $c->last_message_at->timezone($tz)->diffForHumans() }}</div>
                                    <div class="text-[11px] text-muted num">{{ kpi_time($c->last_message_at, 'M j, g:i A') }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- Direct Open in Pancake Button --}}
                            <td class="!text-right align-top whitespace-nowrap">
                                <a href="{{ $pancakeUrl }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white hover:bg-primary-hover text-xs font-semibold shadow-2xs cursor-pointer transition-colors">
                                    <span>Reply in Pancake</span>
                                    <x-icon name="external" class="size-3.5"/>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="!text-center py-12">
                                <div class="max-w-md mx-auto space-y-2">
                                    <div class="text-sm font-semibold text-ink">No conversations match this tab right now</div>
                                    <p class="text-xs text-muted">
                                        Click <b>Refresh Live Inbox</b> in the top-right to pull the latest chats from Pancake across your pages.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table-pager target="inbox-table" :perPage="15"/>
    </section>
@endsection
