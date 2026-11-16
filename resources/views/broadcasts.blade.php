@extends('layouts.app', ['title' => 'Broadcasts · PulseKPI'])

@php
    $q = fn (array $extra = []) => array_merge(array_filter(['product' => $product ?? null, 'team' => $team ?? null, 'page' => $pageId, 'from' => $from, 'to' => $to]), $extra);
    $tT = $broadcasts->sum('targeted');
    $tD = $broadcasts->sum('delivered');
    $tF = $broadcasts->sum('failed');
    $tBlocked = $broadcasts->sum('blocked_count');
    $tLimit = $broadcasts->sum('api_limit_count');
    $tP = $broadcasts->sum('phone_count');
    $tO = $broadcasts->sum('orders');
    $tA = $broadcasts->sum('assisted');
    $tTotalO = $tO + $tA;
    $tS = $broadcasts->sum('sales');
    $tAssistS = $broadcasts->sum('assisted_sales');
    $tTotalS = $tS + $tAssistS;
    $delivRate = $tT > 0 ? $tD / $tT : 0;
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold tracking-tight text-ink">Utility Broadcasts</h1>
            <p class="text-xs text-muted">Filter by date, product, team & page above · click any broadcast row to inspect full Botcake delivery stats & exact Meta failure reasons</p>
        </div>
    </div>

    {{-- Clean 4-column summary strip --}}
    <section class="card overflow-hidden grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-line">
        <div class="p-5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Delivered / Targeted</div>
            <div class="num text-2xl font-bold text-ink mt-1">{{ kpi_pct($tT ? $delivRate : null, 1) }}</div>
            <div class="mt-2.5 h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-primary" style="width: {{ min(100, $delivRate * 100) }}%"></div>
            </div>
            <div class="text-[11px] text-muted mt-1.5 num">{{ kpi_num($tD) }} / {{ kpi_num($tT) }} across {{ $broadcasts->count() }} sends</div>
        </div>

        <div class="p-5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Not Delivered Breakdown</div>
            <div class="num text-2xl font-bold text-bad mt-1">{{ kpi_num($tF) }}</div>
            <div class="text-[11px] text-muted mt-2 num">
                API limit: <b>{{ kpi_num($tLimit) }}</b> · Blocked: <b>{{ kpi_num($tBlocked) }}</b>
            </div>
        </div>

        <div class="p-5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Phone Leads Left</div>
            <div class="num text-2xl font-bold text-primary mt-1">{{ kpi_num($tP) }}</div>
            <div class="text-[11px] text-muted mt-2 num">Lead rate {{ kpi_pct($tD ? $tP / $tD : null, 2) }}</div>
        </div>

        <div class="p-5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Broadcast-Driven POS Sales (24h)</div>
            <div class="num text-2xl font-bold text-good mt-1">{{ kpi_peso($tTotalS) }}</div>
            <div class="text-[11px] text-muted mt-2 num">
                <b>{{ kpi_num($tTotalO) }}</b> orders ({{ kpi_num($tO) }} direct · {{ kpi_num($tA) }} AI-closed)
            </div>
        </div>
    </section>

    {{-- Clean Broadcasts Table (click any row to open Botcake-style Detail Drawer) --}}
    <section class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title">
                    Broadcast Sends ({{ $broadcasts->count() }})
                    @if ($scheduledCount)
                        <span class="pill bg-amber-50 text-amber-700">{{ $scheduledCount }} scheduled</span>
                    @endif
                </h2>
                <p class="card-sub">Click any broadcast to open its full delivery & Meta failure breakdown</p>
            </div>

            <div class="relative">
                <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                <input id="bc-search" type="search" placeholder="Search broadcast or page…" aria-label="Search broadcasts" class="search-field w-56">
            </div>
        </div>

        <div class="overflow-x-auto max-h-[68vh]">
            <table id="bc-table" data-sortable data-active-segment="all" class="tbl">
                <thead>
                    <tr>
                        <th data-sort="text">Broadcast ↕</th>
                        <th data-sort="num">Sent Time ↕</th>
                        <th data-sort="num">Targeted ↕</th>
                        <th data-sort="num" class="min-w-36">Delivered ↕</th>
                        <th data-sort="num">Seen ↕</th>
                        <th data-sort="num">Blocked ↕</th>
                        <th data-sort="num" class="!text-left">Failed & Top Reason ↕</th>
                        <th data-sort="num">Phone ↕</th>
                        <th data-sort="num">Orders ↕</th>
                        <th data-sort="num">Sales ↕</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($broadcasts as $b)
                        @php
                            $drawerPayload = [
                                'id' => $b->id,
                                'name' => $b->name,
                                'page_name' => $b->page_name,
                                'page_id' => $b->page_id,
                                'avatar' => kpi_page_avatar($b->page_id),
                                'sent_at' => kpi_time($b->sent_at, 'M j, Y · g:i A'),
                                'method' => str_replace('_', ' ', (string) $b->send_method),
                                'raw_method' => (string) $b->send_method,
                                'status' => $b->status,
                                'targeted' => $b->targeted,
                                'delivered' => $b->delivered,
                                'success_pct' => kpi_pct($b->success_rate, 1),
                                'seen' => $b->seen,
                                'clicked' => $b->clicked,
                                'failed' => $b->failed,
                                'blocked' => $b->blocked_count,
                                'api_limit' => $b->api_limit_count,
                                'failed_reason' => $b->failed_reason ?? config('kpi.failed_reason_label'),
                                'errors' => $b->error_breakdown ?? [],
                                'phone' => $b->phone_count,
                                'lead_rate' => kpi_pct($b->lead_rate, 2),
                                'orders' => $b->orders,
                                'assisted' => $b->assisted,
                                'total_orders' => $b->total_orders,
                                'sales_fmt' => kpi_peso($b->total_sales),
                                'direct_sales_fmt' => kpi_peso($b->sales),
                                'assisted_sales_fmt' => kpi_peso($b->assisted_sales),
                                'cvr' => kpi_pct($b->cvr, 2),
                                'orders_url' => route('orders', $q(['source_type' => 'broadcast', 'source_id' => $b->id])),
                                'assisted_url' => route('orders', $q(['source_type' => 'broadcast', 'source_id' => $b->id, 'assisted' => 1])),
                            ];
                        @endphp
                        <tr data-bc-row data-sort-row data-segment="all"
                            data-bc-detail='@json($drawerPayload)'
                            data-search="{{ strtolower($b->name.' '.$b->page_name.' '.$b->failed_reason.' '.$b->send_method) }}"
                            class="cursor-pointer">
                            <td class="max-w-64" data-val="{{ $b->name }}">
                                <div class="font-semibold text-ink flex items-center gap-1.5">
                                    <span class="truncate" title="{{ $b->name }}">{{ $b->name }}</span>
                                    <x-icon name="chevron" class="size-3.5 text-slate-300 shrink-0"/>
                                </div>
                                <div class="text-[11px] text-muted flex flex-wrap items-center gap-1.5 mt-0.5">
                                    @if ($pageId === 'all')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-primary-soft text-primary font-medium whitespace-nowrap shrink-0">
                                            <x-page-avatar :id="$b->page_id" :name="$b->page_name" size="size-3.5 !rounded-full !text-[8px]"/>
                                            {{ $b->page_name }}
                                        </span>
                                    @endif
                                    <span>{{ str_replace('_', ' ', $b->send_method) }}</span>
                                    @if ($b->status !== 'FINISHED')
                                        <span class="text-amber-600 font-semibold">· {{ strtolower($b->status) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-muted text-xs" data-val="{{ $b->sent_at?->timestamp ?? 0 }}">{{ kpi_time($b->sent_at, 'M j, g:i A') }}</td>
                            <td data-val="{{ $b->targeted }}">{{ kpi_num($b->targeted) }}</td>
                            <td data-val="{{ $b->delivered }}">
                                <div class="flex items-center justify-end gap-2">
                                    <span class="font-medium">{{ kpi_num($b->delivered) }}</span>
                                    <span class="w-10 h-1.5 rounded-full bg-slate-100 overflow-hidden shrink-0">
                                        <span @class([
                                            'block h-full rounded-full',
                                            'bg-good' => ($b->success_rate ?? 0) >= 0.8,
                                            'bg-accent' => ($b->success_rate ?? 0) >= 0.5 && ($b->success_rate ?? 0) < 0.8,
                                            'bg-bad' => ($b->success_rate ?? 0) < 0.5,
                                        ]) style="width: {{ min(100, ($b->success_rate ?? 0) * 100) }}%"></span>
                                    </span>
                                    <span class="text-[11px] text-muted w-8">{{ kpi_pct($b->success_rate, 0) }}</span>
                                </div>
                            </td>
                            <td data-val="{{ $b->seen }}">{{ kpi_num($b->seen) }}</td>
                            <td data-val="{{ $b->blocked_count }}">
                                @if ($b->blocked_count > 0)
                                    <span class="text-amber-700 font-semibold">{{ kpi_num($b->blocked_count) }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="!text-left max-w-56" data-val="{{ $b->failed }}">
                                @if ($b->failed > 0)
                                    <div class="flex items-center gap-1.5">
                                        <span class="pill bg-red-50 text-bad num">{{ kpi_num($b->failed) }}</span>
                                        <span class="text-[11px] text-muted truncate" title="{{ $b->failed_reason }}">{{ $b->failed_reason }}</span>
                                    </div>
                                @else
                                    <span class="text-slate-400 text-xs">0 failed</span>
                                @endif
                            </td>
                            <td data-val="{{ $b->phone_count }}">
                                <span @class(['font-bold text-primary' => $b->phone_count > 0, 'text-slate-400' => ! $b->phone_count])>
                                    {{ kpi_num($b->phone_count) }}
                                </span>
                            </td>
                            <td data-val="{{ $b->total_orders }}">
                                @if ($b->orders > 0 && $b->assisted > 0)
                                    <div class="inline-flex items-center gap-1 justify-end">
                                        <a onclick="event.stopPropagation()" class="link-num font-bold" href="{{ route('orders', $q(['source_type' => 'broadcast', 'source_id' => $b->id])) }}" title="Direct broadcast orders">{{ kpi_num($b->orders) }}</a>
                                        <a onclick="event.stopPropagation()" class="pill !px-1.5 !py-0 bg-primary-soft text-primary num hover:underline" href="{{ route('orders', $q(['source_type' => 'broadcast', 'source_id' => $b->id, 'assisted' => 1])) }}" title="Closed by Botcake AI / Agent after this broadcast">+{{ kpi_num($b->assisted) }} AI</a>
                                    </div>
                                @elseif ($b->assisted > 0)
                                    <a onclick="event.stopPropagation()" class="pill bg-primary-soft text-primary num font-semibold hover:underline" href="{{ route('orders', $q(['source_type' => 'broadcast', 'source_id' => $b->id, 'assisted' => 1])) }}" title="Triggered by this broadcast and closed by Botcake AI / Agent">{{ kpi_num($b->assisted) }} AI</a>
                                @else
                                    <a onclick="event.stopPropagation()" class="link-num" href="{{ route('orders', $q(['source_type' => 'broadcast', 'source_id' => $b->id])) }}">{{ kpi_num($b->orders) }}</a>
                                @endif
                            </td>
                            <td class="text-good font-semibold" data-val="{{ $b->total_sales }}">
                                @if ($b->total_sales > 0)
                                    <span>{{ kpi_peso($b->total_sales) }}</span>
                                @else
                                    <span class="text-slate-400 font-normal">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="!text-center py-10 text-muted">No broadcasts sent in this date range.</td></tr>
                    @endforelse
                    <tr id="bc-empty-filter" class="hidden">
                        <td colspan="10" class="!text-center py-8 text-muted">No broadcasts match your search.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- Botcake-Style Slide-Over Broadcast Inspector Drawer --}}
    <div id="bc-drawer-backdrop" class="fixed inset-0 z-50 bg-slate-900/30 backdrop-blur-[1px] hidden"></div>
    <aside id="bc-drawer" aria-label="Broadcast details"
           class="fixed top-0 right-0 z-50 h-dvh w-full max-w-lg bg-white border-l border-line shadow-2xl translate-x-full transition-transform duration-200 flex flex-col">
        <div class="px-6 py-4 border-b border-line flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <img id="bcd-avatar" src="" alt="" width="36" height="36" class="size-9 rounded-xl object-cover ring-1 ring-line bg-primary-soft shrink-0">
                <div class="min-w-0">
                    <h2 id="bcd-name" class="font-bold text-[15px] text-ink truncate">Broadcast</h2>
                    <p id="bcd-meta" class="text-xs text-muted truncate"></p>
                </div>
            </div>
            <button id="bc-drawer-close" type="button" aria-label="Close details" class="p-1.5 rounded-lg text-muted hover:bg-slate-100 cursor-pointer">
                <x-icon name="x" class="size-4"/>
            </button>
        </div>

        <div class="p-6 space-y-5 flex-1 overflow-y-auto">
            {{-- Delivery Stats Grid (exact Botcake popup metrics) --}}
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted mb-2.5">Delivery Funnel</div>
                <div class="grid grid-cols-4 gap-2.5">
                    <div class="rounded-xl border border-line p-3">
                        <div class="text-[10px] text-muted uppercase">Targeted</div>
                        <div id="bcd-targeted" class="num text-lg font-bold text-ink mt-0.5">0</div>
                    </div>
                    <div class="rounded-xl border border-line p-3 bg-emerald-50/40">
                        <div class="text-[10px] text-good uppercase font-semibold">Delivered</div>
                        <div id="bcd-delivered" class="num text-lg font-bold text-good mt-0.5">0</div>
                        <div id="bcd-rate" class="num text-[10px] text-muted"></div>
                    </div>
                    <div class="rounded-xl border border-line p-3">
                        <div class="text-[10px] text-muted uppercase">Seen</div>
                        <div id="bcd-seen" class="num text-lg font-bold text-ink mt-0.5">0</div>
                    </div>
                    <div class="rounded-xl border border-line p-3">
                        <div class="text-[10px] text-muted uppercase">Clicked</div>
                        <div id="bcd-clicked" class="num text-lg font-bold text-ink mt-0.5">0</div>
                    </div>
                </div>
            </div>

            {{-- Leads & POS Sales Attribution --}}
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted mb-2.5">Leads & POS Sales (24h Window)</div>
                <div class="grid grid-cols-3 gap-2.5">
                    <div class="rounded-xl border border-primary/20 bg-primary-soft/50 p-3.5">
                        <div class="text-[10px] text-primary font-semibold uppercase">Phone Leads</div>
                        <div id="bcd-phone" class="num text-xl font-bold text-primary mt-0.5">0</div>
                        <div id="bcd-leadrate" class="num text-[10px] text-muted mt-0.5"></div>
                    </div>
                    <a id="bcd-orders-link" href="#" class="rounded-xl border border-line p-3.5 hover:border-primary cursor-pointer block">
                        <div class="text-[10px] text-muted uppercase font-semibold">POS Orders ↗</div>
                        <div id="bcd-orders" class="num text-xl font-bold text-ink mt-0.5">0</div>
                        <div id="bcd-cvr" class="num text-[10px] text-muted mt-0.5"></div>
                    </a>
                    <a id="bcd-sales-link" href="#" class="rounded-xl border border-line p-3.5 hover:border-primary cursor-pointer block">
                        <div class="text-[10px] text-muted uppercase font-semibold">POS Sales ↗</div>
                        <div id="bcd-sales" class="num text-xl font-bold text-good mt-0.5">₱0</div>
                        <div id="bcd-assisted" class="num text-[10px] text-muted mt-0.5"></div>
                    </a>
                </div>
            </div>

            {{-- Exact Meta Failure Reasons Breakdown --}}
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Why Sends Failed</span>
                    <span id="bcd-failed-badge" class="pill bg-red-50 text-bad num">0 failed</span>
                </div>
                <div id="bcd-errors-list" class="rounded-xl border border-line divide-y divide-line overflow-hidden text-xs"></div>
            </div>
        </div>
    </aside>
@endsection
