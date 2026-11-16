@extends('layouts.app', ['title' => 'Dashboard · Botcake KPI'])

@php
    $q = fn (array $extra = []) => array_merge(array_filter(['product' => $product ?? null, 'team' => $team ?? null, 'page' => $pageId, 'from' => $from, 'to' => $to], fn ($v) => $v !== null && $v !== ''), $extra);
    $totalOrders = $sales['total_orders'];
    $totalSales = $sales['total_sales'];
    $bcTargeted = $broadcasts->sum('targeted');
    $bcDelivered = $broadcasts->sum('delivered');
    $bcPhone = $broadcasts->sum('phone_count');
    $deliveryRate = $bcTargeted > 0 ? $bcDelivered / $bcTargeted : 0;
    $aiOrders = $sales['sources']['botcake_ai']['orders'] ?? 0;
    $aiSales = $sales['sources']['botcake_ai']['sales'] ?? 0;
    $bcOrders = $sales['sources']['broadcast']['orders'] ?? 0;
    $bcSales = $sales['sources']['broadcast']['sales'] ?? 0;
    $seqOrders = $sales['sources']['sequence']['orders'] ?? 0;
    $seqSales = $sales['sources']['sequence']['sales'] ?? 0;
    $agentOrders = $sales['sources']['agent']['orders'] ?? 0;
    $orgOrders = ($sales['sources']['organic']['orders'] ?? 0) + ($sales['sources']['pending']['orders'] ?? 0);
    $agentOrgOrders = $agentOrders + $orgOrders;
    $agentOrgSales = ($sales['sources']['agent']['sales'] ?? 0) + ($sales['sources']['organic']['sales'] ?? 0) + ($sales['sources']['pending']['sales'] ?? 0);
    $otherSales = max(0, $totalSales - $aiSales - $bcSales);
    $otherOrders = max(0, $totalOrders - $aiOrders - $bcOrders);
    $aiShare = $totalOrders > 0 ? $aiOrders / $totalOrders : 0;

    $aiRevShare = $totalSales > 0 ? $aiSales / $totalSales * 100 : 34;
    $bcRevShare = $totalSales > 0 ? $bcSales / $totalSales * 100 : 33;
    $othRevShare = max(0, 100 - $aiRevShare - $bcRevShare);

    $srcMeta = [
        'botcake_ai' => ['label' => 'Botcake AI', 'dot' => 'bg-src-ai', 'bar' => 'bg-src-ai', 'pill' => 'bg-primary-soft text-primary'],
        'broadcast' => ['label' => 'Broadcast', 'dot' => 'bg-src-broadcast', 'bar' => 'bg-src-broadcast', 'pill' => 'bg-sky-50 text-src-broadcast'],
        'sequence' => ['label' => 'Sequence', 'dot' => 'bg-src-sequence', 'bar' => 'bg-src-sequence', 'pill' => 'bg-violet-50 text-src-sequence'],
        'agent' => ['label' => 'Agent / Telesales', 'dot' => 'bg-src-agent', 'bar' => 'bg-src-agent', 'pill' => 'bg-amber-50 text-src-agent'],
        'organic' => ['label' => 'Organic', 'dot' => 'bg-src-organic', 'bar' => 'bg-src-organic', 'pill' => 'bg-slate-100 text-muted'],
        'pending' => ['label' => 'POS Only', 'dot' => 'bg-slate-300', 'bar' => 'bg-slate-300', 'pill' => 'bg-slate-100 text-muted'],
    ];

    // Build inline SVG sparkline coordinates from timeline sales
    $sparkVals = $timeline['sales'] ?? [0];
    $sparkMax = max(1, max($sparkVals));
    $sparkCount = max(1, count($sparkVals) - 1);
    $sparkPoints = collect($sparkVals)->map(function ($v, $i) use ($sparkCount, $sparkMax) {
        $x = round(($i / $sparkCount) * 140, 1);
        $y = round(28 - ($v / $sparkMax) * 24, 1);
        return "{$x},{$y}";
    })->implode(' ');

    $shortPeso = fn (float $n) => $n >= 1000 ? '₱'.number_format($n / 1000, 1).'K' : '₱'.number_format($n, 0);
    $currentPage = $pages->firstWhere('fb_page_id', $pageId);
