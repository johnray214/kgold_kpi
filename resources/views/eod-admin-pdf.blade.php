<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Team Leader EOD - {{ $reportDateFormatted }}</title>
    <style>
        @page { margin: 24px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.8px; line-height: 1.42; color: #1e293b; margin: 0; }
        h1 { font-size: 15px; font-weight: bold; color: #162B4D; margin: 0 0 2px 0; }
        h2 { font-size: 11px; font-weight: bold; color: #162B4D; text-transform: uppercase; margin: 13px 0 5px 0; letter-spacing: 0.25px; }
        h3 { font-size: 10px; font-weight: bold; color: #0f172a; margin: 8px 0 3px 0; }
        table { width: 100%; border-collapse: collapse; margin: 4px 0 6px 0; }
        th { background-color: #162B4D; color: #ffffff; font-size: 8.8px; font-weight: bold; text-transform: uppercase; padding: 5px 7px; text-align: left; }
        td { padding: 4.5px 7px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; }
        tr.alt td { background-color: #f8fafc; }
        .meta-tbl td { border: 1px solid #cbd5e1; }
        .meta-lbl { background-color: #f8fafc; font-weight: bold; color: #475569; width: 120px; }
        .sub-tbl th { background-color: #f1f5f9; color: #334155; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; }
        .kpi-strip td { border: 1px solid #cbd5e1; text-align: center; padding: 6px 4px; background-color: #f8fafc; width: 20%; }
        .kpi-big { font-size: 12.5px; font-weight: bold; color: #0f172a; }
        .kpi-sub { font-size: 8px; color: #64748b; margin-top: 1px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .good { color: #047857; font-weight: bold; }
        .indigo { color: #4B55F5; font-weight: bold; }
        .amber { color: #b45309; font-weight: bold; }
        .muted { color: #64748b; }
        .sig-footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #cbd5e1; }
    </style>
</head>
<body>
    @php
        $aiSharePct = $todayStats['orders'] > 0 ? ($todayStats['ai_orders'] / $todayStats['orders'] * 100) : 0;
        $aovToday = $todayStats['orders'] > 0 ? ($todayStats['sales'] / $todayStats['orders']) : 0;
        $roasToday = $totalAdSpend > 0 ? ($todayStats['sales'] / $totalAdSpend) : null;
        $roasYest = $yestAdSpend > 0 ? ($yesterdayStats['sales'] / $yestAdSpend) : null;
        $convToday = $totalInquiries > 0 ? ($todayStats['orders'] / $totalInquiries * 100) : null;

        $combinedMissed = array_merge($missedByPage, array_values(array_filter($extraMissedRows, fn ($r) => !empty(trim((string) ($r['page'] ?? ''))))));
        $missedOrdSum = collect($combinedMissed)->sum(fn ($r) => (int) ($r['orders'] ?? 0));
        $missedSalSum = collect($combinedMissed)->sum(fn ($r) => (float) ($r['sales'] ?? 0));
    @endphp

    <div style="border-bottom: 2px solid #162B4D; padding-bottom: 6px; margin-bottom: 8px;">
        <h1>AUTOMATION TEAM — CONSOLIDATED EOD REPORT</h1>
        <div class="muted">Cutoff Coverage: <strong>{{ $windowLabel }}</strong> ({{ $reportDateFormatted }})</div>
    </div>

    <table class="meta-tbl">
        <tr>
            <td class="meta-lbl">Prepared By</td>
            <td class="bold">{{ $prepBy }}</td>
            <td class="meta-lbl">Date</td>
            <td class="bold">{{ $reportDateFormatted }}</td>
        </tr>
        <tr>
            <td class="meta-lbl">Position</td>
            <td>{{ $position }}</td>
            <td class="meta-lbl">Department</td>
            <td>{{ $department }}</td>
        </tr>
        <tr>
            <td class="meta-lbl">Team Consolidated</td>
            <td colspan="3">{{ $consolidatedNames }}</td>
        </tr>
    </table>

    <table class="kpi-strip">
        <tr>
            <td>
                <div class="kpi-big indigo">{{ $convToday !== null ? number_format($convToday, 1).'%' : number_format($aiSharePct, 1).'%' }}</div>
                <div class="kpi-sub">{{ $convToday !== null ? 'Conv. Rate ('.$endTimeShort.')' : 'Botcake AI Share' }}</div>
            </td>
            <td>
                <div class="kpi-big good">{{ kpi_peso($todayStats['sales']) }}</div>
                <div class="kpi-sub">Total Sales ({{ kpi_num($todayStats['orders']) }} ord)</div>
            </td>
            <td>
                <div class="kpi-big">{{ $roasToday !== null ? number_format($roasToday, 2).'x' : kpi_peso($aovToday) }}</div>
                <div class="kpi-sub">{{ $roasToday !== null ? 'Real POS ROAS' : 'Verified AOV' }}</div>
            </td>
            <td>
                <div class="kpi-big amber">{{ number_format($missedOrdSum) }} / ₱{{ number_format($missedSalSum, 0) }}</div>
                <div class="kpi-sub">Manual / Agent Orders</div>
            </td>
            <td>
                <div class="kpi-big">{{ $activeBcPagesCount }} / {{ $totalPagesCount }}</div>
                <div class="kpi-sub">Active Broadcast Pages</div>
            </td>
        </tr>
    </table>

    <h2>1. KEY NUMBERS</h2>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="center">Today (as of {{ $endTimeShort }})</th>
                <th class="center">{{ $prevDateShort }} (Full Day)</th>
                <th class="center">Target</th>
            </tr>
        </thead>
        <tbody>
            @if ($convToday !== null)
                <tr>
                    <td>Conversion rate (as of {{ $endTimeShort }})</td>
                    <td class="center indigo">{{ number_format($convToday, 1) }}%</td>
                    <td class="center">{{ $yestConvPct ?: '—' }}</td>
                    <td class="center">20%</td>
                </tr>
            @endif
            <tr class="alt">
                <td>Total POS sales / qty (as of {{ $endTimeShort }})</td>
                <td class="center good">{{ kpi_peso($todayStats['sales']) }} / {{ kpi_num($todayStats['orders']) }}</td>
                <td class="center">{{ kpi_peso($yesterdayStats['sales']) }} / {{ kpi_num($yesterdayStats['orders']) }}</td>
                <td class="center muted">—</td>
            </tr>
            <tr>
                <td>Botcake AI closed sales / qty</td>
                <td class="center bold">{{ kpi_peso($todayStats['ai_sales']) }} / {{ kpi_num($todayStats['ai_orders']) }} ({{ number_format($aiSharePct, 1) }}%)</td>
                <td class="center">{{ kpi_peso($yesterdayStats['ai_sales']) }} / {{ kpi_num($yesterdayStats['ai_orders']) }}</td>
                <td class="center muted">—</td>
            </tr>
            <tr class="alt">
                <td>Broadcast &amp; Sequence driven orders</td>
                <td class="center bold">{{ kpi_num($todayStats['bc_orders'] + $todayStats['bc_assisted'] + $todayStats['seq_orders'] + $todayStats['seq_assisted']) }}</td>
                <td class="center">{{ kpi_num($yesterdayStats['bc_orders'] + $yesterdayStats['bc_assisted'] + $yesterdayStats['seq_orders'] + $yesterdayStats['seq_assisted']) }}</td>
                <td class="center muted">—</td>
            </tr>
            @if ($roasToday !== null)
                <tr>
                    <td>Real POS ROAS / Ad spend</td>
                    <td class="center good">{{ number_format($roasToday, 2) }}x (₱{{ number_format($totalAdSpend, 0) }})</td>
                    <td class="center">{{ $roasYest !== null ? number_format($roasYest, 2).'x (₱'.number_format($yestAdSpend, 0).')' : '—' }}</td>
                    <td class="center">3.00x+</td>
                </tr>
            @endif
            <tr>
                <td>Orders handled manually / missed by chatbot</td>
                <td class="center amber">{{ number_format($missedOrdSum) }} / ₱{{ number_format($missedSalSum, 0) }}</td>
                <td class="center">{{ $yesterdayStats['agent_orders'] }} / {{ kpi_peso($yesterdayStats['agent_sales']) }}</td>
                <td class="center">0</td>
            </tr>
            <tr class="alt">
                <td>Pages with manual / missed chatbot orders</td>
                <td class="center bold">{{ count($combinedMissed) }}</td>
                <td class="center muted">—</td>
                <td class="center">0</td>
            </tr>
            <tr>
                <td>Active pages on broadcast ({{ kpi_num($todayStats['bc_checked']) }} sends)</td>
                <td class="center bold">{{ $activeBcPagesCount }} active of {{ $totalPagesCount }}</td>
                <td class="center muted">—</td>
                <td class="center muted">—</td>
            </tr>
            @if ($creativesPending !== '')
                <tr class="alt">
                    <td>Meta Ads system: creatives awaiting decision</td>
                    <td class="center bold">{{ $creativesPending }}</td>
                    <td class="center muted">—</td>
                    <td class="center muted">—</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2>2. PERFORMANCE BY PRODUCT — AS OF {{ $endTimeShort }}</h2>
    @php $hasAnyProdAds = !empty(array_filter($adMeta, fn ($m) => ((float) ($m['inq'] ?? 0) > 0) || ((float) ($m['spend'] ?? 0) > 0))); @endphp
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="right">Pages</th>
                <th class="right">Qty Sold</th>
                <th class="right">POS Sales</th>
                <th class="right">AOV</th>
                <th class="right">AI Closed</th>
                @if ($hasAnyProdAds)
                    <th class="right">Inq.</th>
                    <th class="right">Ad Spend</th>
                    <th class="right">Conv%</th>
                    <th class="right">ROAS</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($productRows as $i => $pr)
                @php
                    $pm = $adMeta[$pr['product']] ?? [];
                    $pInq = (int) ($pm['inq'] ?? 0);
                    $pSpend = (float) ($pm['spend'] ?? 0);
                    $pConv = $pInq > 0 ? ($pr['orders'] / $pInq * 100) : null;
                    $pRoas = $pSpend > 0 ? ($pr['sales'] / $pSpend) : null;
                @endphp
                <tr class="{{ $i % 2 === 1 ? 'alt' : '' }}">
                    <td class="bold">{{ $pr['product'] }}</td>
                    <td class="right muted">{{ $pr['pages_count'] }}</td>
                    <td class="right bold">{{ kpi_num($pr['orders']) }}</td>
                    <td class="right good">{{ $pr['sales'] > 0 ? kpi_peso($pr['sales']) : '₱0' }}</td>
                    <td class="right">{{ $pr['orders'] > 0 ? kpi_peso($pr['aov']) : '—' }}</td>
                    <td class="right indigo">{{ kpi_num($pr['ai_orders']) }}</td>
                    @if ($hasAnyProdAds)
                        <td class="right">{{ $pInq > 0 ? number_format($pInq) : '—' }}</td>
                        <td class="right">{{ $pSpend > 0 ? '₱'.number_format($pSpend, 0) : '—' }}</td>
                        <td class="right bold">{{ $pConv !== null ? number_format($pConv, 1).'%' : '—' }}</td>
                        <td class="right good">{{ $pRoas !== null ? number_format($pRoas, 2).'x' : '—' }}</td>
                    @endif
                </tr>
            @endforeach
            <tr class="alt">
                <td class="bold">GRAND TOTAL</td>
                <td class="right bold">{{ $totalPagesCount }}</td>
                <td class="right bold">{{ kpi_num($todayStats['orders']) }}</td>
                <td class="right good">{{ kpi_peso($todayStats['sales']) }}</td>
                <td class="right bold">{{ $todayStats['orders'] > 0 ? kpi_peso($aovToday) : '—' }}</td>
                <td class="right indigo">{{ kpi_num($todayStats['ai_orders']) }}</td>
                @if ($hasAnyProdAds)
                    <td class="right bold">{{ $totalInquiries > 0 ? number_format($totalInquiries) : '—' }}</td>
                    <td class="right bold">{{ $totalAdSpend > 0 ? '₱'.number_format($totalAdSpend, 0) : '—' }}</td>
                    <td class="right bold">{{ $convToday !== null ? number_format($convToday, 1).'%' : '—' }}</td>
                    <td class="right good">{{ $roasToday !== null ? number_format($roasToday, 2).'x' : '—' }}</td>
                @endif
            </tr>
        </tbody>
    </table>

    <h2>3. ACTIVITIES BY TEAM MEMBER</h2>
    <h3>A. {{ $prepBy }} — {{ $position }}</h3>
    <p style="margin: 2px 0 6px 0;">{{ $leaderSummary ?: "Consolidated automation & POS attribution across {$totalPagesCount} active pages ({$activeBcPagesCount} pages ran scheduled broadcasts). Monitored inbox handoffs and manual recoveries." }}</p>

    @if (!empty($combinedMissed))
        <table class="sub-tbl">
            <thead>
                <tr>
                    <th style="width:26px;">#</th>
                    <th>Facebook Page</th>
                    <th>Page Owner / Team</th>
                    <th class="right">Orders</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($combinedMissed as $mi => $mr)
                    <tr>
                        <td>{{ $mi + 1 }}</td>
                        <td class="bold">{{ $mr['page'] }}</td>
                        <td>{{ $mr['owner'] ?? '—' }}</td>
                        <td class="right bold">{{ number_format((int) ($mr['orders'] ?? 0)) }}</td>
                        <td class="right good">₱{{ number_format((float) ($mr['sales'] ?? 0), 0) }}</td>
                    </tr>
                @endforeach
                <tr class="alt">
                    <td></td>
                    <td colspan="2" class="bold">TOTAL — {{ count($combinedMissed) }} page(s)</td>
                    <td class="right bold">{{ number_format($missedOrdSum) }}</td>
                    <td class="right good">₱{{ number_format($missedSalSum, 0) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @foreach ($memberSections as $idx => $ms)
        @php
            $letter = chr(66 + $idx);
            $noteTxt = $memberNotes[(string) $ms['id']] ?? ($ms['submission']['summary'] ?? $ms['default_summary']);
        @endphp
        <h3>{{ $letter }}. {{ $ms['name'] }} — Assigned Pages ({{ $ms['pages_count'] }})</h3>
        <p style="margin: 2px 0 6px 0;">{{ $noteTxt }}</p>
    @endforeach

    @if (!empty($issuesRows))
        <h2>4. ISSUES &amp; BLOCKERS</h2>
        <table>
            <thead>
                <tr>
                    <th>Issue</th>
                    <th style="width:95px;">Owner</th>
                    <th style="width:95px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($issuesRows as $ii => $ir)
                    @if (trim((string) ($ir['issue'] ?? '')) !== '')
                        <tr class="{{ $ii % 2 === 1 ? 'alt' : '' }}">
                            <td>{{ $ir['issue'] }}</td>
                            <td class="bold">{{ $ir['owner'] ?? 'Team' }}</td>
                            <td>{{ $ir['status'] ?? 'Open' }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>5. DECISIONS NEEDED</h2>
    <table class="sub-tbl">
        <thead>
            <tr>
                <th>Decision</th>
                <th style="width:95px;">By</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $decisionText ?: 'None / Wala.' }}</td>
                <td class="bold">{{ $decisionBy ?: '—' }}</td>
            </tr>
        </tbody>
    </table>

    <h2>6. TOP 3 PRIORITIES — NEXT WORKING DAY</h2>
    <table class="sub-tbl">
        <thead>
            <tr>
                <th style="width:26px;">#</th>
                <th>Priority</th>
                <th style="width:110px;">Owner</th>
            </tr>
        </thead>
        <tbody>
            @foreach ([[1, $prio1, $prio1Owner], [2, $prio2, $prio2Owner], [3, $prio3, $prio3Owner]] as [$num, $pText, $pOwn])
                @if (trim((string) $pText) !== '')
                    <tr>
                        <td class="bold">{{ $num }}</td>
                        <td>{{ $pText }}</td>
                        <td class="bold">{{ $pOwn }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="sig-footer">
        <div class="muted">Prepared By:</div>
        <div class="bold">{{ $prepBy }}</div>
        <div>{{ $position }} · {{ $department }}</div>
        <div class="muted">K Gold Beauty Essentials Corporation · Generated {{ $generatedAtLabel }} ({{ $windowLabel }})</div>
    </div>
</body>
</html>
