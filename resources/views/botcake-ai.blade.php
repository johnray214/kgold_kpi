@extends('layouts.app', ['title' => 'AI Botcake · KGOLD AUTOMATION TEAM KPI'])

@php
    $q = fn (array $extra = []) => array_merge(array_filter(['product' => $product ?? null, 'team' => $team ?? null, 'page' => $pageId, 'from' => $from, 'to' => $to], fn ($v) => $v !== null && $v !== ''), $extra);
    $totalOrders = $sales['total_orders'];
    $aiShare = $totalOrders > 0 ? $ai['orders'] / $totalOrders : 0;
    $humanTotal = $ai['orders'] + $ai['handoff'];
    $aiVsHumanPct = $humanTotal > 0 ? $ai['orders'] / $humanTotal * 100 : 100;
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold tracking-tight text-ink">AI Botcake Performance</h1>
            <p class="text-xs text-muted">Verified POS orders closed directly by BotcakeAI vs handed off to human agents</p>
        </div>
    </div>

    {{-- Sypher-style split summary + chart --}}
    <div class="card overflow-hidden grid lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-line">
        <div class="lg:col-span-5 p-6 flex flex-col justify-between space-y-6">
            <div>
                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">AI Botcake Revenue</div>
                <div class="num text-3xl font-bold text-primary mt-1">{{ kpi_peso($ai['sales']) }}</div>
                <div class="text-xs text-muted mt-1 num">
                    {{ kpi_num($ai['orders']) }} valid POS orders ({{ kpi_pct($aiShare, 1) }} of all store orders) · AOV {{ $ai['orders'] ? kpi_peso($ai['sales'] / $ai['orders']) : '—' }}
                </div>
            </div>

            {{-- Proportional AI vs Human closer bar --}}
            <div>
                <div class="flex items-center justify-between text-xs mb-1.5">
                    <span class="font-semibold text-primary">Autonomous AI Closers ({{ kpi_num($ai['orders']) }})</span>
                    <span class="font-semibold text-src-agent">Human Handoff ({{ kpi_num($ai['handoff']) }})</span>
                </div>
                <div class="flex h-2.5 rounded-full overflow-hidden bg-slate-100 gap-0.5">
                    <div class="bg-primary rounded-full" style="width: {{ $aiVsHumanPct }}%"></div>
                    <div class="bg-src-agent rounded-full" style="width: {{ max(0, 100 - $aiVsHumanPct) }}%"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-line">
                <div class="rounded-xl bg-primary-soft/60 p-3.5">
                    <div class="text-[11px] font-medium text-muted">Started by Broadcast/Seq</div>
                    <div class="num text-xl font-bold text-primary mt-0.5">{{ kpi_num($ai['assisted_by_automation']) }}</div>
                    <div class="text-[10px] text-muted">woken up by automation, closed by AI</div>
                </div>
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <div class="text-[11px] font-medium text-muted">Direct / Ad Chat AI</div>
                    <div class="num text-xl font-bold text-ink mt-0.5">{{ kpi_num(max(0, $ai['orders'] - $ai['assisted_by_automation'])) }}</div>
                    <div class="text-[10px] text-muted">closed from organic or ad click</div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-7 p-6 flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-[15px] font-semibold text-ink">AI & Revenue Timeline</h2>
                    <p class="text-[11px] text-muted">{{ $timeline['subtitle'] }}</p>
                </div>
                <div class="flex items-center gap-1 rounded-xl border border-line p-0.5 bg-slate-50/70">
                    <button type="button" data-chart-toggle="bar" aria-label="Bar chart" class="p-1.5 rounded-lg cursor-pointer bg-primary-soft text-primary">
                        <x-icon name="chart" class="size-3.5"/>
                    </button>
                    <button type="button" data-chart-toggle="line" aria-label="Line chart" class="p-1.5 rounded-lg cursor-pointer text-muted hover:text-ink">
                        <x-icon name="line-chart" class="size-3.5"/>
                    </button>
                </div>
            </div>
            <div class="h-[230px] flex-1">
                <canvas id="trend-chart" data-trend='@json($timeline)' data-chart-mode="bar" aria-label="AI orders timeline" role="img"></canvas>
            </div>
        </div>
    </div>

    {{-- Recent AI-Closed POS Orders --}}
    <section class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title">Recent Orders Closed by Botcake AI</h2>
                <p class="card-sub">Every order below exists in POS and had `BotcakeAI` as the last sender before checkout</p>
            </div>
            <div class="relative">
                <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                <input type="search" data-table-search="ai-orders-tbl" placeholder="Search customer, phone, page…" aria-label="Search AI orders" class="search-field w-56">
            </div>
        </div>
        <div class="overflow-x-auto max-h-[60vh]">
            <table id="ai-orders-tbl" data-sortable class="tbl">
                <thead>
                    <tr>
                        <th data-sort="num">Order ↕</th>
                        @if ($pageId === 'all')
                            <th data-sort="text" class="!text-left">Page ↕</th>
                        @endif
                        <th data-sort="num" class="!text-left">Time ↕</th>
                        <th data-sort="text" class="!text-left">Customer ↕</th>
                        <th class="!text-left">Items</th>
                        <th class="!text-left">Assisted By</th>
                        <th data-sort="num">Amount ↕</th>
                        <th class="!text-left">Chat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($aiOrders as $o)
                        <tr data-sort-row data-search-item data-search="{{ strtolower($o->id.' '.$o->customer_name.' '.$o->phone.' '.$o->page_name.' '.$o->assisted_name) }}">
                            <td class="font-mono text-xs text-muted" data-val="{{ $o->id }}">#{{ $o->id }}</td>
                            @if ($pageId === 'all')
                                <td class="!text-left text-xs font-medium max-w-48" data-val="{{ $o->page_name }}">
                                    <div class="flex items-center gap-2">
                                        <x-page-avatar :id="$o->page_id" :name="$o->page_name" size="size-5 !rounded-md !text-[8px]"/>
                                        <span class="truncate">{{ $o->page_name }}</span>
                                    </div>
                                </td>
                            @endif
                            <td class="!text-left whitespace-nowrap text-xs" data-val="{{ $o->ordered_at?->timestamp ?? 0 }}">{{ kpi_time($o->ordered_at) }}</td>
                            <td class="!text-left" data-val="{{ $o->customer_name }}">
                                <div class="font-semibold text-ink">{{ $o->customer_name ?: '—' }}</div>
                                <div class="font-mono text-[11px] text-muted">{{ $o->phone }}</div>
                            </td>
                            <td class="!text-left text-xs text-muted max-w-60 truncate" title="{{ $o->items_summary }}">{{ $o->items_summary }}</td>
                            <td class="!text-left text-xs text-muted max-w-48 truncate" title="{{ $o->assisted_name }}">{{ $o->assisted_name ?? 'Direct chat' }}</td>
                            <td class="font-bold text-good" data-val="{{ $o->total }}">{{ kpi_peso($o->total) }}</td>
                            <td class="!text-left">
                                @if ($o->conversation_id)
                                    <a target="_blank" rel="noopener" class="link-num text-xs" href="https://pancake.vn/{{ $o->page_id }}?c_id={{ $o->conversation_id }}">
                                        Open <x-icon name="external" class="size-3"/>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $pageId === 'all' ? 8 : 7 }}" class="!text-center py-10 text-muted">No AI orders in this date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