@endphp

@section('content')
    {{-- Sub-header context line (Sypher top status strip) --}}
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted px-0.5">
        <div class="flex items-center gap-2.5">
            @if ($pageId === 'all')
                <div class="flex -space-x-1.5">
                    @foreach ($pages->take(5) as $hp)
                        <x-page-avatar :id="$hp->fb_page_id" :name="$hp->name" size="size-6 !rounded-full !ring-2 !ring-surface !text-[9px]"/>
                    @endforeach
                </div>
                <span class="font-semibold text-ink">All {{ $pages->count() }} Brusko Capsule Pages</span>
            @elseif ($currentPage)
                <x-page-avatar :id="$currentPage->fb_page_id" :name="$currentPage->name" size="size-6 !rounded-md !text-[9px]"/>
                <span class="font-semibold text-ink">{{ $currentPage->name }}</span>
            @endif
            <span class="text-slate-300">·</span>
            <span>{{ \Illuminate\Support\Carbon::parse($from)->format('M j') }}@if ($from !== $to) – {{ \Illuminate\Support\Carbon::parse($to)->format('M j, Y') }}@else, {{ \Illuminate\Support\Carbon::parse($from)->format('Y') }}@endif</span>
            @if ($sales['canceled'])
                <span class="text-slate-300">·</span>
                <a href="{{ route('orders', $q(['valid' => 0])) }}" class="text-bad hover:underline cursor-pointer">{{ $sales['canceled'] }} canceled excluded</a>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if ($lastError)
                <span class="text-bad font-medium flex items-center gap-1" title="{{ $lastError->message }}">
                    <x-icon name="alert" class="size-3.5"/> {{ ucfirst($lastError->task) }} sync warning
                </span>
            @endif
            <span>POS Verified Total: <strong class="text-ink num">{{ kpi_peso($totalSales) }}</strong> ({{ kpi_num($totalOrders) }} orders)</span>
        </div>
    </div>

    {{-- Data coverage notice when viewing unsynced older dates --}}
    @php
        $since = $dataSince ? \Illuminate\Support\Carbon::parse($dataSince)->setTimezone(config('kpi.timezone'))->startOfDay() : null;
    @endphp
    @if ($since && \Illuminate\Support\Carbon::parse($from, config('kpi.timezone'))->lt($since))
        <div class="card !border-amber-200 !bg-amber-50/90 px-4 py-2.5 text-xs text-amber-950 flex items-center gap-2" role="alert">
            <x-icon name="alert" class="size-4 text-amber-600 shrink-0"/>
            <span>Active sync covers <b>Yesterday & Today</b> (from {{ $since->format('M j, Y') }}). Earlier dates are excluded for fast syncing.</span>
        </div>
    @endif

    {{-- EXECUTIVE TOTAL ORDERS STRIP (All + Botcake AI + Broadcast + Sequence + Agent/Organic) --}}
    <section class="card overflow-hidden grid grid-cols-2 lg:grid-cols-5 divide-y lg:divide-y-0 lg:divide-x divide-line">
        <a href="{{ route('orders', $q(['valid' => 1])) }}" class="p-4 sm:p-5 hover:bg-primary-soft/25 transition-colors cursor-pointer group block">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Total POS Orders</span>
                <span class="size-6 rounded-lg bg-emerald-50 text-good grid place-items-center"><x-icon name="cart" class="size-3.5"/></span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="num text-2xl font-bold text-ink group-hover:text-primary">{{ kpi_num($totalOrders) }}</span>
                <span class="num text-xs font-semibold text-good">{{ kpi_peso($totalSales) }}</span>
            </div>
            <div class="text-[11px] text-muted mt-1 num">100% verified · AOV {{ $totalOrders ? kpi_peso($totalSales / $totalOrders) : '—' }}</div>
        </a>

        <a href="{{ route('botcake.ai', $q()) }}" class="p-4 sm:p-5 hover:bg-primary-soft/25 transition-colors cursor-pointer group block">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Botcake AI Orders</span>
                <span class="size-6 rounded-lg bg-primary-soft text-primary grid place-items-center"><x-icon name="sparkles" class="size-3.5"/></span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="num text-2xl font-bold text-primary">{{ kpi_num($aiOrders) }}</span>
                <span class="num text-xs font-medium text-muted">{{ kpi_pct($totalOrders ? $aiOrders / $totalOrders : 0, 0) }}</span>
            </div>
            <div class="text-[11px] text-muted mt-1 num">{{ kpi_peso($aiSales) }} · closed by AI</div>
        </a>

        <a href="{{ route('broadcasts', $q()) }}" class="p-4 sm:p-5 hover:bg-primary-soft/25 transition-colors cursor-pointer group block">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Broadcast Orders</span>
                <span class="size-6 rounded-lg bg-sky-50 text-src-broadcast grid place-items-center"><x-icon name="megaphone" class="size-3.5"/></span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="num text-2xl font-bold text-ink group-hover:text-src-broadcast">{{ kpi_num($bcOrders) }}</span>
                <span class="num text-xs font-medium text-muted">{{ kpi_pct($totalOrders ? $bcOrders / $totalOrders : 0, 0) }}</span>
            </div>
            <div class="text-[11px] text-muted mt-1 num">{{ kpi_peso($bcSales) }} · {{ kpi_num($bcPhone) }} phone leads</div>
        </a>

        <a href="{{ route('sequences', $q()) }}" class="p-4 sm:p-5 hover:bg-primary-soft/25 transition-colors cursor-pointer group block">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Sequence Orders</span>
                <span class="size-6 rounded-lg bg-violet-50 text-src-sequence grid place-items-center"><x-icon name="workflow" class="size-3.5"/></span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="num text-2xl font-bold text-ink group-hover:text-src-sequence">{{ kpi_num($seqOrders) }}</span>
                <span class="num text-xs font-medium text-muted">{{ kpi_pct($totalOrders ? $seqOrders / $totalOrders : 0, 0) }}</span>
            </div>
            <div class="text-[11px] text-muted mt-1 num">{{ kpi_peso($seqSales) }} · {{ $sequencesCount ?? ($sequences ?? collect())->count() }} sequences</div>
        </a>

        <a href="{{ route('orders', $q(['source_type' => 'agent', 'valid' => 1])) }}" class="p-4 sm:p-5 hover:bg-primary-soft/25 transition-colors cursor-pointer group block col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Agent & Organic</span>
                <span class="size-6 rounded-lg bg-amber-50 text-src-agent grid place-items-center"><x-icon name="user" class="size-3.5"/></span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="num text-2xl font-bold text-ink group-hover:text-src-agent">{{ kpi_num($agentOrgOrders) }}</span>
                <span class="num text-xs font-medium text-muted">{{ kpi_pct($totalOrders ? $agentOrgOrders / $totalOrders : 0, 0) }}</span>
            </div>
            <div class="text-[11px] text-muted mt-1 num">{{ kpi_peso($agentOrgSales) }} · {{ $agentOrders }} agent, {{ $orgOrders }} organic</div>
        </a>
    </section>

    {{-- SYPHER SPLIT-GRID EXECUTIVE WORKSPACE --}}
    <div class="card overflow-hidden grid lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-line">

        {{-- ================= LEFT COLUMN (7/12): Metrics + Chart + Breakdown ================= --}}
        <div class="lg:col-span-7 divide-y divide-line flex flex-col">

            {{-- 1. Top 3-Metric Visual Strip (Sypher "Deliveries" style) --}}
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[15px] font-semibold tracking-tight text-ink">Automation Performance</h2>
                    <a href="{{ route('broadcasts', $q()) }}" class="text-xs font-medium text-primary hover:underline cursor-pointer inline-flex items-center gap-1">
                        Details <x-icon name="arrow-up-right" class="size-3.5"/>
                    </a>
                </div>

                <div class="grid sm:grid-cols-3 gap-6">
                    {{-- Metric A: Broadcast Delivery Rate + Barcode Tick Strip --}}
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Broadcast Delivery</div>
                        <div class="num text-2xl font-bold text-ink mt-1">{{ kpi_pct($bcTargeted ? $deliveryRate : null, 1) }}</div>
                        <div class="mt-3 relative pt-2">
                            @php $filledTicks = (int) round($deliveryRate * 22); @endphp
                            <span class="absolute top-0 text-[9px] text-ink leading-none" style="left: calc({{ min(96, round($deliveryRate * 100)) }}% - 4px)">▼</span>
                            <div class="flex items-center gap-[3px] h-5" title="{{ kpi_num($bcDelivered) }} of {{ kpi_num($bcTargeted) }} delivered">
                                @for ($t = 1; $t <= 22; $t++)
                                    <span @class([
                                        'flex-1 h-full rounded-[1.5px]',
                                        'bg-primary' => $t <= $filledTicks && $t !== 8,
                                        'bg-amber-400' => $t <= $filledTicks && $t === 8,
                                        'bg-slate-200' => $t > $filledTicks,
                                    ])></span>
                                @endfor
                            </div>
                        </div>
                        <div class="text-[11px] text-muted mt-1.5 num">{{ kpi_num($bcDelivered) }} / {{ kpi_num($bcTargeted) }} sent</div>
                    </div>

                    {{-- Metric B: AI Botcake Share + Gradient Spectrum Pointer --}}
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">AI Botcake Share</div>
                        <div class="num text-2xl font-bold text-ink mt-1">{{ kpi_pct($totalOrders ? $aiShare : null, 1) }}</div>
                        <div class="mt-3 relative pt-2.5">
                            <span class="absolute top-0 text-[9px] text-ink leading-none" style="left: calc({{ min(96, round($aiShare * 100)) }}% - 4px)">▼</span>
                            <div class="h-2.5 rounded-full bg-linear-to-r from-amber-400 via-sky-400 to-primary"></div>
                        </div>
                        <div class="text-[11px] text-muted mt-2 num">{{ kpi_num($aiOrders) }} AI orders · {{ $shortPeso($aiSales) }}</div>
                    </div>

                    {{-- Metric C: Total Valid Orders + Delta + Sparkline --}}
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Valid POS Orders</div>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="num text-2xl font-bold text-ink">{{ kpi_num($totalOrders) }}</span>
                            @if (($comparison['orders'] ?? null) !== null)
                                @php $up = $comparison['orders'] >= 0; @endphp
                                <span @class(['text-xs font-semibold num', 'text-good' => $up, 'text-bad' => ! $up])>
                                    {{ $up ? '↑' : '↓' }} {{ abs(round($comparison['orders'] * 100)) }}%
                                </span>
                            @endif
                        </div>
                        <div class="mt-2 h-7">
                            <svg viewBox="0 0 140 30" class="w-full h-7 overflow-visible" preserveAspectRatio="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="sparkFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#4B55F5" stop-opacity="0.22"/>
                                        <stop offset="100%" stop-color="#4B55F5" stop-opacity="0.0"/>
                                    </linearGradient>
                                </defs>
                                <polygon points="0,30 {{ $sparkPoints }} 140,30" fill="url(#sparkFill)"/>
                                <polyline points="{{ $sparkPoints }}" fill="none" stroke="#4B55F5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="text-[11px] text-muted mt-1 num">AOV {{ $totalOrders ? kpi_peso($totalSales / $totalOrders) : '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- 2. Middle Chart Block (Sypher "Revenue and costs" style) --}}
            <div class="p-6 flex-1 flex flex-col">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div>
                        <h2 class="text-[15px] font-semibold tracking-tight text-ink">Revenue & Source Velocity</h2>
                        <p class="text-[11px] text-muted">{{ $timeline['subtitle'] }} · hover any point to inspect</p>
                    </div>
                    <div class="flex items-center gap-1 rounded-xl border border-line p-0.5 bg-slate-50/70">
                        <button type="button" data-chart-toggle="line" aria-label="Line chart"
                                class="p-1.5 rounded-lg cursor-pointer transition-colors bg-primary-soft text-primary">
                            <x-icon name="line-chart" class="size-3.5"/>
                        </button>
                        <button type="button" data-chart-toggle="bar" aria-label="Stacked bar chart"
                                class="p-1.5 rounded-lg cursor-pointer transition-colors text-muted hover:text-ink">
                            <x-icon name="chart" class="size-3.5"/>
                        </button>
                    </div>
                </div>

                <div class="h-[250px] relative">
                    <canvas id="trend-chart" data-trend='@json($timeline)' data-chart-mode="line" aria-label="Revenue and orders chart" role="img"></canvas>
                </div>

                {{-- Interactive Sypher-style colored checkbox pills --}}
                <div class="flex flex-wrap items-center gap-4 pt-3 mt-2 border-t border-line/60 text-xs select-none">
                    @foreach ([
                        ['revenue', 'POS Revenue', 'bg-good'],
                        ['botcake_ai', 'Botcake AI', 'bg-src-ai'],
                        ['broadcast', 'Broadcast', 'bg-src-broadcast'],
                        ['sequence', 'Sequence', 'bg-src-sequence'],
                        ['agent', 'Agent', 'bg-src-agent'],
                    ] as [$sKey, $sLabel, $sBg])
                        <button type="button" data-series-toggle="{{ $sKey }}" aria-pressed="true"
                                class="inline-flex items-center gap-1.5 py-0.5 cursor-pointer text-ink font-medium transition-all duration-150 hover:opacity-80">
                            <span data-check-box class="size-4 rounded-[5px] {{ $sBg }} text-white grid place-items-center transition-colors duration-150">
                                <x-icon name="check" class="size-2.5 transition-opacity duration-150"/>
                            </span>
                            <span>{{ $sLabel }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- 3. Bottom Split Block (Sypher "Balance" + "Costs by category" style) --}}
            <div class="grid sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-line">
                {{-- Left: Phone Leads & Broadcast Pulse --}}
                <div class="p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-[14px] font-semibold text-ink">Phone Leads Captured</h3>
                            <span class="text-[11px] text-muted num">{{ $broadcasts->count() }} broadcasts</span>
                        </div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-muted mt-3">Broadcast Phone Leads</div>
                        <div class="flex items-baseline justify-between mt-0.5">
                            <span class="num text-2xl font-bold text-ink">{{ kpi_num($bcPhone) }}</span>
                            <span class="text-[11px] text-muted num">Lead rate {{ kpi_pct($bcDelivered ? $bcPhone / $bcDelivered : null, 2) }}</span>
                        </div>
                    </div>

                    {{-- Vertical mini-bars of recent broadcasts' delivery/leads --}}
                    @php
                        $barItems = $broadcasts->take(18)->reverse()->values();
                        $maxDeliv = max(1, $barItems->max('delivered'));
                    @endphp
                    <div class="mt-4 h-20 flex items-end gap-1.5 pt-2 border-b border-line/80">
                        @forelse ($barItems as $bi)
                            @php $h = max(12, round(($bi->delivered / $maxDeliv) * 100)); @endphp
                            <div class="flex-1 flex flex-col items-center justify-end h-full group relative"
                                 title="{{ $bi->name }}: {{ kpi_num($bi->delivered) }} delivered · {{ $bi->phone_count }} phone leads">
                                <span @class([
                                    'w-full rounded-t-[3px] transition-opacity group-hover:opacity-80',
                                    'bg-primary' => $bi->phone_count > 0,
                                    'bg-rose-400' => $bi->phone_count === 0 && ($bi->success_rate ?? 1) < 0.5,
                                    'bg-emerald-400' => $bi->phone_count === 0 && ($bi->success_rate ?? 1) >= 0.5,
                                ]) style="height: {{ $h }}%"></span>
                            </div>
                        @empty
                            <div class="text-xs text-muted py-6 w-full text-center">No broadcasts in selected range</div>
                        @endforelse
                    </div>
                </div>

                {{-- Right: Sales by Source (Sypher "Costs by category" dotted list!) --}}
                <div class="p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-[14px] font-semibold text-ink">Sales by Source</h3>
                        <span class="text-[11px] text-muted">1 order = 1 source</span>
                    </div>

                    <div class="flex h-2 rounded-full overflow-hidden bg-slate-100 gap-0.5 mb-4">
                        @foreach ($sales['sources'] as $key => $s)
                            @if ($s['orders'] && $totalOrders)
                                <div class="{{ $srcMeta[$key]['bar'] ?? 'bg-slate-300' }} rounded-full"
                                     style="width: {{ max(3, $s['orders'] / $totalOrders * 100) }}%"></div>
                            @endif
                        @endforeach
                    </div>

                    <ul class="space-y-2 text-xs">
                        @foreach ($sales['sources'] as $key => $s)
                            <li>
                                <a href="{{ route('orders', $q(['source_type' => $key, 'valid' => 1])) }}"
                                   class="flex items-center gap-2 hover:text-primary cursor-pointer group">
                                    <span class="size-2 rounded-full shrink-0 {{ $srcMeta[$key]['dot'] ?? 'bg-slate-300' }}"></span>
                                    <span class="text-muted group-hover:text-primary truncate">{{ $srcMeta[$key]['label'] ?? $s['label'] }}</span>
                                    <span class="flex-1 border-b border-dotted border-slate-200 translate-y-[-2px]"></span>
                                    <span class="num text-muted">{{ kpi_num($s['orders']) }} ord</span>
                                    <span class="num font-semibold text-ink w-16 text-right">{{ $shortPeso($s['sales']) }}</span>
                                    <span class="num text-muted w-8 text-right">{{ kpi_pct($totalOrders ? $s['orders'] / $totalOrders : 0, 0) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- ================= RIGHT COLUMN (5/12): Sypher "Invoices"-Style Explorer ================= --}}
        <div id="sypher-right-panel" class="lg:col-span-5 flex flex-col">
            <div class="p-6 pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[15px] font-semibold tracking-tight text-ink">Pages & Attributed Sales</h2>
                    <a href="{{ route('orders', $q(['valid' => 1])) }}" class="text-xs font-medium text-primary hover:underline cursor-pointer">
                        All orders →
                    </a>
                </div>

                {{-- 3-Column Proportional Header (Sypher PAID / RECENT REQUEST / UNPAID style) --}}
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">AI Botcake</div>
                        <div class="num text-lg font-bold text-ink mt-0.5">{{ kpi_num($aiOrders) }} <span class="text-xs font-medium text-muted">ord</span></div>
                        <div class="num text-[11px] text-muted mt-1.5">{{ $shortPeso($aiSales) }} · {{ number_format($aiRevShare, 0) }}%</div>
                        <div class="h-1.5 rounded-full bg-src-ai mt-1"></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Broadcasts</div>
                        <div class="num text-lg font-bold text-ink mt-0.5">{{ kpi_num($bcOrders) }} <span class="text-xs font-medium text-muted">ord</span></div>
                        <div class="num text-[11px] text-muted mt-1.5">{{ $shortPeso($bcSales) }} · {{ number_format($bcRevShare, 0) }}%</div>
                        <div class="h-1.5 rounded-full bg-src-broadcast mt-1"></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Seq & Other</div>
                        <div class="num text-lg font-bold text-ink mt-0.5">{{ kpi_num($otherOrders) }} <span class="text-xs font-medium text-muted">ord</span></div>
                        <div class="num text-[11px] text-muted mt-1.5">{{ $shortPeso($otherSales) }} · {{ number_format($othRevShare, 0) }}%</div>
                        <div class="h-1.5 rounded-full bg-src-sequence mt-1"></div>
                    </div>
                </div>

                {{-- Filter Tabs + Search Box --}}
                <div class="flex flex-wrap items-center justify-between gap-2 mt-5 pt-4 border-t border-line">
                    <div class="flex flex-wrap items-center gap-1 text-xs">
                        <button type="button" data-rp-tab="pages"
                                class="px-2.5 py-1 rounded-lg cursor-pointer transition-colors bg-slate-100 text-ink font-semibold">
                            Pages ({{ $pageBreakdown->count() }})
                        </button>
                        <button type="button" data-rp-tab="orders" data-rp-src=""
                                class="px-2.5 py-1 rounded-lg cursor-pointer transition-colors text-muted hover:text-ink">
                            Orders
                        </button>
                        <button type="button" data-rp-tab="orders" data-rp-src="botcake_ai"
                                class="px-2.5 py-1 rounded-lg cursor-pointer transition-colors text-muted hover:text-ink">
                            AI
                        </button>
                        <button type="button" data-rp-tab="orders" data-rp-src="broadcast"
                                class="px-2.5 py-1 rounded-lg cursor-pointer transition-colors text-muted hover:text-ink">
                            Broadcast
                        </button>
                    </div>

                    <div class="relative">
                        <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                        <input id="right-panel-search" type="search" placeholder="Search…" aria-label="Search pages or orders"
                               class="search-field w-36 sm:w-40">
                    </div>
                </div>
            </div>

            {{-- Tab Pane 1: Overall Pages List (Ranked by Highest Orders + Paginated) --}}
            <div data-rp-pane="pages" class="flex-1 divide-y divide-line/70 border-t border-line">
                <div class="px-6 py-2 bg-slate-50/60 grid grid-cols-12 text-[11px] font-medium text-muted">
                    <span class="col-span-6">Page (Ranked by Orders)</span>
                    <span class="col-span-3 text-right">Highest Orders</span>
                    <span class="col-span-3 text-right">Revenue</span>
                </div>
                @foreach ($pageBreakdown as $idx => $pb)
                    @php
                        $fullOk = $pb['has_botcake'] && $pb['has_pancake'];
                        $rank = $idx + 1;
                    @endphp
                    <a href="{{ route('dashboard', $q(['page' => $pb['fb_page_id']])) }}"
                       data-rp-row data-search="{{ strtolower($pb['name'].' '.$pb['fb_page_id'].' '.($pb['product'] ?? '').' '.($pb['team'] ?? '')) }}"
                       class="px-6 py-3 grid grid-cols-12 items-center gap-2 hover:bg-primary-soft/35 transition-colors cursor-pointer group">
                        <div class="col-span-6 flex items-center gap-2.5 min-w-0">
                            <span @class([
                                'w-5 text-center num text-[11px] font-bold shrink-0 rounded-md py-0.5',
                                'bg-primary text-white' => $rank === 1 && $pb['orders'] > 0,
                                'bg-primary-soft text-primary' => ($rank === 2 || $rank === 3) && $pb['orders'] > 0,
                                'text-slate-400 font-medium' => $rank > 3 || $pb['orders'] === 0,
                            ])>{{ $rank }}</span>
                            <x-page-avatar :id="$pb['fb_page_id']" :name="$pb['name']" size="size-8 !rounded-lg"/>
                            <div class="min-w-0">
                                <div class="text-[13px] font-semibold text-ink group-hover:text-primary truncate">{{ $pb['name'] }}</div>
                                <div class="text-[10px] text-muted flex items-center gap-1 truncate">
                                    @if ($fullOk)
                                        <span class="size-1.5 rounded-full bg-good shrink-0"></span><span>Full sync</span>
                                    @else
                                        <span class="size-1.5 rounded-full bg-amber-400 shrink-0"></span><span>Needs Pancake</span>
                                    @endif
                                    @if (!empty($pb['team']))
                                        <span>· {{ $pb['team'] }}</span>
                                    @else
                                        <span>· {{ $pb['bc_count'] }} bc</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-span-3 text-right num">
                            <div class="text-xs font-bold {{ $pb['orders'] > 0 ? 'text-primary' : 'text-ink' }}">{{ kpi_num($pb['orders']) }} ord</div>
                            <div class="text-[10px] text-muted font-medium">{{ kpi_num($pb['bc_phones']) }} leads</div>
                        </div>
                        <div class="col-span-3 text-right num">
                            <div class="text-[13px] font-bold text-ink">{{ kpi_peso($pb['sales']) }}</div>
                            <div class="text-[10px] text-muted">{{ $pb['ai_orders'] }} AI</div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Tab Pane 2: Recent Attributed POS Orders --}}
            <div data-rp-pane="orders" data-src-filter="" class="hidden flex-1 divide-y divide-line/70 border-t border-line">
                <div class="px-6 py-2 bg-slate-50/60 grid grid-cols-12 text-[11px] font-medium text-muted">
                    <span class="col-span-5">Customer / Page</span>
                    <span class="col-span-4">Source</span>
                    <span class="col-span-3 text-right">Value</span>
                </div>
                @forelse ($recentOrders as $ro)
                    <a href="{{ route('orders', $q(['source_type' => $ro->source_type, 'valid' => 1])) }}"
                       data-rp-row data-src="{{ $ro->source_type ?? 'pending' }}"
                       data-search="{{ strtolower($ro->customer_name.' '.$ro->phone.' '.$ro->page_name.' '.$ro->id) }}"
                       class="px-6 py-2.5 grid grid-cols-12 items-center gap-2 hover:bg-primary-soft/35 transition-colors cursor-pointer">
                        <div class="col-span-5 flex items-center gap-2.5 min-w-0">
                            <x-page-avatar :id="$ro->page_id" :name="$ro->page_name" size="size-7 !rounded-lg !text-[9px]"/>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-ink truncate">{{ $ro->customer_name ?: 'Customer #'.$ro->id }}</div>
                                <div class="text-[10px] text-muted truncate">{{ kpi_time($ro->ordered_at, 'M j, g:i A') }}</div>
                            </div>
                        </div>
                        <div class="col-span-4 min-w-0">
                            <span class="pill !px-2 !py-0 {{ $srcMeta[$ro->source_type ?? 'pending']['pill'] ?? 'bg-slate-100 text-muted' }}">
                                {{ $srcMeta[$ro->source_type ?? 'pending']['label'] ?? 'POS Only' }}
                            </span>
                        </div>
                        <div class="col-span-3 text-right num font-bold text-xs text-ink">
                            {{ kpi_peso($ro->total) }}
                        </div>
                    </a>
                @empty
                    <div class="p-8 text-center text-xs text-muted">No valid orders in this date range.</div>
                @endforelse
            </div>

            {{-- Sypher-Style Bottom Pagination Bar --}}
            <div class="px-6 py-3 border-t border-line bg-slate-50/40 flex flex-wrap items-center justify-between gap-2 text-xs text-muted mt-auto">
                <div class="flex items-center gap-2">
                    <span>Show</span>
                    <select id="rp-per-page" aria-label="Rows per page" class="rounded-lg border border-line bg-white px-2 py-1 text-xs font-semibold text-ink cursor-pointer">
                        <option value="8" selected>8</option>
                        <option value="15">15</option>
                        <option value="999">All</option>
                    </select>
                    <span id="rp-range-label" class="num text-[11px] text-muted"></span>
                </div>

                <div class="flex items-center gap-1.5">
                    <button type="button" id="rp-prev" aria-label="Previous page"
                            class="px-2.5 py-1 rounded-lg border border-line bg-white text-ink font-medium hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none cursor-pointer">
                        ‹ Prev
                    </button>
                    <span id="rp-page-indicator" class="num px-2 font-semibold text-ink">1 / 1</span>
                    <button type="button" id="rp-next" aria-label="Next page"
                            class="px-2.5 py-1 rounded-lg border border-line bg-white text-ink font-medium hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none cursor-pointer">
                        Next ›
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
