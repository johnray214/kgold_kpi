@extends('layouts.app', ['title' => 'POS Orders · KGOLD AUTOMATION TEAM KPI'])

@php
    $labels = \App\Models\PosOrder::SOURCES;
    $pillCls = [
        'botcake_ai' => 'bg-primary-soft text-primary border border-indigo-200/70',
        'broadcast' => 'bg-sky-50 text-src-broadcast border border-sky-200/70',
        'sequence' => 'bg-violet-50 text-src-sequence border border-violet-200/70',
        'agent' => 'bg-amber-50 text-src-agent border border-amber-200/70',
        'organic' => 'bg-slate-100 text-muted border border-slate-200',
    ];
    $validOrders = $orders->where('is_valid', true);
    $baseQ = array_filter(['product' => $product ?? null, 'team' => $team ?? null, 'page' => $pageId, 'from' => $from, 'to' => $to], fn ($v) => $v !== null && $v !== '');
    $activeSrc = $filters['source_type'] ?? null;
    $onlyInvalid = isset($filters['valid']) && (string) $filters['valid'] === '0';
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('dashboard', $baseQ) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline cursor-pointer">
                <x-icon name="arrow-left" class="size-3.5"/> Back to overview
            </a>
            <h1 class="text-xl font-bold tracking-tight mt-1">
                POS Orders Drill-Down
                @if ($activeSrc)
                    <span class="text-muted font-medium">· {{ !empty($filters['assisted']) ? 'Assisted by ' : '' }}{{ $labels[$activeSrc] ?? $activeSrc }}</span>
                @endif
                @if ($onlyInvalid)<span class="text-bad font-medium">· Canceled / Excluded</span>@endif
            </h1>
        </div>

        <div class="flex items-center gap-3">
            <div class="card px-4 py-2.5">
                <div class="text-[11px] font-semibold text-muted uppercase tracking-wider">Orders Shown</div>
                <div class="num text-lg font-bold">{{ kpi_num($orders->count()) }}</div>
            </div>
            <div class="card px-4 py-2.5">
                <div class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Revenue</div>
                <div class="num text-lg font-bold text-good">{{ kpi_peso($validOrders->sum('total')) }}</div>
            </div>
        </div>
    </div>

    <section class="card overflow-hidden">
        {{-- Source quick-filter bar + instant search --}}
        <div class="px-5 py-3 bg-slate-50/70 border-b border-line flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="{{ route('orders', $baseQ + ['valid' => 1]) }}"
                   @class(['px-3 py-1 rounded-full text-xs font-semibold border cursor-pointer transition-colors', 'bg-primary text-white border-primary' => !$activeSrc && !$onlyInvalid, 'bg-white text-muted border-line hover:text-ink' => $activeSrc || $onlyInvalid])>
                    All Orders
                </a>
                @foreach ($labels as $key => $label)
                    <a href="{{ route('orders', $baseQ + ['source_type' => $key, 'valid' => 1]) }}"
                       @class(['px-3 py-1 rounded-full text-xs font-semibold border cursor-pointer transition-colors', 'bg-primary text-white border-primary' => $activeSrc === $key && empty($filters['assisted']), 'bg-white text-muted border-line hover:text-ink' => $activeSrc !== $key || !empty($filters['assisted'])])>
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <div class="relative">
                    <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                    <input type="search" data-table-search="orders-table" placeholder="Search name, phone, order #…" aria-label="Search orders" class="search-field w-56">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="orders-table" data-sortable class="tbl">
                <thead>
                    <tr>
                        <th data-sort="num">Order ↕</th>
                        @if ($pageId === 'all')
                            <th data-sort="text" class="!text-left">Page ↕</th>
                        @endif
                        <th data-sort="num" class="!text-left">Time ↕</th>
                        <th data-sort="text" class="!text-left">Customer ↕</th>
                        <th class="!text-left">Items</th>
                        <th data-sort="num">Amount ↕</th>
                        <th class="!text-left">Status</th>
                        <th data-sort="text" class="!text-left">Credited Source ↕</th>
                        <th class="!text-left">Assisted By</th>
                        <th class="!text-left">Closed By</th>
                        <th class="!text-left">Chat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $o)
                        <tr data-sort-row data-search-item
                            data-search="{{ strtolower($o->id.' '.$o->customer_name.' '.$o->phone.' '.$o->items_summary.' '.$o->source_name.' '.$o->closed_by) }}"
                            @class(['opacity-60' => ! $o->is_valid])>
                            <td class="font-mono text-xs text-muted" data-val="{{ $o->id }}">#{{ $o->id }}</td>
                            @if ($pageId === 'all')
                                <td class="!text-left text-xs font-medium max-w-52" data-val="{{ $o->page_name }}" title="{{ $o->page_name }}">
                                    <div class="flex items-center gap-2">
                                        <x-page-avatar :id="$o->page_id" :name="$o->page_name" size="size-6 !rounded-md !text-[9px]"/>
                                        <span class="truncate">{{ $o->page_name ?? $o->page_id }}</span>
                                    </div>
                                </td>
                            @endif
                            <td class="!text-left whitespace-nowrap text-xs" data-val="{{ $o->ordered_at?->timestamp ?? 0 }}">{{ kpi_time($o->ordered_at) }}</td>
                            <td class="!text-left" data-val="{{ $o->customer_name }}">
                                <div class="font-semibold text-ink">{{ $o->customer_name ?: '—' }}</div>
                                <div class="font-mono text-[11px] text-muted">{{ $o->phone ?: 'No phone' }}</div>
                            </td>
                            <td class="!text-left text-xs text-muted max-w-64 truncate" title="{{ $o->items_summary }}">{{ $o->items_summary }}</td>
                            <td class="font-bold text-good" data-val="{{ $o->total }}">{{ kpi_peso($o->total) }}</td>
                            <td class="!text-left">
                                <span @class([
                                    'pill',
                                    'bg-amber-50 text-amber-800 border border-amber-200/70' => (int) $o->status === 6,
                                    'bg-red-50 text-bad line-through' => ! $o->is_valid,
                                    'bg-slate-100 text-ink' => $o->is_valid && (int) $o->status !== 6,
                                ])>{{ $o->status_name }}</span>
                            </td>
                            <td class="!text-left" data-val="{{ $labels[$o->source_type] ?? 'Pending' }}">
                                <span class="pill {{ $pillCls[$o->source_type] ?? 'bg-slate-100 text-muted' }}">{{ $labels[$o->source_type] ?? 'Pending' }}</span>
                                @if ($o->source_id)
                                    <div class="text-[11px] text-muted mt-0.5 max-w-48 truncate" title="{{ $o->source_name }}">{{ $o->source_name }}</div>
                                @endif
                            </td>
                            <td class="!text-left text-xs text-muted max-w-44 truncate" title="{{ $o->assisted_name }}">{{ $o->assisted_name ?? '—' }}</td>
                            <td class="!text-left text-xs font-medium">{{ $o->closed_by ?? '—' }}</td>
                            <td class="!text-left">
                                @if ($o->conversation_id)
                                    <a target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline cursor-pointer"
                                       href="https://pancake.vn/{{ $o->page_id }}?c_id={{ $o->conversation_id }}">
                                        Open <x-icon name="external" class="size-3"/>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $pageId === 'all' ? 11 : 10 }}" class="!text-center py-10 text-muted">No orders match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table-pager target="orders-table" :perPage="15"/>
    </section>
@endsection
