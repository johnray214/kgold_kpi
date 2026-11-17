<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>EOD Report - {{ $prepBy }} - {{ $reportDateFormatted }}</title>
    <style>
        @page { margin: 28px 34px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            line-height: 1.48;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        h1 {
            font-size: 16.5px;
            font-weight: bold;
            color: #162B4D;
            margin: 0 0 8px 0;
            letter-spacing: -0.2px;
        }
        h2 {
            font-size: 11.5px;
            font-weight: bold;
            color: #162B4D;
            text-transform: uppercase;
            margin: 14px 0 6px 0;
            letter-spacing: 0.3px;
        }
        h3 {
            font-size: 10.5px;
            font-weight: bold;
            color: #0f172a;
            margin: 8px 0 3px 0;
        }
        .header-box {
            border-bottom: 2px solid #162B4D;
            padding-bottom: 9px;
            margin-bottom: 10px;
        }
        .meta-line { margin: 2px 0; font-size: 10.5px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 6px;
        }
        th {
            background-color: #162B4D;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
        }
        td {
            padding: 5.5px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        tr.alt td { background-color: #f8fafc; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .good { color: #047857; font-weight: bold; }
        .indigo { color: #4B55F5; font-weight: bold; }
        .muted { color: #64748b; }
        ul, ol { margin: 3px 0 6px 0; padding-left: 18px; }
        li { margin-bottom: 2.5px; }
        .sub-head th {
            background-color: #f1f5f9;
            color: #334155;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .sig-footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #cbd5e1;
        }
    </style>
</head>
<body>
    @php
        $pCount = $assignedPages->count();
        $pageNamesPreview = $assignedPages->take(5)->pluck('name')->implode(', ').($pCount > 5 ? ' +'.($pCount - 5).' more' : '');
        $customTotalOrders = collect($customRows)->sum(fn ($r) => (int) ($r['orders'] ?? 0));
        $customTotalSales = collect($customRows)->sum(fn ($r) => (float) ($r['sales'] ?? 0));
    @endphp

    <div class="header-box">
        <h1>AUTOMATION TEAM - EOD REPORT</h1>
        <div class="meta-line"><strong>Prepared By:</strong> {{ $prepBy }}</div>
        <div class="meta-line"><strong>Date:</strong> {{ $reportDateFormatted }} &nbsp;|&nbsp; <strong>Time Covered:</strong> <strong>{{ $windowLabel }}</strong></div>
        <div class="meta-line"><strong>Assigned Coverage:</strong> {{ $coverage }}</div>
    </div>

    <h2>1. KEY NUMBERS</h2>
    <table>
        <thead>
            <tr>
                <th>KPI</th>
                <th class="center">Today ({{ $isToday ? '12AM-Now' : 'Full Day' }})</th>
                <th class="center">Yesterday</th>
                <th class="center">Target</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Pages Monitored</td>
                <td class="center bold">{{ $pCount }}</td>
                <td class="center">{{ $pCount }}</td>
                <td class="center">{{ $pCount }}</td>
            </tr>
            <tr class="alt">
                <td>Broadcasts Checked</td>
                <td class="center bold">{{ kpi_num($todayStats['bc_checked']) }}</td>
                <td class="center">{{ kpi_num($yesterdayStats['bc_checked']) }}</td>
                <td class="center">{{ $bcTarget !== '' ? $bcTarget : '—' }}</td>
            </tr>
            <tr>
                <td>Successful Broadcasts</td>
                <td class="center good">{{ kpi_num($todayStats['bc_successful']) }}</td>
                <td class="center">{{ kpi_num($yesterdayStats['bc_successful']) }}</td>
                <td class="center">{{ $bcTarget !== '' ? $bcTarget : '—' }}</td>
            </tr>
            <tr class="alt">
                <td>Verified POS Orders (Assigned Pages)</td>
                <td class="center indigo">{{ kpi_num($todayStats['orders']) }}</td>
                <td class="center">{{ kpi_num($yesterdayStats['orders']) }}</td>
                <td class="center {{ ($posTarget ?? '') !== '' ? '' : 'muted' }}">{{ ($posTarget ?? '') !== '' ? $posTarget : '—' }}</td>
            </tr>
            <tr>
                <td>Botcake AI Orders Closed</td>
                <td class="center bold">{{ kpi_num($todayStats['ai_orders']) }}</td>
                <td class="center">{{ kpi_num($yesterdayStats['ai_orders']) }}</td>
                <td class="center {{ ($aiTarget ?? '') !== '' ? '' : 'muted' }}">{{ ($aiTarget ?? '') !== '' ? $aiTarget : '—' }}</td>
            </tr>
            <tr class="alt">
                <td>Phone Leads Captured (Broadcasts)</td>
                <td class="center bold">{{ kpi_num($todayStats['bc_phones']) }}</td>
                <td class="center">{{ kpi_num($yesterdayStats['bc_phones']) }}</td>
                <td class="center {{ ($phonesTarget ?? '') !== '' ? '' : 'muted' }}">{{ ($phonesTarget ?? '') !== '' ? $phonesTarget : '—' }}</td>
            </tr>
            @if ($macroToday !== '' || $macroTarget !== '')
                <tr>
                    <td>Bot/Macro Issues Resolved</td>
                    <td class="center bold">{{ $macroToday !== '' ? $macroToday : '0' }}</td>
                    <td class="center muted">—</td>
                    <td class="center">{{ $macroTarget !== '' ? $macroTarget : '—' }}</td>
                </tr>
            @endif
            @if ($inqToday !== '' || $inqYest !== '' || $inqTarget !== '')
                <tr class="alt">
                    <td>Unanswered Inquiries Assisted</td>
                    <td class="center bold">{{ $inqToday !== '' ? $inqToday : '0' }}</td>
                    <td class="center">{{ $inqYest !== '' ? $inqYest : '—' }}</td>
                    <td class="center">{{ $inqTarget !== '' ? $inqTarget : '—' }}</td>
                </tr>
            @endif
            @if ($pendingConcerns !== '' || ($pendingTarget ?? '') !== '')
                <tr>
                    <td>Pending Customer Concerns</td>
                    <td class="center bold">{{ $pendingConcerns !== '' ? $pendingConcerns : '0' }}</td>
                    <td class="center muted">—</td>
                    <td class="center">{{ ($pendingTarget ?? '') !== '' ? $pendingTarget : '0' }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2>2. SALES / OPERATIONAL IMPACT</h2>
    <ul>
        <li>
            <strong>Assigned Page Orders ({{ $windowLabel }}):</strong>
            {{ kpi_num($todayStats['orders']) }} valid POS orders
            (Botcake AI: {{ kpi_num($todayStats['ai_orders']) }}, Broadcast: {{ kpi_num($todayStats['bc_orders']) }}{{ $todayStats['bc_assisted'] > 0 ? ' + '.$todayStats['bc_assisted'].' AI-closed' : '' }}, Sequence: {{ kpi_num($todayStats['seq_orders']) }}{{ $todayStats['seq_assisted'] > 0 ? ' + '.$todayStats['seq_assisted'].' AI-closed' : '' }}, Agent/Organic: {{ kpi_num($todayStats['agent_orders'] + $todayStats['organic_orders']) }})
        </li>
        <li>
            <strong>Financial Impact (Verified POS Sales):</strong>
            <span class="good">{{ kpi_peso($todayStats['sales']) }}</span>
            (AI: {{ kpi_peso($todayStats['ai_sales']) }} · Yesterday full day: {{ kpi_peso($yesterdayStats['sales']) }})
        </li>
        <li>
            <strong>Broadcast Delivery &amp; Failures:</strong>
            {{ $todayStats['bc_failures'] }} failed broadcast run(s) ·
            {{ kpi_num($todayStats['bc_delivered']) }} / {{ kpi_num($todayStats['bc_targeted']) }} delivered ({{ number_format($todayStats['deliv_rate'], 1) }}%) ·
            {{ kpi_num($todayStats['bc_not_delivered']) }} undelivered (Blocked #551: {{ kpi_num($todayStats['bc_blocked']) }}, API Limit #613: {{ kpi_num($todayStats['bc_api_limit']) }})
        </li>
        @if ($inqToday !== '')
            <li><strong>Customer Inquiries Assisted:</strong> {{ $inqToday }}</li>
        @endif
        @if ($pendingConcerns !== '')
            <li><strong>Pending Customer Concerns:</strong> {{ $pendingConcerns }}</li>
        @endif
    </ul>

    @if (! empty($customRows))
        <h3>Additional Macro / Broadcast / Manual Sales Logged</h3>
        <table class="sub-head">
            <thead>
                <tr>
                    <th>Facebook Page</th>
                    <th>Type / Source</th>
                    <th class="right">Orders</th>
                    <th class="right">Sales Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customRows as $cr)
                    <tr>
                        <td class="bold">{{ $cr['page'] ?? '—' }}</td>
                        <td>{{ $cr['type'] ?? 'Macro / Broadcast' }}</td>
                        <td class="right bold">{{ number_format((int) ($cr['orders'] ?? 0)) }}</td>
                        <td class="right good">₱{{ number_format((float) ($cr['sales'] ?? 0), 0) }}</td>
                        <td class="muted">{{ $cr['note'] ?? '' }}</td>
                    </tr>
                @endforeach
                <tr class="alt">
                    <td colspan="2" class="bold">TOTAL ADDITIONAL LOGGED</td>
                    <td class="right bold">{{ number_format($customTotalOrders) }}</td>
                    <td class="right good">₱{{ number_format($customTotalSales, 0) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @endif

    @if ($includePagesTable && $assignedBreakdown->isNotEmpty())
        <h3>Assigned Pages Breakdown ({{ $windowLabel }})</h3>
        <table class="sub-head">
            <thead>
                <tr>
                    <th>Assigned Page</th>
                    <th class="right">Broadcasts</th>
                    <th class="right">Phone Leads</th>
                    <th class="right">AI Orders</th>
                    <th class="right">POS Orders</th>
                    <th class="right">POS Sales</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assignedBreakdown as $ab)
                    <tr>
                        <td>{{ $ab['name'] }}</td>
                        <td class="right">{{ kpi_num($ab['bc_count']) }}</td>
                        <td class="right">{{ kpi_num($ab['bc_phones']) }}</td>
                        <td class="right">{{ kpi_num($ab['ai_orders']) }}</td>
                        <td class="right bold">{{ kpi_num($ab['orders']) }}</td>
                        <td class="right good">{{ $ab['sales'] > 0 ? kpi_peso($ab['sales']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>3. ISSUES, BLOCKERS &amp; ACTIVITIES COMPLETED</h2>

    @php
        $secLists = [
            'A. Broadcast Monitoring' => $bullets['a'] ?? [
                "Monitored Utility (Outside 24h) and Within-window broadcasts across {$pCount} assigned page(s) ({$windowLabel}).",
                'Checked '.kpi_num($todayStats['bc_checked']).' broadcast send(s) totaling '.kpi_num($todayStats['bc_delivered']).' delivered messages ('.number_format($todayStats['deliv_rate'], 1).'% delivery rate) and '.kpi_num($todayStats['bc_phones']).' phone leads.',
                $todayStats['bc_failures'] > 0 ? "Identified {$todayStats['bc_failures']} broadcast send issue(s) for review." : '0 broadcast run failures recorded in the current cutoff window.',
            ],
            'B. Page & Inbox Monitoring' => $bullets['b'] ?? array_values(array_filter([
                "Monitored {$pCount} assigned page(s) ({$pageNamesPreview}) and checked customer interactions.",
                'Tracked '.kpi_num($todayStats['orders']).' verified POS order(s) ('.kpi_peso($todayStats['sales']).') across assigned pages.',
                ($inqToday !== '' || $pendingConcerns !== '')
                    ? trim(($inqToday !== '' ? "Assisted {$inqToday} customer inquiries." : '').' '.($pendingConcerns !== '' ? "Identified {$pendingConcerns} pending customer concern(s) for follow-up." : ''))
                    : null,
            ])),
            'C. Bot & Macro Monitoring' => $bullets['c'] ?? array_values(array_filter([
                'Reviewed Botcake AI ('.kpi_num($todayStats['ai_orders']).' orders closed directly by AI) and macro responses for accuracy.',
                $macroToday !== '' ? "Corrected {$macroToday} identified bot/macro response issue(s)." : null,
            ])),
            'D. Automation Development' => $bullets['d'] ?? [
                'Reviewed '.kpi_num($activeSequences).' active automation sequence(s) ('.kpi_num($todayStats['seq_orders'] + $todayStats['seq_assisted']).' sequence-driven orders) and customer response flows.',
                'Checked step drop-off points and opportunities for improving welcome messages and follow-up sequences.',
            ],
            'E. Team Coordination' => $bullets['e'] ?? [
                'Reported unresolved concerns to the appropriate team members and coordinated automation-related updates.',
            ],
        ];
    @endphp

    @foreach ($secLists as $heading => $items)
        @if (! empty($items))
            <h3>{{ $heading }}</h3>
            <ul>
                @foreach ($items as $line)
                    @if (trim((string) $line) !== '')
                        <li>{{ $line }}</li>
                    @endif
                @endforeach
            </ul>
        @endif
    @endforeach

    <h2>4. DECISIONS / SUPPORT NEEDED</h2>
    <ul>
        @if ($supportNeeded !== '')
            <li>{{ $supportNeeded }}</li>
        @endif
        <li><strong>CEO Decision:</strong> {{ $ceoDecision ?: 'Wala.' }}</li>
    </ul>

    <h2>5. TOP 3 PRIORITIES TOMORROW</h2>
    <ol>
        @foreach ([$prio1, $prio2, $prio3] as $pr)
            @if (trim((string) $pr) !== '')
                <li>{{ $pr }}</li>
            @endif
        @endforeach
    </ol>

    <div class="sig-footer">
        <div class="bold">Prepared by: {{ $prepBy }}</div>
        <div>{{ $roleTitle }}</div>
        <div class="muted" style="font-size:9px; margin-top:4px;">
            Generated {{ $generatedAtLabel }} · Coverage {{ $windowLabel }} ({{ $pCount }} assigned pages)
        </div>
    </div>
</body>
</html>
