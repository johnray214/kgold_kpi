@extends('layouts.app', ['title' => 'EOD Report · KGOLD AUTOMATION TEAM KPI'])

@php
    $isAdmin = auth()->user()?->isAdmin();
    $pCount = $assignedPages->count();
    $detectedManualOrders = $todayStats['agent_orders'];
    $defaultSupportNeeded = $todayStats['bc_failures'] > 0
        ? "Technical check / monitoring on {$todayStats['bc_failures']} broadcast send issue(s)."
        : 'No critical technical blockers across assigned pages.';
    $pageNamesPreview = $assignedPages->take(5)->pluck('name')->implode(', ').($pCount > 5 ? ' +' . ($pCount - 5) . ' more' : '');
@endphp

@section('content')
    {{-- Top Page Action Header --}}
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-bold tracking-tight text-ink">Member End-of-Day (EOD) Generator</h1>
                <span class="pill bg-primary-soft text-primary num">{{ $windowLabel }}</span>
            </div>
            <p class="text-xs text-muted mt-0.5">
                Scoped strictly to your assigned pages · covers <b>12:00 AM up to the exact moment you click Generate</b>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('inbox') }}" class="btn-ghost !text-xs">
                <x-icon name="inbox" class="size-3.5 text-bad"/>
                <span>Unanswered Inbox ({{ kpi_num($inboxCounts['unanswered'] ?? 0) }})</span>
            </a>

            <button type="button" id="eod-reset-draft" class="btn-ghost !text-xs text-muted hover:text-bad" title="Clear saved optional inputs for this date">
                <span>Reset Draft</span>
            </button>

            <button type="button" id="eod-copy-btn" class="btn-ghost">
                <x-icon name="copy" class="size-3.5 text-primary"/>
                <span id="eod-copy-label">Copy for Chat</span>
            </button>

            <form id="eod-pdf-form" method="POST" action="{{ route('eod.pdf') }}" data-download-form="1">
                @csrf
                <input type="hidden" name="date" value="{{ $reportDate }}">
                <input type="hidden" name="member_id" value="{{ $targetMember->id }}">
                <input type="hidden" name="frozen_end" value="{{ $frozenEndIso }}">
                <input type="hidden" name="prep_by" id="pdf-prep-by" value="{{ $targetMember->name }}">
                <input type="hidden" name="role_title" id="pdf-role-title" value="Automation Team Member">
                <input type="hidden" name="coverage" id="pdf-coverage" value="{{ $defaultCoverage }}">
                <input type="hidden" name="bc_target" id="pdf-bc-target" value="">
                <input type="hidden" name="macro_today" id="pdf-macro-today" value="">
                <input type="hidden" name="macro_target" id="pdf-macro-target" value="">
                <input type="hidden" name="inq_today" id="pdf-inq-today" value="">
                <input type="hidden" name="inq_yest" id="pdf-inq-yest" value="">
                <input type="hidden" name="inq_target" id="pdf-inq-target" value="">
                <input type="hidden" name="pending_concerns" id="pdf-pending" value="">
                <input type="hidden" name="support_needed" id="pdf-support" value="{{ $defaultSupportNeeded }}">
                <input type="hidden" name="ceo_decision" id="pdf-ceo" value="Wala.">
                <input type="hidden" name="prio_1" id="pdf-prio-1" value="{{ $todayStats['bc_failures'] > 0 ? 'Resolve '.$todayStats['bc_failures'].' broadcast delivery issue(s) and verify scheduled sends.' : 'Verify scheduled Utility & Within-window broadcasts across all assigned pages.' }}">
                <input type="hidden" name="prio_2" id="pdf-prio-2" value="Follow up on pending customer inquiries and check sequence drop-off steps.">
                <input type="hidden" name="prio_3" id="pdf-prio-3" value="Monitor {{ $pCount }} assigned page(s) and audit Botcake AI & macro response accuracy.">
                <input type="hidden" name="include_pages_table" id="pdf-include-pages" value="1">
                <input type="hidden" name="custom_rows_json" id="pdf-custom-rows" value="[]">
                <input type="hidden" name="bullets_json" id="pdf-bullets" value="{}">

                <button type="submit" data-loading-text="Building PDF…" class="btn-primary">
                    <x-icon name="download" class="size-3.5"/>
                    <span>Download PDF</span>
                </button>
            </form>
        </div>
    </div>

    @if ($isAdmin)
        <div class="no-print flex flex-wrap items-center justify-between gap-3 card px-4 py-2.5 !bg-primary-soft/45 !border-indigo-200">
            <div class="inline-flex items-center rounded-xl bg-white p-1 border border-line text-xs">
                <a href="{{ route('eod', ['date' => $reportDate]) }}" class="px-3 py-1.5 rounded-lg text-muted hover:text-ink font-medium cursor-pointer">
                    Team Leader Consolidated EOD
                </a>
                <a href="{{ route('eod', ['mode' => 'member', 'date' => $reportDate, 'member_id' => $targetMember->id]) }}" class="px-3 py-1.5 rounded-lg bg-primary text-white font-semibold cursor-pointer">
                    Member EOD Preview ({{ $targetMember->name }})
                </a>
            </div>
            <span class="text-xs text-muted">Scoped to <b>{{ $targetMember->name }}</b>’s {{ $pCount }} assigned page(s)</span>
        </div>
    @else
        <div class="no-print card px-4 py-2.5 !bg-emerald-50/60 !border-emerald-200 flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="text-emerald-950">
                @if (!empty($savedSubmission))
                    <b>Submitted to Team Leader</b> ({{ kpi_time($savedSubmission->submitted_at, 'g:i A') }}) — you can update & re-submit anytime today.
                @else
                    Done filling your optional notes? Click <b>Submit to Team EOD</b> so your section automatically appears in the Team Leader’s consolidated report.
                @endif
            </span>
            <form id="eod-submit-form" method="POST" action="{{ route('eod.submit') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $reportDate }}">
                <input type="hidden" name="payload_json" id="submit-eod-payload" value="{}">
                <button type="submit" data-loading-text="Saving…" class="btn-primary !bg-good hover:!bg-emerald-600 !min-h-[32px]">
                    <x-icon name="check" class="size-3.5"/> Submit to Team EOD
                </button>
            </form>
        </div>
    @endif

    <div class="grid lg:grid-cols-12 gap-5 items-start">
        {{-- ================= LEFT PANEL (5/12): Generator & Optional Inputs ================= --}}
        <aside class="no-print lg:col-span-5 space-y-4">
            {{-- 1. Generate Window Card --}}
            <section class="card overflow-hidden">
                <div class="card-head !px-5 !py-3.5">
                    <div>
                        <h2 class="card-title !text-sm"><x-icon name="clock" class="size-4 text-primary"/> 1. Generate Cutoff Window</h2>
                        <p class="card-sub !text-[11px]">Captures 12:00 AM → exact click time</p>
                    </div>
                </div>
                <form method="GET" action="{{ route('eod') }}" class="p-5 space-y-3">
                    <input type="hidden" name="gen" value="{{ now()->timestamp }}">

                    @if ($isAdmin && $members->isNotEmpty())
                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] font-semibold text-muted uppercase">Preview Member</span>
                            <select name="member_id" class="field cursor-pointer">
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}" @selected($m->id === $targetMember->id)>{{ $m->name }} ({{ $m->email }})</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">EOD Date</span>
                        <input type="date" name="date" value="{{ $reportDate }}" class="field num cursor-pointer">
                    </label>

                    <button type="submit" data-loading-text="Generating EOD…" class="btn-primary w-full !min-h-[40px]">
                        <x-icon name="refresh" class="size-3.5"/>
                        <span>Generate EOD (12:00 AM → Now)</span>
                    </button>

                    <div class="text-[11px] text-muted text-center num">
                        Last generated: <strong class="text-ink">{{ $generatedAtLabel }}</strong>
                    </div>
                </form>
            </section>

            {{-- 2. Optional Macro / Broadcast / Manual Sale Entries by Page --}}
            <section class="card overflow-hidden">
                <div class="card-head !px-5 !py-3.5">
                    <div>
                        <h2 class="card-title !text-sm"><x-icon name="plus" class="size-4 text-primary"/> 2. Optional Page / Macro / Broadcast Sales</h2>
                        <p class="card-sub !text-[11px]">Log manual macro sales, missed-by-bot recoveries, or promo broadcast sales per page</p>
                    </div>
                    <button type="button" id="add-custom-eod-row" class="btn-ghost !min-h-[30px] !px-2.5 !text-xs text-primary border-primary/30">
                        <x-icon name="plus" class="size-3.5"/> Add Entry
                    </button>
                </div>

                <div class="p-5 space-y-2.5">
                    <div id="custom-eod-rows-empty" class="text-xs text-muted text-center py-2">
                        No extra manual entries added (optional — click <b>+ Add Entry</b> to log macro/broadcast/manual sales by page).
                    </div>
                    <div id="custom-eod-rows-list" class="space-y-2.5"></div>

                    <datalist id="eod-assigned-pages-list">
                        @foreach ($assignedPages as $ap)
                            <option value="{{ $ap->name }}"></option>
                        @endforeach
                    </datalist>
                    <datalist id="eod-sale-types-list">
                        <option value="Macro Sale"></option>
                        <option value="Broadcast Sale"></option>
                        <option value="Manual / Missed by Bot"></option>
                        <option value="Retention / Follow-up"></option>
                    </datalist>

                    <template id="custom-eod-row-tpl">
                        <div data-custom-row class="p-3 rounded-xl border border-line bg-slate-50/70 space-y-2 relative">
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-7">
                                    <input type="text" list="eod-assigned-pages-list" data-cr-page placeholder="Type page name or pick…" class="field !min-h-[30px] !text-xs w-full bg-white">
                                </div>
                                <div class="col-span-4">
                                    <input type="text" list="eod-sale-types-list" data-cr-type value="Macro Sale" placeholder="Type (e.g. Macro Sale)" class="field !min-h-[30px] !text-xs w-full bg-white">
                                </div>
                                <div class="col-span-1 flex justify-end">
                                    <button type="button" data-cr-remove aria-label="Remove row" class="p-1 rounded-lg text-muted hover:text-bad hover:bg-red-50 cursor-pointer">
                                        <x-icon name="x" class="size-3.5"/>
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-12 gap-2">
                                <div class="col-span-3">
                                    <input type="number" min="0" data-cr-orders placeholder="Orders" class="field !min-h-[30px] !text-xs w-full bg-white num">
                                </div>
                                <div class="col-span-4">
                                    <input type="number" min="0" step="1" data-cr-sales placeholder="₱ Sales" class="field !min-h-[30px] !text-xs w-full bg-white num">
                                </div>
                                <div class="col-span-5">
                                    <input type="text" data-cr-note placeholder="Note (optional)" class="field !min-h-[30px] !text-xs w-full bg-white">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            {{-- 3. Optional Manual Metrics & Report Notes --}}
            <section class="card overflow-hidden">
                <div class="card-head !px-5 !py-3.5">
                    <div>
                        <h2 class="card-title !text-sm"><x-icon name="edit" class="size-4 text-primary"/> 3. Optional Inputs & Notes</h2>
                        <p class="card-sub !text-[11px]">Leave any optional box blank to hide it cleanly from the EOD</p>
                    </div>
                </div>

                <div class="p-5 space-y-3.5 text-xs">
                    <div class="grid grid-cols-2 gap-2.5">
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold text-muted uppercase">Prepared By</span>
                            <input type="text" id="in-prep-by" value="{{ $targetMember->name }}" class="field !min-h-[32px]">
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold text-muted uppercase">Role Title</span>
                            <input type="text" id="in-role-title" value="Automation Team Member" class="field !min-h-[32px]">
                        </label>
                    </div>

                    <label class="flex flex-col gap-1">
                        <span class="text-[10px] font-semibold text-muted uppercase">Assigned Coverage</span>
                        <input type="text" id="in-coverage" value="{{ $defaultCoverage }}" class="field !min-h-[32px]">
                    </label>

                    <div class="pt-2 border-t border-line space-y-2.5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Optional QA & Manual Inbox Counters</div>

                        <div class="grid grid-cols-3 gap-2">
                            <label class="flex flex-col gap-1">
                                <span class="text-[10px] text-muted truncate">BC Target (opt)</span>
                                <input type="number" min="0" id="in-bc-target" value="" placeholder="Optional" class="field !min-h-[32px] num">
                            </label>
                            <label class="flex flex-col gap-1">
                                <span class="text-[10px] text-muted truncate">Bot/Macro Fixed</span>
                                <input type="number" min="0" id="in-macro-today" value="" placeholder="Optional" class="field !min-h-[32px] num">
                            </label>
                            <label class="flex flex-col gap-1">
                                <span class="text-[10px] text-muted truncate">Macro Target</span>
                                <input type="number" min="0" id="in-macro-target" value="" placeholder="Optional" class="field !min-h-[32px] num">
                            </label>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <label class="flex flex-col gap-1">
                                <span class="text-[10px] text-muted truncate flex items-center justify-between gap-1">
                                    <span>Inquiries Assisted</span>
                                    @php $liveAssisted = max((int) ($inboxCounts['human_assisted'] ?? 0), (int) $detectedManualOrders); @endphp
                                    @if ($liveAssisted > 0)
                                        <button type="button" data-fill-val="{{ $liveAssisted }}" data-fill-target="in-inq-today" class="text-[9px] text-primary hover:underline cursor-pointer shrink-0">({{ $liveAssisted }} live)</button>
                                    @endif
                                </span>
                                <input type="number" min="0" id="in-inq-today" value="" placeholder="Optional" class="field !min-h-[32px] num">
                            </label>
                            <label class="flex flex-col gap-1">
                                <span class="text-[10px] text-muted truncate">Inquiries Yest.</span>
                                <input type="number" min="0" id="in-inq-yest" value="" placeholder="Optional" class="field !min-h-[32px] num">
                            </label>
                            <label class="flex flex-col gap-1">
                                <span class="text-[10px] text-muted truncate">Inquiries Target</span>
                                <input type="number" min="0" id="in-inq-target" value="" placeholder="Optional" class="field !min-h-[32px] num">
                            </label>
                        </div>

                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted flex items-center justify-between gap-2">
                                <span>Pending Customer Concerns <span class="text-slate-400">(optional)</span></span>
                                <span class="inline-flex items-center gap-2">
                                    @if (($inboxCounts['unanswered'] ?? 0) > 0)
                                        <button type="button" data-fill-val="{{ $inboxCounts['unanswered'] }}" data-fill-target="in-pending" class="text-[10px] font-semibold text-bad hover:underline cursor-pointer">
                                            Use {{ $inboxCounts['unanswered'] }} unanswered
                                        </button>
                                    @endif
                                    <a href="{{ route('inbox') }}" class="text-[10px] font-semibold text-primary hover:underline">See names in Inbox ↗</a>
                                </span>
                            </span>
                            <input type="number" min="0" id="in-pending" value="" placeholder="Leave blank if none" class="field !min-h-[32px] num">
                        </label>
                    </div>

                    <div class="pt-2 border-t border-line space-y-2.5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Decisions & Tomorrow’s Top 3</div>

                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">Technical / Support Needed <span class="text-slate-400">(optional)</span></span>
                            <input type="text" id="in-support" value="{{ $defaultSupportNeeded }}" class="field !min-h-[32px]">
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">CEO Decision</span>
                            <input type="text" id="in-ceo" value="Wala." class="field !min-h-[32px]">
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">Priority #1 Tomorrow</span>
                            <input type="text" id="in-prio-1" value="{{ $todayStats['bc_failures'] > 0 ? 'Resolve '.$todayStats['bc_failures'].' broadcast delivery issue(s) and verify scheduled sends.' : 'Verify scheduled Utility & Within-window broadcasts across all assigned pages.' }}" class="field !min-h-[32px]">
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">Priority #2 Tomorrow</span>
                            <input type="text" id="in-prio-2" value="Follow up on pending customer inquiries and check sequence drop-off steps." class="field !min-h-[32px]">
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] text-muted">Priority #3 Tomorrow</span>
                            <input type="text" id="in-prio-3" value="Monitor {{ $pCount }} assigned page(s) and audit Botcake AI & macro response accuracy." class="field !min-h-[32px]">
                        </label>

                        <label class="inline-flex items-center gap-2 pt-1 cursor-pointer select-none text-muted">
                            <input type="checkbox" id="in-show-pages-tbl" checked class="rounded border-slate-300 text-primary">
                            <span>Include Per-Page Breakdown table in PDF</span>
                        </label>
                    </div>
                </div>
            </section>
        </aside>

        {{-- ================= RIGHT PANEL (7/12): Live Executive EOD Preview ================= --}}
        <div class="lg:col-span-7">
            <article id="eod-paper" data-report-date="{{ $reportDate }}" data-member-id="{{ $targetMember->id }}" class="bg-white rounded-2xl border border-line shadow-[0_6px_24px_rgba(15,23,42,0.06)] p-7 sm:p-9 space-y-6 text-[13px] leading-relaxed text-slate-800">

                {{-- Report Document Header --}}
                <div class="border-b-2 border-[#162B4D] pb-4 flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2.5">
                            <x-brand-logo size="size-8" iconSize="18"/>
                            <h2 class="text-xl sm:text-[21px] font-extrabold tracking-tight text-[#162B4D]">
                                AUTOMATION TEAM - EOD REPORT
                            </h2>
                        </div>
                        <div class="pt-1 space-y-0.5 text-[13px]">
                            <div><strong class="text-slate-900">Prepared By:</strong> <span id="out-prep-by">{{ $targetMember->name }}</span></div>
                            <div>
                                <strong class="text-slate-900">Date:</strong> <span>{{ $reportDateFormatted }}</span>
                                <span class="mx-1.5 text-slate-300">|</span>
                                <strong class="text-slate-900">Time Covered:</strong>
                                <span class="font-semibold text-[#162B4D] num">{{ $windowLabel }}</span>
                            </div>
                            <div><strong class="text-slate-900">Assigned Coverage:</strong> <span id="out-coverage">{{ $defaultCoverage }}</span></div>
                        </div>
                    </div>

                    @if ($pCount > 0)
                        <div class="no-print flex flex-col items-end gap-1.5">
                            <div class="flex -space-x-1.5">
                                @foreach ($assignedPages->take(6) as $ap)
                                    <x-page-avatar :id="$ap->fb_page_id" :name="$ap->name" size="size-7 !rounded-full !ring-2 !ring-white !text-[8px]"/>
                                @endforeach
                            </div>
                            <span class="text-[11px] font-semibold text-muted">{{ $pCount }} Assigned Page{{ $pCount === 1 ? '' : 's' }}</span>
                        </div>
                    @endif
                </div>

                {{-- 1. KEY NUMBERS --}}
                <section class="space-y-2.5">
                    <h3 class="text-[14px] font-extrabold uppercase tracking-wide text-[#162B4D]">1. KEY NUMBERS</h3>
                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full text-[13px] border-collapse">
                            <thead>
                                <tr class="bg-[#162B4D] text-white text-xs uppercase tracking-wider">
                                    <th class="py-2.5 px-4 text-left font-bold">KPI</th>
                                    <th class="py-2.5 px-4 text-center font-bold">Today ({{ $isToday ? '12AM–Now' : 'Full Day' }})</th>
                                    <th class="py-2.5 px-4 text-center font-bold">Yesterday</th>
                                    <th class="py-2.5 px-4 text-center font-bold">Target</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 num">
                                <tr class="bg-white">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">Pages Monitored</td>
                                    <td class="py-2.5 px-4 text-center font-bold text-slate-900">{{ $pCount }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-600">{{ $pCount }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-600">{{ $pCount }}</td>
                                </tr>
                                <tr class="bg-slate-50/80">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">Broadcasts Checked</td>
                                    <td class="py-2.5 px-4 text-center font-bold text-slate-900">{{ kpi_num($todayStats['bc_checked']) }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-600">{{ kpi_num($yesterdayStats['bc_checked']) }}</td>
                                    <td id="out-bc-target-1" class="py-2.5 px-4 text-center text-slate-400">—</td>
                                </tr>
                                <tr class="bg-white">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">Successful Broadcasts</td>
                                    <td class="py-2.5 px-4 text-center font-bold text-emerald-700">{{ kpi_num($todayStats['bc_successful']) }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-600">{{ kpi_num($yesterdayStats['bc_successful']) }}</td>
                                    <td id="out-bc-target-2" class="py-2.5 px-4 text-center text-slate-400">—</td>
                                </tr>
                                <tr class="bg-slate-50/80">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">Verified POS Orders (Assigned Pages)</td>
                                    <td class="py-2.5 px-4 text-center font-bold text-[#4B55F5]">{{ kpi_num($todayStats['orders']) }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-600">{{ kpi_num($yesterdayStats['orders']) }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-400">—</td>
                                </tr>
                                <tr class="bg-white">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">Botcake AI Orders Closed</td>
                                    <td class="py-2.5 px-4 text-center font-bold text-slate-900">{{ kpi_num($todayStats['ai_orders']) }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-600">{{ kpi_num($yesterdayStats['ai_orders']) }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-400">—</td>
                                </tr>
                                <tr class="bg-slate-50/80">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">Phone Leads Captured (Broadcasts)</td>
                                    <td class="py-2.5 px-4 text-center font-bold text-slate-900">{{ kpi_num($todayStats['bc_phones']) }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-600">{{ kpi_num($yesterdayStats['bc_phones']) }}</td>
                                    <td class="py-2.5 px-4 text-center text-slate-400">—</td>
                                </tr>
                                {{-- Optional Manual Rows (hidden unless user enters a value) --}}
                                <tr id="row-macro" class="bg-white hidden">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">Bot/Macro Issues Resolved</td>
                                    <td id="out-macro-today" class="py-2.5 px-4 text-center font-bold text-slate-900">0</td>
                                    <td class="py-2.5 px-4 text-center text-slate-400">—</td>
                                    <td id="out-macro-target" class="py-2.5 px-4 text-center text-slate-600">—</td>
                                </tr>
                                <tr id="row-inq" class="bg-slate-50/80 hidden">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">Unanswered Inquiries Assisted</td>
                                    <td id="out-inq-today" class="py-2.5 px-4 text-center font-bold text-slate-900">0</td>
                                    <td id="out-inq-yest" class="py-2.5 px-4 text-center text-slate-600">—</td>
                                    <td id="out-inq-target" class="py-2.5 px-4 text-center text-slate-600">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- 2. SALES / OPERATIONAL IMPACT --}}
                <section class="space-y-2.5">
                    <h3 class="text-[14px] font-extrabold uppercase tracking-wide text-[#162B4D]">2. SALES / OPERATIONAL IMPACT</h3>
                    <ul class="list-disc pl-5 space-y-1 text-[13px]">
                        <li>
                            <strong>Assigned Page Orders ({{ $windowLabel }}):</strong>
                            <span class="num font-semibold text-slate-900">{{ kpi_num($todayStats['orders']) }} valid POS orders</span>
                            (Botcake AI: {{ kpi_num($todayStats['ai_orders']) }} · Broadcast: {{ kpi_num($todayStats['bc_orders']) }}{{ $todayStats['bc_assisted'] > 0 ? ' + '.$todayStats['bc_assisted'].' AI-closed' : '' }} · Sequence: {{ kpi_num($todayStats['seq_orders']) }}{{ $todayStats['seq_assisted'] > 0 ? ' + '.$todayStats['seq_assisted'].' AI-closed' : '' }} · Agent/Organic: {{ kpi_num($todayStats['agent_orders'] + $todayStats['organic_orders']) }})
                        </li>
                        <li>
                            <strong>Financial Impact (Verified POS Sales):</strong>
                            <span class="num font-bold text-emerald-700">{{ kpi_peso($todayStats['sales']) }}</span>
                            <span class="text-slate-500 num">(AI: {{ kpi_peso($todayStats['ai_sales']) }} · Yesterday full day: {{ kpi_peso($yesterdayStats['sales']) }})</span>
                        </li>
                        <li>
                            <strong>Broadcast Delivery & Failures:</strong>
                            <span class="num font-semibold">{{ $todayStats['bc_failures'] }}</span> failed broadcast run(s) ·
                            <span class="num">{{ kpi_num($todayStats['bc_delivered']) }} / {{ kpi_num($todayStats['bc_targeted']) }}</span> delivered ({{ number_format($todayStats['deliv_rate'], 1) }}%) ·
                            <span class="num">{{ kpi_num($todayStats['bc_not_delivered']) }}</span> undelivered (Blocked #551: {{ kpi_num($todayStats['bc_blocked']) }}, API Limit #613: {{ kpi_num($todayStats['bc_api_limit']) }})
                        </li>
                        <li id="li-inq-impact" class="hidden"><strong>Customer Inquiries Assisted:</strong> <span id="out-inq-impact" class="num font-semibold"></span></li>
                        <li id="li-pending-impact" class="hidden"><strong>Pending Customer Concerns:</strong> <span id="out-pending-impact" class="num font-semibold"></span></li>
                    </ul>

                    {{-- Live Optional Custom Macro / Broadcast / Manual Sales Table --}}
                    <div id="out-custom-sales-wrap" class="hidden pt-2 space-y-1.5">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Additional Macro / Broadcast / Manual Sales Logged</h4>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-xs border-collapse num">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                        <th class="py-2 px-3 text-left font-semibold">Facebook Page</th>
                                        <th class="py-2 px-3 text-left font-semibold">Type / Source</th>
                                        <th class="py-2 px-3 text-right font-semibold">Orders</th>
                                        <th class="py-2 px-3 text-right font-semibold">Sales</th>
                                        <th class="py-2 px-3 text-left font-semibold">Note</th>
                                    </tr>
                                </thead>
                                <tbody id="out-custom-sales-tbody" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                    </div>
                </section>

                {{-- Optional Assigned Pages Breakdown Table --}}
                @if ($assignedBreakdown->isNotEmpty())
                    <section id="out-pages-table-wrap" class="space-y-2">
                        <h3 class="text-[12px] font-bold uppercase tracking-wider text-slate-500">Assigned Pages Breakdown ({{ $windowLabel }})</h3>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-xs border-collapse num">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                        <th class="py-2 px-3 text-left font-semibold">Assigned Page</th>
                                        <th class="py-2 px-3 text-right font-semibold">Broadcasts</th>
                                        <th class="py-2 px-3 text-right font-semibold">Phone Leads</th>
                                        <th class="py-2 px-3 text-right font-semibold">AI Orders</th>
                                        <th class="py-2 px-3 text-right font-semibold">POS Orders</th>
                                        <th class="py-2 px-3 text-right font-semibold">POS Sales</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($assignedBreakdown as $ab)
                                        <tr>
                                            <td class="py-1.5 px-3 font-medium text-slate-800">{{ $ab['name'] }}</td>
                                            <td class="py-1.5 px-3 text-right text-slate-600">{{ kpi_num($ab['bc_count']) }}</td>
                                            <td class="py-1.5 px-3 text-right text-slate-600">{{ kpi_num($ab['bc_phones']) }}</td>
                                            <td class="py-1.5 px-3 text-right text-slate-600">{{ kpi_num($ab['ai_orders']) }}</td>
                                            <td class="py-1.5 px-3 text-right font-bold text-slate-900">{{ kpi_num($ab['orders']) }}</td>
                                            <td class="py-1.5 px-3 text-right font-semibold text-emerald-700">{{ $ab['sales'] > 0 ? kpi_peso($ab['sales']) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                {{-- 3. ISSUES, BLOCKERS & ACTIVITIES COMPLETED --}}
                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[14px] font-extrabold uppercase tracking-wide text-[#162B4D]">3. ISSUES, BLOCKERS & ACTIVITIES COMPLETED</h3>
                        <span class="no-print text-[11px] text-muted italic">Click any bullet to edit text (included in PDF!)</span>
                    </div>

                    <div class="space-y-2.5">
                        <div>
                            <h4 class="font-bold text-slate-900">A. Broadcast Monitoring</h4>
                            <ul data-eod-sec="a" contenteditable="true" class="list-disc pl-5 space-y-0.5 rounded-lg focus:bg-primary-soft/25 focus:outline-none px-1 -mx-1">
                                <li>Monitored Utility (Outside 24h) and Within-window broadcasts across {{ $pCount }} assigned page(s) ({{ $windowLabel }}).</li>
                                <li>Checked {{ kpi_num($todayStats['bc_checked']) }} broadcast send(s) totaling {{ kpi_num($todayStats['bc_delivered']) }} delivered messages ({{ number_format($todayStats['deliv_rate'], 1) }}% delivery rate) and {{ kpi_num($todayStats['bc_phones']) }} phone leads.</li>
                                <li>{{ $todayStats['bc_failures'] > 0 ? "Identified {$todayStats['bc_failures']} broadcast send issue(s) for review." : '0 broadcast run failures recorded in the current cutoff window.' }}</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-bold text-slate-900">B. Page & Inbox Monitoring</h4>
                            <ul data-eod-sec="b" contenteditable="true" class="list-disc pl-5 space-y-0.5 rounded-lg focus:bg-primary-soft/25 focus:outline-none px-1 -mx-1">
                                <li>Monitored {{ $pCount }} assigned page(s) ({{ $pageNamesPreview }}) and checked customer interactions.</li>
                                <li>Tracked {{ kpi_num($todayStats['orders']) }} verified POS order(s) ({{ kpi_peso($todayStats['sales']) }}) across assigned pages.</li>
                                <li id="li-inq-bullet" class="hidden"></li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-bold text-slate-900">C. Bot & Macro Monitoring</h4>
                            <ul data-eod-sec="c" contenteditable="true" class="list-disc pl-5 space-y-0.5 rounded-lg focus:bg-primary-soft/25 focus:outline-none px-1 -mx-1">
                                <li>Reviewed Botcake AI ({{ kpi_num($todayStats['ai_orders']) }} orders closed directly by AI) and macro responses for accuracy.</li>
                                <li id="li-macro-bullet" class="hidden"></li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-bold text-slate-900">D. Automation Development</h4>
                            <ul data-eod-sec="d" contenteditable="true" class="list-disc pl-5 space-y-0.5 rounded-lg focus:bg-primary-soft/25 focus:outline-none px-1 -mx-1">
                                <li>Reviewed {{ kpi_num($activeSequences) }} active automation sequence(s) ({{ kpi_num($todayStats['seq_orders'] + $todayStats['seq_assisted']) }} sequence-driven orders) and customer response flows.</li>
                                <li>Checked step drop-off points and opportunities for improving welcome messages and follow-up sequences.</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-bold text-slate-900">E. Team Coordination</h4>
                            <ul data-eod-sec="e" contenteditable="true" class="list-disc pl-5 space-y-0.5 rounded-lg focus:bg-primary-soft/25 focus:outline-none px-1 -mx-1">
                                <li>Reported unresolved concerns to the appropriate team members and coordinated automation-related updates.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                {{-- 4. DECISIONS / SUPPORT NEEDED --}}
                <section class="space-y-1.5">
                    <h3 class="text-[14px] font-extrabold uppercase tracking-wide text-[#162B4D]">4. DECISIONS / SUPPORT NEEDED</h3>
                    <ul class="list-disc pl-5 space-y-0.5">
                        <li id="out-support">{{ $defaultSupportNeeded }}</li>
                        <li><strong>CEO Decision:</strong> <span id="out-ceo">Wala.</span></li>
                    </ul>
                </section>

                {{-- 5. TOP 3 PRIORITIES TOMORROW --}}
                <section class="space-y-1.5">
                    <h3 class="text-[14px] font-extrabold uppercase tracking-wide text-[#162B4D]">5. TOP 3 PRIORITIES TOMORROW</h3>
                    <ol class="list-decimal pl-5 space-y-0.5">
                        <li id="out-prio-1">{{ $todayStats['bc_failures'] > 0 ? 'Resolve '.$todayStats['bc_failures'].' broadcast delivery issue(s) and verify scheduled sends.' : 'Verify scheduled Utility & Within-window broadcasts across all assigned pages.' }}</li>
                        <li id="out-prio-2">Follow up on pending customer inquiries and check sequence drop-off steps.</li>
                        <li id="out-prio-3">Monitor {{ $pCount }} assigned page(s) and audit Botcake AI & macro response accuracy.</li>
                    </ol>
                </section>

                {{-- Signature Footer --}}
                <footer class="pt-4 border-t border-slate-200 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div class="font-bold text-slate-900">Prepared by: <span id="out-sig-name">{{ $targetMember->name }}</span></div>
                        <div id="out-sig-role" class="text-slate-600">Automation Team Member</div>
                    </div>
                    <div class="text-[11px] italic text-slate-400">
                        Generated {{ $generatedAtLabel }} · Coverage {{ $windowLabel }} ({{ $pCount }} assigned pages)
                    </div>
                </footer>
            </article>
        </div>
    </div>
@endsection
