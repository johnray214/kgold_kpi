@extends('layouts.app', ['title' => 'Sequences · KGOLD AUTOMATION TEAM KPI'])

@php
    $q = fn (array $extra = []) => array_merge(array_filter(['product' => $product ?? null, 'team' => $team ?? null, 'page' => $pageId, 'from' => $from, 'to' => $to], fn ($v) => $v !== null && $v !== ''), $extra);
    $hasRangeDelta = $sequences->contains(fn ($s) => $s->phone_in_range !== null);
    $seqOrdersCount = $sequences->sum('orders');
    $seqSalesSum = $sequences->sum('sales');
    $seqAssistedSum = $sequences->sum('assisted');
    $seqPhoneRange = $sequences->sum(fn ($s) => $s->phone_in_range ?? 0);
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold tracking-tight text-ink">Sequences & Funnels</h1>
            <p class="text-xs text-muted">Module 2 · Step retention funnels, biggest drop-off points, and 48-hour POS sales attribution</p>
        </div>
        <a href="{{ route('orders', $q(['source_type' => 'sequence', 'valid' => 1])) }}" class="btn-ghost">
            View Sequence Orders <x-icon name="arrow-up-right" class="size-3.5"/>
        </a>
    </div>

    {{-- Sypher-style 4-column summary strip --}}
    <section class="card overflow-hidden grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-line">
        <div class="p-5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Active Sequences</div>
            <div class="num text-2xl font-bold text-ink mt-1">{{ kpi_num($sequences->count()) }}</div>
            <div class="text-[11px] text-muted mt-2 num">{{ kpi_num($sequences->sum('subscribers')) }} total subscribers</div>
        </div>

        <div class="p-5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Phone Leads Captured</div>
            <div class="num text-2xl font-bold text-src-sequence mt-1">
                {{ $hasRangeDelta ? kpi_num($seqPhoneRange) : kpi_num($sequences->sum('phone_total')) }}
            </div>
            <div class="text-[11px] text-muted mt-2">{{ $hasRangeDelta ? 'In selected date range' : 'All-time (daily delta active from next day)' }}</div>
        </div>

        <div class="p-5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Credited Sequence Orders</div>
            <div class="num text-2xl font-bold text-ink mt-1">{{ kpi_num($seqOrdersCount) }}</div>
            <div class="text-[11px] text-muted mt-2 num">+ {{ kpi_num($seqAssistedSum) }} AI/agent orders assisted by sequences</div>
        </div>

        <div class="p-5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Sequence POS Revenue</div>
            <div class="num text-2xl font-bold text-good mt-1">{{ kpi_peso($seqSalesSum) }}</div>
            <div class="text-[11px] text-muted mt-2 num">AOV {{ $seqOrdersCount ? kpi_peso($seqSalesSum / $seqOrdersCount) : '—' }}</div>
        </div>
    </section>

    {{-- Expandable Sequences Funnel Table --}}
    <section class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title">Sequence Retention & Drop-Off</h2>
                <p class="card-sub">Click any sequence row to expand its step-by-step retention bars</p>
            </div>
            <div class="relative">
                <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                <input type="search" data-table-search="seq-table" placeholder="Search sequence or page…" aria-label="Search sequences" class="search-field w-52">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="seq-table" class="tbl">
                <thead>
                    <tr>
                        <th>Sequence</th>
                        <th>Entered (Range)</th>
                        <th>Phone (Range)</th>
                        <th>Orders</th>
                        <th>Sales</th>
                        <th>Assisted</th>
                        <th>Entered (All)</th>
                        <th>Completed (All)</th>
                        <th>Phone (All)</th>
                        <th class="!text-left">Biggest Drop-off</th>
                    </tr>
                </thead>
                @forelse ($sequences as $s)
                    <tbody class="group/seq" data-search-item data-search="{{ strtolower($s->name.' '.$s->page_name) }}">
                        <tr class="cursor-pointer" onclick="this.parentElement.classList.toggle('open'); document.getElementById('seq-{{ $s->id }}').classList.toggle('hidden')">
                            <td class="max-w-72">
                                <div class="flex items-center gap-2.5">
                                    <x-icon name="chevron" class="size-3.5 text-muted shrink-0 transition-transform duration-200 group-[.open]/seq:rotate-90"/>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink truncate" title="{{ $s->name }}">{{ $s->name }}</div>
                                        <div class="text-[11px] text-muted flex flex-wrap items-center gap-1.5 mt-0.5">
                                            @if ($pageId === 'all')
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-primary-soft text-primary font-medium">
                                                    <x-page-avatar :id="$s->page_id" :name="$s->page_name" size="size-3.5 !rounded-full !text-[8px]"/>
                                                    {{ $s->page_name }}
                                                </span>
                                            @endif
                                            <span>{{ $s->published_steps }} steps · {{ kpi_pct($s->completion_rate, 0) }} completion</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ kpi_num($s->entered) }}</td>
                            <td class="font-semibold text-src-sequence">{{ kpi_num($s->phone_in_range) }}</td>
                            <td><a onclick="event.stopPropagation()" class="link-num" href="{{ route('orders', $q(['source_type' => 'sequence', 'source_id' => $s->id])) }}">{{ kpi_num($s->orders) }}</a></td>
                            <td class="text-good font-semibold">
                                @if (($s->sales + ($s->assisted_sales ?? 0)) > 0)
                                    <span title="Direct: {{ kpi_peso($s->sales) }} · AI-closed: {{ kpi_peso($s->assisted_sales ?? 0) }}">{{ kpi_peso($s->sales + ($s->assisted_sales ?? 0)) }}</span>
                                @else
                                    <span class="text-slate-400 font-normal">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($s->assisted > 0)
                                    <a onclick="event.stopPropagation()" class="pill bg-primary-soft text-primary num font-semibold hover:underline" href="{{ route('orders', $q(['source_type' => 'sequence', 'source_id' => $s->id, 'assisted' => 1])) }}">+{{ kpi_num($s->assisted) }} AI</a>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ kpi_num($s->entered_total) }}</td>
                            <td class="text-muted">{{ kpi_num($s->completed_total) }}</td>
                            <td class="text-muted">{{ kpi_num($s->phone_total) }}</td>
                            <td class="!text-left">
                                @if ($s->dropoff)
                                    <span class="pill bg-red-50 text-bad max-w-52 truncate" title="{{ $s->dropoff->name }}">
                                        {{ $s->dropoff->name }} · −{{ kpi_num($s->dropoff->drop) }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        <tr id="seq-{{ $s->id }}" class="hidden">
                            <td colspan="10" class="!p-0 bg-slate-50/70">
                                @php $max = max(1, $s->steps->max('sends')); @endphp
                                <div class="px-8 py-4 max-h-96 overflow-y-auto border-b border-line">
                                    <table class="w-full text-xs">
                                        <thead class="text-muted text-[11px]">
                                            <tr class="text-right">
                                                <th class="text-left py-1.5 w-8">#</th>
                                                <th class="text-left">Step</th>
                                                <th class="text-left pl-4 w-1/3">Retention</th>
                                                <th>Sends</th><th>Delivered</th><th>Seen</th><th>Phone</th><th>Drop vs Prev</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($s->steps as $step)
                                                @php $isDrop = $s->dropoff && $s->dropoff->id === $step->id; @endphp
                                                <tr @class(['text-right border-t border-line/80', 'bg-red-50/40' => $isDrop])>
                                                    <td class="text-left py-1.5 text-muted font-mono">{{ $step->position }}</td>
                                                    <td class="text-left font-medium">{{ $step->name }}</td>
                                                    <td class="pl-4">
                                                        <div class="h-2 rounded-full bg-slate-200/70 overflow-hidden">
                                                            <div @class(['h-2 rounded-full', 'bg-bad' => $isDrop, 'bg-primary' => ! $isDrop]) style="width: {{ $step->sends / $max * 100 }}%"></div>
                                                        </div>
                                                    </td>
                                                    <td>{{ kpi_num($step->sends) }}</td>
                                                    <td>{{ kpi_num($step->delivery) }}</td>
                                                    <td>{{ kpi_num($step->seen) }}</td>
                                                    <td class="font-semibold text-primary">{{ kpi_num($step->phone_count) }}</td>
                                                    <td @class(['font-semibold text-bad' => $isDrop, 'text-muted' => ! $isDrop])>{{ $step->drop ? '−'.kpi_num($step->drop) : '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="10" class="!text-center py-10 text-muted">No active sequences found.</td></tr></tbody>
                @endforelse
            </table>
        </div>
    </section>
@endsection
