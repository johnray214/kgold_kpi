@extends('layouts.app', ['title' => 'Team Leader EOD · KGOLD AUTOMATION TEAM KPI'])

@php
    $aiSharePct = $todayStats['orders'] > 0 ? ($todayStats['ai_orders'] / $todayStats['orders'] * 100) : 0;
    $aovToday = $todayStats['orders'] > 0 ? ($todayStats['sales'] / $todayStats['orders']) : 0;
    $missedPagesCount = count($missedByPage);
@endphp

@section('content')
    {{-- Top Mode Bar + Direct PDF & Copy Actions --}}
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex items-center rounded-xl bg-white p-1 border border-line text-xs shadow-2xs">
                <a href="{{ route('eod', ['date' => $reportDate]) }}" class="px-3 py-1.5 rounded-lg bg-primary text-white font-semibold cursor-pointer">
                    Team Leader Consolidated EOD
                </a>
                <a href="{{ route('eod', ['mode' => 'member', 'date' => $reportDate]) }}" class="px-3 py-1.5 rounded-lg text-muted hover:text-ink font-medium cursor-pointer">
                    Member EOD Preview
                </a>
            </div>
            <span class="pill bg-primary-soft text-primary num">{{ $windowLabel }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('inbox') }}" class="btn-ghost !text-xs">
                <x-icon name="inbox" class="size-3.5 text-bad"/>
                <span>Unanswered Inbox ({{ kpi_num($inboxCounts['unanswered'] ?? 0) }})</span>
            </a>

            <button type="button" id="adm-eod-reset-draft" class="btn-ghost !text-xs text-muted hover:text-bad" title="Clear saved optional inputs for this date">
                <span>Reset Draft</span>
            </button>

            <button type="button" id="adm-eod-copy-btn" class="btn-ghost">
                <x-icon name="copy" class="size-3.5 text-primary"/>
                <span id="adm-eod-copy-label">Copy for Chat</span>
            </button>

            <form id="adm-eod-pdf-form" method="POST" action="{{ route('eod.admin.pdf') }}" data-download-form="1">
                @csrf
                <input type="hidden" name="date" value="{{ $reportDate }}">
                <input type="hidden" name="frozen_end" value="{{ $frozenEndIso }}">
                <input type="hidden" name="prep_by" id="apdf-prep-by" value="{{ auth()->user()->name }}">
                <input type="hidden" name="position" id="apdf-position" value="Automation Team Leader">
                <input type="hidden" name="department" id="apdf-department" value="Automation - Marketing">
                <input type="hidden" name="consolidated_names" id="apdf-consolidated" value="{{ $defaultConsolidatedNames }}">
                <input type="hidden" name="total_ad_spend" id="apdf-spend" value="0">
                <input type="hidden" name="total_inquiries" id="apdf-inq" value="0">
                <input type="hidden" name="yest_ad_spend" id="apdf-yest-spend" value="0">
                <input type="hidden" name="yest_conv_pct" id="apdf-yest-conv" value="">
                <input type="hidden" name="creatives_pending" id="apdf-creatives" value="">
                <input type="hidden" name="leader_summary" id="apdf-leader-sum" value="">
                <input type="hidden" name="decision_text" id="apdf-decision" value="None / Wala.">
                <input type="hidden" name="decision_by" id="apdf-decision-by" value="—">
                <input type="hidden" name="prio_1" id="apdf-prio-1" value="">
                <input type="hidden" name="prio_1_owner" id="apdf-prio-1-own" value="Automation Team">
                <input type="hidden" name="prio_2" id="apdf-prio-2" value="">
                <input type="hidden" name="prio_2_owner" id="apdf-prio-2-own" value="Team Leader">
                <input type="hidden" name="prio_3" id="apdf-prio-3" value="">
                <input type="hidden" name="prio_3_owner" id="apdf-prio-3-own" value="Automation Team">
                <input type="hidden" name="product_ads_json" id="apdf-product-ads" value="{}">
                <input type="hidden" name="extra_kpi_rows_json" id="apdf-extra-kpis" value="[]">
                <input type="hidden" name="extra_missed_rows_json" id="apdf-extra-missed" value="[]">
                <input type="hidden" name="issues_rows_json" id="apdf-issues" value="[]">
                <input type="hidden" name="member_notes_json" id="apdf-member-notes" value="{}">

                <button type="submit" data-loading-text="Building PDF…" class="btn-primary">
                    <x-icon name="download" class="size-3.5"/>
                    <span>Download Team EOD PDF</span>
                </button>
            </form>
        </div>
    </div>

    <div class="grid lg:grid-cols-12 gap-5 items-start">
        {{-- ================= LEFT CONTROLS (4/12): Cutoff, Optional Ads/ROAS, Highlights & Issues ================= --}}
        <aside class="no-print lg:col-span-4 space-y-4">
            {{-- 1. Cutoff Generator --}}
            <section class="card overflow-hidden">
                <div class="card-head !px-5 !py-3.5">
                    <div>
                        <h2 class="card-title !text-sm"><x-icon name="clock" class="size-4 text-primary"/> 1. Generate Cutoff Window</h2>
                        <p class="card-sub !text-[11px]">12:00 AM → exact click time across all {{ $totalPagesCount }} active pages</p>
                    </div>
                </div>
                <form method="GET" action="{{ route('eod') }}" class="p-5 space-y-3">
                    <input type="hidden" name="gen" value="{{ now()->timestamp }}">
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">Report Date</span>
                        <input type="date" name="date" value="{{ $reportDate }}" class="field num cursor-pointer">
                    </label>
                    <button type="submit" data-loading-text="Generating…" class="btn-primary w-full !min-h-[38px]">
                        <x-icon name="refresh" class="size-3.5"/> Generate Consolidated EOD
                    </button>
                    <div class="text-[11px] text-muted text-center num">Generated: <strong class="text-ink">{{ $generatedAtLabel }}</strong></div>
                </form>
            </section>

            {{-- 2. Header & Optional Meta Ads Summary Inputs --}}
            <section class="card overflow-hidden">
                <div class="card-head !px-5 !py-3.5">
                    <div>
                        <h2 class="card-title !text-sm"><x-icon name="edit" class="size-4 text-primary"/> 2. Header & Optional Meta Ads Totals</h2>
                        <p class="card-sub !text-[11px]">Enter Ad Spend / Inquiries to auto-compute Real ROAS & Conv% (leave blank to omit)</p>
                    </div>
                </div>
                <div class="p-5 space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold text-muted uppercase">Prepared By</span>
                            <input type="text" id="adm-prep-by" value="{{ auth()->user()->name }}" class="field !min-h-[30px]">
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold text-muted uppercase">Position</span>
                            <input type="text" id="adm-position" value="Automation Team Leader" class="field !min-h-[30px]">
                        </label>
                    </div>
                    <label class="flex flex-col gap-1">
                        <span class="text-[10px] font-semibold text-muted uppercase">Department</span>
                        <input type="text" id="adm-dept" value="Automation - Marketing" class="field !min-h-[30px]">
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-[10px] font-semibold text-muted uppercase">Team Consolidated</span>
                        <input type="text" id="adm-consolidated" value="{{ $defaultConsolidatedNames }}" class="field !min-h-[30px]">
                    </label>

                    <div class="pt-2 border-t border-line grid grid-cols-2 gap-2">
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">Ad Spend Today (₱ opt)</span>
                            <input type="number" min="0" step="0.01" id="adm-spend" placeholder="Optional (computes ROAS)" class="field !min-h-[30px] num">
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">Inquiries Today (opt)</span>
                            <input type="number" min="0" id="adm-inq" placeholder="Optional (computes Conv%)" class="field !min-h-[30px] num">
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">Ad Spend {{ $prevDateShort }} (₱ opt)</span>
                            <input type="number" min="0" step="0.01" id="adm-yest-spend" placeholder="Optional" class="field !min-h-[30px] num">
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">Creatives Queue (opt)</span>
                            <input type="text" id="adm-creatives" placeholder="e.g. 18 awaiting (5 blocked)" class="field !min-h-[30px]">
                        </label>
                    </div>
                </div>
            </section>

            {{-- 3. Optional Extra Missed/Manual Order or Test Highlight --}}
            <section class="card overflow-hidden">
                <div class="card-head !px-5 !py-3.5">
                    <div>
                        <h2 class="card-title !text-sm"><x-icon name="plus" class="size-4 text-primary"/> 3. Add Extra Missed Order / Highlight</h2>
                        <p class="card-sub !text-[11px]">Type any page name or pick from the list (auto-saved across reloads)</p>
                    </div>
                    <button type="button" id="adm-add-missed-btn" class="btn-ghost !min-h-[28px] !px-2.5 !text-xs text-primary">
                        + Add Page Row
                    </button>
                </div>
                <div class="p-5 space-y-2 text-xs">
                    <datalist id="adm-all-pages-datalist">
                        @foreach ($allPages as $ap)
                            <option value="{{ $ap->name }}"></option>
                        @endforeach
                    </datalist>
                    <div id="adm-extra-missed-list" class="space-y-2"></div>
                    <template id="adm-missed-tpl">
                        <div data-adm-missed-row class="p-2.5 rounded-xl border border-line bg-slate-50/70 space-y-1.5">
                            <div class="grid grid-cols-12 gap-1.5 items-center">
                                <input type="text" list="adm-all-pages-datalist" autocomplete="off" data-am-page placeholder="Type or pick Facebook page…" class="col-span-7 field !min-h-[28px] !text-xs bg-white">
                                <input type="text" autocomplete="off" data-am-owner placeholder="Owner (auto/type)" class="col-span-4 field !min-h-[28px] !text-xs bg-white">
                                <button type="button" data-am-del title="Remove row" class="col-span-1 text-muted hover:text-bad flex justify-center cursor-pointer"><x-icon name="x" class="size-3.5"/></button>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <input type="number" min="0" data-am-orders placeholder="Orders (e.g. 1)" class="field !min-h-[28px] !text-xs bg-white num">
                                <input type="number" min="0" data-am-sales placeholder="₱ Amount (e.g. 999)" class="field !min-h-[28px] !text-xs bg-white num">
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            {{-- 4. Decisions & Next-Day Top 3 Priorities --}}
            <section class="card overflow-hidden">
                <div class="card-head !px-5 !py-3.5">
                    <h2 class="card-title !text-sm"><x-icon name="sparkles" class="size-4 text-primary"/> 4. Decisions & Top 3 Priorities</h2>
                </div>
                <div class="p-5 space-y-2.5 text-xs">
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text" id="adm-decision" value="None / Wala." placeholder="Decision needed" class="col-span-8 field !min-h-[30px]">
                        <input type="text" id="adm-decision-by" value="—" placeholder="By date" class="col-span-4 field !min-h-[30px]">
                    </div>
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text" id="adm-prio-1" value="{{ $missedPagesCount > 0 ? 'Audit chatbot flows on '.$missedPagesCount.' page(s) with manual/agent orders — target 0 missed orders.' : 'Audit chatbot & Botcake AI closing flows across top-volume pages.' }}" class="col-span-8 field !min-h-[30px]">
                        <input type="text" id="adm-prio-1-own" value="Team" class="col-span-4 field !min-h-[30px]">
                    </div>
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text" id="adm-prio-2" value="Review low-converting product chats and optimize sequence follow-ups." class="col-span-8 field !min-h-[30px]">
                        <input type="text" id="adm-prio-2-own" value="Team Leader" class="col-span-4 field !min-h-[30px]">
                    </div>
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text" id="adm-prio-3" value="Monitor scheduled broadcasts across all {{ $totalPagesCount }} active pages ({{ $activeBcPagesCount }} active today)." class="col-span-8 field !min-h-[30px]">
                        <input type="text" id="adm-prio-3-own" value="Automation Team" class="col-span-4 field !min-h-[30px]">
                    </div>
                </div>
            </section>
        </aside>

        {{-- ================= RIGHT PANEL (8/12): Consolidated Team Leader EOD Document ================= --}}
        <div class="lg:col-span-8">
            <article id="adm-eod-paper"
                     data-report-date="{{ $reportDate }}"
                     data-sales-today="{{ $todayStats['sales'] }}"
                     data-orders-today="{{ $todayStats['orders'] }}"
                     data-sales-yest="{{ $yesterdayStats['sales'] }}"
                     data-ai-share="{{ number_format($aiSharePct, 1) }}"
                     data-aov="{{ number_format($aovToday, 0) }}"
                     data-base-missed='@json($missedByPage)'
                     data-base-issues='@json($defaultIssues)'
                     data-page-owners='@json($pageNameOwners ?? [])'
                     class="bg-white rounded-2xl border border-line shadow-[0_6px_24px_rgba(15,23,42,0.06)] p-7 sm:p-9 space-y-6 text-[12.5px] leading-relaxed text-slate-800">

                {{-- Executive Metadata Header Table (Maricar EOD Format) --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between border-b-2 border-[#162B4D] pb-3">
                        <div class="flex items-center gap-2.5">
                            <x-brand-logo size="size-8" iconSize="18"/>
                            <div>
                                <h2 class="text-lg sm:text-xl font-extrabold tracking-tight text-[#162B4D]">
                                    AUTOMATION TEAM — CONSOLIDATED EOD REPORT
                                </h2>
                                <p class="text-[11px] text-muted num">Cutoff Coverage: <b>{{ $windowLabel }}</b> ({{ $reportDateFormatted }})</p>
                            </div>
                        </div>
                    </div>

                    <table class="w-full text-xs border border-slate-200 border-collapse">
                        <tbody>
                            <tr class="border-b border-slate-200">
                                <td class="py-1.5 px-3 bg-slate-50 font-semibold text-slate-600 w-36">Prepared By</td>
                                <td id="aout-prep-by" class="py-1.5 px-3 font-bold text-slate-900">{{ auth()->user()->name }}</td>
                                <td class="py-1.5 px-3 bg-slate-50 font-semibold text-slate-600 w-28">Date</td>
                                <td class="py-1.5 px-3 font-semibold text-slate-900">{{ $reportDateFormatted }}</td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="py-1.5 px-3 bg-slate-50 font-semibold text-slate-600">Position</td>
                                <td id="aout-position" class="py-1.5 px-3 text-slate-800">Automation Team Leader</td>
                                <td class="py-1.5 px-3 bg-slate-50 font-semibold text-slate-600">Department</td>
                                <td id="aout-dept" class="py-1.5 px-3 text-slate-800">Automation - Marketing</td>
                            </tr>
                            <tr>
                                <td class="py-1.5 px-3 bg-slate-50 font-semibold text-slate-600">Team Consolidated</td>
                                <td id="aout-consolidated" colspan="3" class="py-1.5 px-3 text-slate-800">{{ $defaultConsolidatedNames }}</td>
                            </tr>
                        </tbody>
                    </table>

                    {{-- 5-Card Executive KPI Strip --}}
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 num">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 text-center">
                            <div id="aout-kpi1-val" class="text-base font-extrabold text-[#4B55F5]">{{ number_format($aiSharePct, 1) }}%</div>
                            <div id="aout-kpi1-lbl" class="text-[10px] text-muted">Botcake AI Share ({{ $endTimeShort }})</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-emerald-50/50 p-2.5 text-center">
                            <div class="text-base font-extrabold text-emerald-700">{{ kpi_peso($todayStats['sales']) }}</div>
                            <div class="text-[10px] text-muted">POS Sales · {{ kpi_num($todayStats['orders']) }} ord</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 text-center">
                            <div id="aout-kpi3-val" class="text-base font-extrabold text-slate-900">₱{{ number_format($aovToday, 0) }}</div>
                            <div id="aout-kpi3-lbl" class="text-[10px] text-muted">Verified AOV</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-amber-50/50 p-2.5 text-center">
                            <div id="aout-kpi4-val" class="text-base font-extrabold text-amber-800">{{ $todayStats['agent_orders'] }} / {{ kpi_peso($todayStats['agent_sales']) }}</div>
                            <div class="text-[10px] text-muted">Manual / Agent Orders</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 text-center col-span-2 sm:col-span-1">
                            <div class="text-base font-extrabold text-slate-900">{{ $activeBcPagesCount }} / {{ $totalPagesCount }}</div>
                            <div class="text-[10px] text-muted">Active Broadcast Pages</div>
                        </div>
                    </div>
                </div>

                {{-- 1. KEY NUMBERS --}}
                <section class="space-y-2">
                    <h3 class="text-[13.5px] font-extrabold uppercase tracking-wide text-[#162B4D]">1. KEY NUMBERS</h3>
                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full text-xs border-collapse num">
                            <thead>
                                <tr class="bg-[#162B4D] text-white uppercase tracking-wider text-[10.5px]">
                                    <th class="py-2 px-3.5 text-left font-bold">Metric</th>
                                    <th class="py-2 px-3.5 text-center font-bold">Today (as of {{ $endTimeShort }})</th>
                                    <th class="py-2 px-3.5 text-center font-bold">{{ $prevDateShort }} (Full Day)</th>
                                    <th class="py-2 px-3.5 text-center font-bold">Target</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr id="arow-conv" class="hidden bg-white">
                                    <td class="py-2 px-3.5 font-medium">Conversion rate (as of {{ $endTimeShort }})</td>
                                    <td id="aout-conv-today" class="py-2 px-3.5 text-center font-bold text-[#4B55F5]">—</td>
                                    <td class="py-2 px-3.5 text-center text-slate-500">—</td>
                                    <td class="py-2 px-3.5 text-center">20%</td>
                                </tr>
                                <tr class="bg-slate-50/80">
                                    <td class="py-2 px-3.5 font-medium">Total POS sales / qty (as of {{ $endTimeShort }})</td>
                                    <td class="py-2 px-3.5 text-center font-bold text-emerald-700">{{ kpi_peso($todayStats['sales']) }} / {{ kpi_num($todayStats['orders']) }}</td>
                                    <td class="py-2 px-3.5 text-center text-slate-600">{{ kpi_peso($yesterdayStats['sales']) }} / {{ kpi_num($yesterdayStats['orders']) }}</td>
                                    <td class="py-2 px-3.5 text-center text-slate-400">—</td>
                                </tr>
                                <tr>
                                    <td class="py-2 px-3.5 font-medium">Botcake AI closed sales / qty</td>
                                    <td class="py-2 px-3.5 text-center font-bold text-slate-900">{{ kpi_peso($todayStats['ai_sales']) }} / {{ kpi_num($todayStats['ai_orders']) }} ({{ number_format($aiSharePct, 1) }}%)</td>
                                    <td class="py-2 px-3.5 text-center text-slate-600">{{ kpi_peso($yesterdayStats['ai_sales']) }} / {{ kpi_num($yesterdayStats['ai_orders']) }}</td>
                                    <td class="py-2 px-3.5 text-center text-slate-400">—</td>
                                </tr>
                                <tr class="bg-slate-50/80">
                                    <td class="py-2 px-3.5 font-medium">Broadcast & Sequence driven orders</td>
                                    <td class="py-2 px-3.5 text-center font-bold text-slate-900">{{ kpi_num($todayStats['bc_orders'] + $todayStats['bc_assisted'] + $todayStats['seq_orders'] + $todayStats['seq_assisted']) }}</td>
                                    <td class="py-2 px-3.5 text-center text-slate-600">{{ kpi_num($yesterdayStats['bc_orders'] + $yesterdayStats['bc_assisted'] + $yesterdayStats['seq_orders'] + $yesterdayStats['seq_assisted']) }}</td>
                                    <td class="py-2 px-3.5 text-center text-slate-400">—</td>
                                </tr>
                                <tr id="arow-roas" class="hidden">
                                    <td class="py-2 px-3.5 font-medium">Real POS ROAS / Ad spend</td>
                                    <td id="aout-roas-today" class="py-2 px-3.5 text-center font-bold text-emerald-700">—</td>
                                    <td id="aout-roas-yest" class="py-2 px-3.5 text-center text-slate-600">—</td>
                                    <td class="py-2 px-3.5 text-center">3.00x+</td>
                                </tr>
                                <tr>
                                    <td class="py-2 px-3.5 font-medium">Orders handled manually / missed by chatbot</td>
                                    <td id="aout-missed-kpi" class="py-2 px-3.5 text-center font-bold text-amber-800">{{ $todayStats['agent_orders'] }} / {{ kpi_peso($todayStats['agent_sales']) }}</td>
                                    <td class="py-2 px-3.5 text-center text-slate-600">{{ $yesterdayStats['agent_orders'] }} / {{ kpi_peso($yesterdayStats['agent_sales']) }}</td>
                                    <td class="py-2 px-3.5 text-center">0</td>
                                </tr>
                                <tr class="bg-slate-50/80">
                                    <td class="py-2 px-3.5 font-medium">Pages with manual / missed chatbot orders</td>
                                    <td id="aout-missed-pages-kpi" class="py-2 px-3.5 text-center font-bold text-slate-900">{{ $missedPagesCount }}</td>
                                    <td class="py-2 px-3.5 text-center text-slate-500">—</td>
                                    <td class="py-2 px-3.5 text-center">0</td>
                                </tr>
                                <tr>
                                    <td class="py-2 px-3.5 font-medium">Active pages on broadcast ({{ kpi_num($todayStats['bc_checked']) }} sends)</td>
                                    <td class="py-2 px-3.5 text-center font-bold text-slate-900">{{ $activeBcPagesCount }} active of {{ $totalPagesCount }}</td>
                                    <td class="py-2 px-3.5 text-center text-slate-500">—</td>
                                    <td class="py-2 px-3.5 text-center text-slate-400">—</td>
                                </tr>
                                <tr id="arow-creatives" class="hidden bg-slate-50/80">
                                    <td class="py-2 px-3.5 font-medium">Meta Ads system: creatives awaiting decision</td>
                                    <td id="aout-creatives-val" class="py-2 px-3.5 text-center font-bold text-slate-900"></td>
                                    <td class="py-2 px-3.5 text-center text-slate-400">—</td>
                                    <td class="py-2 px-3.5 text-center text-slate-400">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- 2. PRODUCT PERFORMANCE BREAKDOWN --}}
                <section class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-[13.5px] font-extrabold uppercase tracking-wide text-[#162B4D]">2. PERFORMANCE BY PRODUCT — AS OF {{ $endTimeShort }}</h3>
                        <span class="no-print text-[11px] text-muted">Optional: type Inquiries or Spend in any row to compute Conv% & ROAS</span>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full text-[11.5px] border-collapse num">
                            <thead>
                                <tr class="bg-[#162B4D] text-white text-[10px] uppercase tracking-wider">
                                    <th class="py-2 px-2.5 text-left font-bold">Product</th>
                                    <th class="py-2 px-2 text-right font-bold">Pages</th>
                                    <th class="py-2 px-2 text-right font-bold">Qty Sold</th>
                                    <th class="py-2 px-2.5 text-right font-bold">POS Sales</th>
                                    <th class="py-2 px-2 text-right font-bold">AOV</th>
                                    <th class="py-2 px-2 text-right font-bold">AI Closed</th>
                                    <th class="no-print py-2 px-2 text-right font-bold">Inq. (opt)</th>
                                    <th class="no-print py-2 px-2 text-right font-bold">Spend ₱ (opt)</th>
                                    <th class="py-2 px-2 text-right font-bold">Conv% / ROAS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($productRows as $pr)
                                    <tr data-prod-row data-prod-name="{{ $pr['product'] }}" data-prod-orders="{{ $pr['orders'] }}" data-prod-sales="{{ $pr['sales'] }}" class="even:bg-slate-50/70">
                                        <td class="py-1.5 px-2.5 font-semibold text-slate-800">{{ $pr['product'] }}</td>
                                        <td class="py-1.5 px-2 text-right text-slate-500">{{ $pr['pages_count'] }}</td>
                                        <td class="py-1.5 px-2 text-right font-bold text-slate-900">{{ kpi_num($pr['orders']) }}</td>
                                        <td class="py-1.5 px-2.5 text-right font-semibold text-emerald-700">{{ $pr['sales'] > 0 ? kpi_peso($pr['sales']) : '₱0' }}</td>
                                        <td class="py-1.5 px-2 text-right text-slate-600">{{ $pr['orders'] > 0 ? kpi_peso($pr['aov']) : '—' }}</td>
                                        <td class="py-1.5 px-2 text-right text-[#4B55F5] font-medium">{{ kpi_num($pr['ai_orders']) }}</td>
                                        <td class="no-print py-1 px-1.5 text-right">
                                            <input type="number" min="0" data-prod-inq placeholder="—" class="w-16 rounded border border-slate-200 px-1.5 py-0.5 text-right text-[11px] bg-white">
                                        </td>
                                        <td class="no-print py-1 px-1.5 text-right">
                                            <input type="number" min="0" step="0.01" data-prod-spend placeholder="—" class="w-20 rounded border border-slate-200 px-1.5 py-0.5 text-right text-[11px] bg-white">
                                        </td>
                                        <td data-prod-calc class="py-1.5 px-2 text-right text-slate-500">—</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300">
                                    <td class="py-2 px-2.5">GRAND TOTAL</td>
                                    <td class="py-2 px-2 text-right">{{ $totalPagesCount }}</td>
                                    <td class="py-2 px-2 text-right">{{ kpi_num($todayStats['orders']) }}</td>
                                    <td class="py-2 px-2.5 text-right text-emerald-700">{{ kpi_peso($todayStats['sales']) }}</td>
                                    <td class="py-2 px-2 text-right">{{ $todayStats['orders'] > 0 ? kpi_peso($aovToday) : '—' }}</td>
                                    <td class="py-2 px-2 text-right text-[#4B55F5]">{{ kpi_num($todayStats['ai_orders']) }}</td>
                                    <td class="no-print"></td>
                                    <td class="no-print"></td>
                                    <td id="aout-prod-grand-calc" class="py-2 px-2 text-right">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- 3. ACTIVITIES BY TEAM MEMBER & MISSED-ORDER RECOVERY --}}
                <section class="space-y-3">
                    <h3 class="text-[13.5px] font-extrabold uppercase tracking-wide text-[#162B4D]">3. ACTIVITIES BY TEAM MEMBER</h3>

                    <div class="space-y-2">
                        <h4 class="font-bold text-slate-900">A. <span id="aout-leader-heading">{{ auth()->user()->name }}</span> — Team Leader</h4>
                        <p id="aout-leader-sum-p" contenteditable="true" class="rounded-lg focus:bg-primary-soft/25 focus:outline-none px-1 -mx-1">
                            Consolidated automation & POS attribution across {{ $totalPagesCount }} active pages ({{ $activeBcPagesCount }} pages ran scheduled broadcasts totaling {{ kpi_num($todayStats['bc_delivered']) }} delivered sends). Monitored inbox handoffs and manual order recoveries.
                        </p>

                        <div id="aout-missed-wrap" class="{{ empty($missedByPage) ? 'hidden' : '' }} space-y-1">
                            <div class="text-xs font-semibold text-slate-700">Manual / Missed-by-Chatbot Orders Summary (Customer PII omitted per EOD privacy rule)</div>
                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="w-full text-xs border-collapse num">
                                    <thead>
                                        <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                            <th class="py-1.5 px-3 text-left w-10">#</th>
                                            <th class="py-1.5 px-3 text-left">Facebook Page</th>
                                            <th class="py-1.5 px-3 text-left">Page Owner / Team</th>
                                            <th class="py-1.5 px-3 text-right">Orders</th>
                                            <th class="py-1.5 px-3 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="aout-missed-tbody" class="divide-y divide-slate-100"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @foreach ($memberSections as $idx => $ms)
                        @php $letter = chr(66 + $idx); @endphp
                        <div class="space-y-1 pt-1">
                            <div class="flex items-center justify-between gap-2">
                                <h4 class="font-bold text-slate-900">{{ $letter }}. {{ $ms['name'] }} — Assigned Pages ({{ $ms['pages_count'] }})</h4>
                                @if ($ms['submitted_at'])
                                    <span class="no-print pill bg-emerald-50 text-good">EOD Submitted {{ kpi_time($ms['submitted_at'], 'g:i A') }}</span>
                                @endif
                            </div>
                            <div data-member-note="{{ $ms['id'] }}" contenteditable="true" class="rounded-lg focus:bg-primary-soft/25 focus:outline-none px-1 -mx-1 text-slate-700">
                                {{ $ms['submission']['summary'] ?? $ms['default_summary'] }}
                            </div>
                        </div>
                    @endforeach
                </section>

                {{-- 4. ISSUES & BLOCKERS --}}
                <section class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[13.5px] font-extrabold uppercase tracking-wide text-[#162B4D]">4. ISSUES & BLOCKERS</h3>
                        <button type="button" id="adm-add-issue-btn" class="no-print text-xs font-semibold text-primary hover:underline cursor-pointer">+ Add Issue Row</button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#162B4D] text-white text-[10.5px] uppercase tracking-wider">
                                    <th class="py-2 px-3 text-left font-bold">Issue</th>
                                    <th class="py-2 px-3 text-left font-bold w-32">Owner</th>
                                    <th class="py-2 px-3 text-left font-bold w-36">Status</th>
                                    <th class="no-print py-2 px-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody id="aout-issues-tbody" class="divide-y divide-slate-200"></tbody>
                        </table>
                    </div>
                </section>

                {{-- 5. DECISIONS NEEDED --}}
                <section class="space-y-1.5">
                    <h3 class="text-[13.5px] font-extrabold uppercase tracking-wide text-[#162B4D]">5. DECISIONS NEEDED</h3>
                    <table class="w-full text-xs border border-slate-200 border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                <th class="py-1.5 px-3 text-left">Decision</th>
                                <th class="py-1.5 px-3 text-left w-32">By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td id="aout-decision" class="py-2 px-3">None / Wala.</td>
                                <td id="aout-decision-by" class="py-2 px-3 font-semibold">—</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                {{-- 6. TOP 3 PRIORITIES — NEXT WORKING DAY --}}
                <section class="space-y-1.5">
                    <h3 class="text-[13.5px] font-extrabold uppercase tracking-wide text-[#162B4D]">6. TOP 3 PRIORITIES — NEXT WORKING DAY</h3>
                    <table class="w-full text-xs border border-slate-200 border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                <th class="py-1.5 px-3 text-left w-10">#</th>
                                <th class="py-1.5 px-3 text-left">Priority</th>
                                <th class="py-1.5 px-3 text-left w-36">Owner</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr>
                                <td class="py-1.5 px-3 font-bold">1</td>
                                <td id="aout-prio-1" class="py-1.5 px-3"></td>
                                <td id="aout-prio-1-own" class="py-1.5 px-3 font-medium">Team</td>
                            </tr>
                            <tr>
                                <td class="py-1.5 px-3 font-bold">2</td>
                                <td id="aout-prio-2" class="py-1.5 px-3"></td>
                                <td id="aout-prio-2-own" class="py-1.5 px-3 font-medium">Team Leader</td>
                            </tr>
                            <tr>
                                <td class="py-1.5 px-3 font-bold">3</td>
                                <td id="aout-prio-3" class="py-1.5 px-3"></td>
                                <td id="aout-prio-3-own" class="py-1.5 px-3 font-medium">Automation Team</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                {{-- Signature Footer --}}
                <footer class="pt-4 border-t border-slate-200 flex flex-wrap items-end justify-between gap-4">
                    <div class="space-y-0.5">
                        <div class="text-xs text-slate-500">Prepared By:</div>
                        <div id="aout-sig-name" class="font-bold text-slate-900">{{ auth()->user()->name }}</div>
                        <div id="aout-sig-pos" class="text-xs text-slate-700">Automation Team Leader</div>
                        <div class="text-xs text-slate-500"><span id="aout-sig-dept">Automation - Marketing</span> · K Gold Beauty Essentials Corporation</div>
                    </div>
                    <div class="text-[11px] italic text-slate-400">
                        Generated {{ $generatedAtLabel }} · Coverage {{ $windowLabel }}
                    </div>
                </footer>
            </article>
        </div>
    </div>
@endsection
