<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\EodSubmission;
use App\Models\InboxConversation;
use App\Models\PosOrder;
use App\Models\Sequence;
use App\Models\SyncRun;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EodController extends Controller
{
    public function show(Request $request)
    {
        /** @var User $me */
        $me = $request->user();
        $syncMeta = Cache::remember('kpi:sync_meta', 180, fn () => [
            'lastSync' => SyncRun::where('status', 'ok')->latest('finished_at')->first(),
            'syncRunning' => SyncRun::where('status', 'running')->where('started_at', '>', now()->subMinutes(15))->exists(),
            'lastError' => null,
        ]);

        if ($me->isAdmin() && $request->input('mode') !== 'member') {
            return view('eod-admin', $syncMeta + $this->buildAdminEodData($request));
        }

        return view('eod', $syncMeta + $this->buildMemberEodData($request));
    }

    public function submitMemberEod(Request $request)
    {
        /** @var User $me */
        $me = $request->user();
        $reportDate = $request->input('date', now(config('kpi.timezone', 'Asia/Manila'))->toDateString());
        $payload = json_decode((string) $request->input('payload_json', '{}'), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        if (Schema::hasTable('eod_submissions')) {
            EodSubmission::updateOrCreate(
                ['user_id' => $me->id, 'report_date' => $reportDate],
                ['payload' => $payload, 'submitted_at' => now()]
            );
        }

        return back()->with('status', 'Your EOD report has been saved and linked to the Team Leader Consolidated EOD!');
    }

    public function downloadPdf(Request $request)
    {
        $data = $this->buildMemberEodData($request, $request->input('frozen_end'));

        $customRows = json_decode((string) $request->input('custom_rows_json', '[]'), true);
        if (! is_array($customRows)) {
            $customRows = [];
        }
        $customRows = array_values(array_filter($customRows, fn ($r) => ! empty(trim((string) ($r['page'] ?? ''))) || (float) ($r['orders'] ?? 0) > 0 || (float) ($r['sales'] ?? 0) > 0));

        $bullets = json_decode((string) $request->input('bullets_json', '{}'), true);
        if (! is_array($bullets)) {
            $bullets = [];
        }

        $pdfPayload = $data + [
            'prepBy' => trim((string) $request->input('prep_by', $data['targetMember']->name)),
            'roleTitle' => trim((string) $request->input('role_title', 'Automation Team Member')),
            'coverage' => trim((string) $request->input('coverage', $data['defaultCoverage'])),
            'bcTarget' => trim((string) $request->input('bc_target', '')),
            'posTarget' => trim((string) $request->input('pos_target', '')),
            'aiTarget' => trim((string) $request->input('ai_target', '')),
            'phonesTarget' => trim((string) $request->input('phones_target', '')),
            'macroToday' => trim((string) $request->input('macro_today', '')),
            'macroTarget' => trim((string) $request->input('macro_target', '')),
            'inqToday' => trim((string) $request->input('inq_today', '')),
            'inqYest' => trim((string) $request->input('inq_yest', '')),
            'inqTarget' => trim((string) $request->input('inq_target', '')),
            'pendingConcerns' => trim((string) $request->input('pending_concerns', '')),
            'pendingTarget' => trim((string) $request->input('pending_target', '')),
            'supportNeeded' => trim((string) $request->input('support_needed', '')),
            'ceoDecision' => trim((string) $request->input('ceo_decision', 'Wala.')),
            'prio1' => trim((string) $request->input('prio_1', '')),
            'prio2' => trim((string) $request->input('prio_2', '')),
            'prio3' => trim((string) $request->input('prio_3', '')),
            'includePagesTable' => $request->boolean('include_pages_table', true),
            'customRows' => $customRows,
            'bullets' => $bullets,
        ];

        $pdf = Pdf::loadView('eod-pdf', $pdfPayload)->setPaper('a4', 'portrait');
        $slug = Str::slug($pdfPayload['prepBy'] ?: 'member');

        return $pdf->download("EOD-{$slug}-{$data['reportDate']}.pdf");
    }

    public function downloadAdminPdf(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $this->buildAdminEodData($request, $request->input('frozen_end'));

        $decodeArr = function (string $key): array {
            $arr = json_decode((string) request()->input($key, '[]'), true);

            return is_array($arr) ? $arr : [];
        };

        $adMeta = $decodeArr('product_ads_json'); // keyed by product name: ['inquiries' => ..., 'spend' => ...]
        $extraKeyRows = $decodeArr('extra_kpi_rows_json');
        $extraMissedRows = $decodeArr('extra_missed_rows_json');
        $issuesRows = $decodeArr('issues_rows_json');
        $memberNotes = $decodeArr('member_notes_json');

        $pdfPayload = $data + [
            'prepBy' => trim((string) $request->input('prep_by', $request->user()->name)),
            'position' => trim((string) $request->input('position', 'Automation Team Leader')),
            'department' => trim((string) $request->input('department', 'Automation - Marketing')),
            'consolidatedNames' => trim((string) $request->input('consolidated_names', $data['defaultConsolidatedNames'])),
            'totalAdSpend' => (float) $request->input('total_ad_spend', 0),
            'totalInquiries' => (int) $request->input('total_inquiries', 0),
            'yestAdSpend' => (float) $request->input('yest_ad_spend', 0),
            'yestConvPct' => trim((string) $request->input('yest_conv_pct', '')),
            'creativesPending' => trim((string) $request->input('creatives_pending', '')),
            'leaderSummary' => trim((string) $request->input('leader_summary', '')),
            'decisionText' => trim((string) $request->input('decision_text', 'None / Wala.')),
            'decisionBy' => trim((string) $request->input('decision_by', '—')),
            'prio1' => trim((string) $request->input('prio_1', '')),
            'prio1Owner' => trim((string) $request->input('prio_1_owner', 'Team')),
            'prio2' => trim((string) $request->input('prio_2', '')),
            'prio2Owner' => trim((string) $request->input('prio_2_owner', 'Team')),
            'prio3' => trim((string) $request->input('prio_3', '')),
            'prio3Owner' => trim((string) $request->input('prio_3_owner', 'Team')),
            'adMeta' => $adMeta,
            'extraKeyRows' => $extraKeyRows,
            'extraMissedRows' => $extraMissedRows,
            'issuesRows' => $issuesRows,
            'memberNotes' => $memberNotes,
        ];

        $pdf = Pdf::loadView('eod-admin-pdf', $pdfPayload)->setPaper('a4', 'portrait');

        return $pdf->download("Team-Leader-EOD-{$data['reportDate']}.pdf");
    }

    private function buildAdminEodData(Request $request, ?string $frozenEndIso = null): array
    {
        $tz = config('kpi.timezone', 'Asia/Manila');
        $allPages = Cache::remember('kpi:all_pages', 900, fn () => DashboardController::lightPagesQuery()->get());
        $pageIds = $allPages->pluck('fb_page_id')->map(fn ($id) => (string) $id)->values()->all();

        $nowLocal = now($tz);
        $reportDate = $request->input('date', $nowLocal->toDateString());
        try {
            $dayStartLocal = Carbon::parse($reportDate, $tz)->startOfDay();
        } catch (\Throwable) {
            $reportDate = $nowLocal->toDateString();
            $dayStartLocal = $nowLocal->copy()->startOfDay();
        }

        $isToday = $dayStartLocal->toDateString() === $nowLocal->toDateString();
        if ($frozenEndIso) {
            try {
                $dayEndLocal = Carbon::parse($frozenEndIso, $tz);
            } catch (\Throwable) {
                $dayEndLocal = $isToday ? $nowLocal->copy() : $dayStartLocal->copy()->endOfDay();
            }
        } else {
            $dayEndLocal = $isToday ? $nowLocal->copy() : $dayStartLocal->copy()->endOfDay();
        }

        $prevStartLocal = $dayStartLocal->copy()->subDay()->startOfDay();
        $prevEndLocal = $dayStartLocal->copy()->subDay()->endOfDay();

        $startUtc = $dayStartLocal->copy()->utc();
        $endUtc = $dayEndLocal->copy()->utc();
        $prevStartUtc = $prevStartLocal->copy()->utc();
        $prevEndUtc = $prevEndLocal->copy()->utc();

        $todayStats = $this->computeWindow($pageIds, $startUtc, $endUtc);
        $yesterdayStats = $this->computeWindow($pageIds, $prevStartUtc, $prevEndUtc);

        // Map page_id -> page model & assigned member names
        $usersWithPages = User::with(['pages:id,fb_page_id,name'])->orderBy('name')->get();
        $members = $usersWithPages->where('role', 'member')->values();
        $pageOwnerMap = [];
        foreach ($members as $m) {
            foreach ($m->pages as $mp) {
                $pageOwnerMap[$mp->fb_page_id][] = $m->name;
            }
        }

        // Per-page orders today & yesterday + broadcast counts today
        $ordTodayByPage = ! empty($pageIds)
            ? PosOrder::whereIn('page_id', $pageIds)
                ->whereBetween('ordered_at', [$startUtc, $endUtc])
                ->where('is_valid', true)
                ->selectRaw("
                    page_id,
                    count(*) as orders,
                    coalesce(sum(total), 0) as sales,
                    count(*) filter (where source_type = 'botcake_ai') as ai_orders,
                    count(*) filter (where source_type = 'agent') as agent_orders,
                    coalesce(sum(total) filter (where source_type = 'agent'), 0) as agent_sales
                ")
                ->groupBy('page_id')
                ->get()
                ->keyBy('page_id')
            : collect();

        $ordYestByPage = ! empty($pageIds)
            ? PosOrder::whereIn('page_id', $pageIds)
                ->whereBetween('ordered_at', [$prevStartUtc, $prevEndUtc])
                ->where('is_valid', true)
                ->selectRaw('page_id, count(*) as orders, coalesce(sum(total), 0) as sales')
                ->groupBy('page_id')
                ->get()
                ->keyBy('page_id')
            : collect();

        $bcTodayByPage = ! empty($pageIds)
            ? Broadcast::whereIn('page_id', $pageIds)
                ->whereBetween('sent_at', [$startUtc, $endUtc])
                ->where('status', '!=', 'SCHEDULED')
                ->selectRaw('page_id, count(*) as bc_count, coalesce(sum(delivered), 0) as delivered, coalesce(sum(phone_count), 0) as phones')
                ->groupBy('page_id')
                ->get()
                ->keyBy('page_id')
            : collect();

        // 1) Product Breakdown Table (grouped by Product across all active pages)
        $productRows = $allPages->groupBy(fn ($p) => trim((string) ($p->product ?: $p->name)))
            ->map(function ($grp, $prodName) use ($ordTodayByPage, $ordYestByPage, $bcTodayByPage) {
                $ordT = 0;
                $salT = 0.0;
                $aiT = 0;
                $ordY = 0;
                $salY = 0.0;
                $bcCount = 0;
                $bcActivePages = 0;
                $phones = 0;

                foreach ($grp as $p) {
                    $ot = $ordTodayByPage[$p->fb_page_id] ?? null;
                    $oy = $ordYestByPage[$p->fb_page_id] ?? null;
                    $bc = $bcTodayByPage[$p->fb_page_id] ?? null;
                    $ordT += (int) ($ot->orders ?? 0);
                    $salT += (float) ($ot->sales ?? 0);
                    $aiT += (int) ($ot->ai_orders ?? 0);
                    $ordY += (int) ($oy->orders ?? 0);
                    $salY += (float) ($oy->sales ?? 0);
                    if ($bc && (int) $bc->bc_count > 0) {
                        $bcActivePages++;
                        $bcCount += (int) $bc->bc_count;
                        $phones += (int) $bc->phones;
                    }
                }

                return [
                    'product' => $prodName,
                    'pages_count' => $grp->count(),
                    'bc_active_pages' => $bcActivePages,
                    'bc_count' => $bcCount,
                    'phones' => $phones,
                    'orders' => $ordT,
                    'sales' => $salT,
                    'aov' => $ordT > 0 ? ($salT / $ordT) : 0.0,
                    'ai_orders' => $aiT,
                    'yest_orders' => $ordY,
                    'yest_sales' => $salY,
                    'page_names' => $grp->pluck('name')->implode(' · '),
                ];
            })
            ->sortByDesc(fn ($r) => [$r['orders'], $r['sales'], $r['pages_count']])
            ->values();

        // 2) Manual / Agent-Closed ("Orders Missed by Chatbot / Handled Manually") per page
        $missedByPage = [];
        $pageNameOwners = [];
        foreach ($allPages as $p) {
            $owner = $p->team ?: (! empty($pageOwnerMap[$p->fb_page_id]) ? implode(', ', $pageOwnerMap[$p->fb_page_id]) : 'Unassigned');
            $pageNameOwners[$p->name] = $owner;
            $ot = $ordTodayByPage[$p->fb_page_id] ?? null;
            $agOrd = (int) ($ot->agent_orders ?? 0);
            if ($agOrd > 0) {
                $missedByPage[] = [
                    'page' => $p->name,
                    'owner' => $owner,
                    'orders' => $agOrd,
                    'sales' => (float) ($ot->agent_sales ?? 0),
                ];
            }
        }
        usort($missedByPage, fn ($a, $b) => [$b['orders'], $b['sales']] <=> [$a['orders'], $a['sales']]);

        $inboxCounts = $this->computeInboxCounts($pageIds, $startUtc, $endUtc);

        $activeBcPagesCount = $bcTodayByPage->filter(fn ($b) => (int) $b->bc_count > 0)->count();

        // 3) Submitted Member EODs + Per-Member Live Stats
        $submissions = Schema::hasTable('eod_submissions')
            ? EodSubmission::whereDate('report_date', $dayStartLocal->toDateString())->get()->keyBy('user_id')
            : collect();

        $memberSections = $members->map(function (User $m) use ($ordTodayByPage, $bcTodayByPage, $submissions) {
            $mPages = $m->pages;
            $ord = 0;
            $sal = 0.0;
            $ai = 0;
            $agOrd = 0;
            $agSal = 0.0;
            $bcSends = 0;
            $bcDeliv = 0;

            foreach ($mPages as $mp) {
                $ot = $ordTodayByPage[$mp->fb_page_id] ?? null;
                $bc = $bcTodayByPage[$mp->fb_page_id] ?? null;
                $ord += (int) ($ot->orders ?? 0);
                $sal += (float) ($ot->sales ?? 0);
                $ai += (int) ($ot->ai_orders ?? 0);
                $agOrd += (int) ($ot->agent_orders ?? 0);
                $agSal += (float) ($ot->agent_sales ?? 0);
                $bcSends += (int) ($bc->bc_count ?? 0);
                $bcDeliv += (int) ($bc->delivered ?? 0);
            }

            $sub = $submissions[$m->id] ?? null;
            $defaultSummary = "Monitored {$mPages->count()} assigned page(s); tracked ".number_format($ord).' verified POS order(s) (₱'.number_format($sal, 0).', '.number_format($ai).' closed by Botcake AI) and '.number_format($bcSends).' broadcast send(s) ('.number_format($bcDeliv).' delivered).';
            if ($agOrd > 0) {
                $defaultSummary .= " {$agOrd} order(s) (₱".number_format($agSal, 0).') on assigned pages were handled manually by an agent.';
            }

            return [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'pages_count' => $mPages->count(),
                'orders' => $ord,
                'sales' => $sal,
                'ai_orders' => $ai,
                'agent_orders' => $agOrd,
                'agent_sales' => $agSal,
                'bc_sends' => $bcSends,
                'bc_delivered' => $bcDeliv,
                'submitted_at' => $sub?->submitted_at,
                'submission' => $sub?->payload ?? null,
                'default_summary' => $defaultSummary,
            ];
        })->values();

        // Auto-draft initial Issues & Blockers from live anomalies
        $defaultIssues = [];
        if ($todayStats['agent_orders'] > 0) {
            $defaultIssues[] = [
                'issue' => "{$todayStats['agent_orders']} order(s) (₱".number_format($todayStats['agent_sales'], 0).') across '.count($missedByPage).' page(s) required manual agent handling instead of Botcake AI.',
                'owner' => 'Automation Team',
                'status' => 'Monitoring',
            ];
        }
        if ($todayStats['bc_api_limit'] > 0 || $todayStats['bc_failures'] > 0) {
            $defaultIssues[] = [
                'issue' => "{$todayStats['bc_failures']} broadcast send issue(s) detected (API limit #613: ".number_format($todayStats['bc_api_limit']).', Blocked #551: '.number_format($todayStats['bc_blocked']).').',
                'owner' => 'Broadcast Lead',
                'status' => 'Open',
            ];
        }
        $zeroOrderProds = $productRows->where('orders', 0)->take(3)->pluck('product')->all();
        if (! empty($zeroOrderProds)) {
            $defaultIssues[] = [
                'issue' => '0 POS orders recorded in current window for: '.implode(', ', $zeroOrderProds).'.',
                'owner' => 'Team Leader',
                'status' => 'Under Review',
            ];
        }

        $teamNamesList = collect([$request->user()->name.' (Team Leader)'])
            ->merge($members->pluck('name'))
            ->unique()
            ->implode(', ');

        return [
            'pages' => $allPages,
            'allPages' => $allPages,
            'products' => $allPages->pluck('product')->filter()->unique()->sort()->values(),
            'teams' => $allPages->pluck('team')->filter()->unique()->sort()->values(),
            'product' => null,
            'team' => null,
            'pageId' => 'all',
            'from' => $reportDate,
            'to' => $reportDate,
            // Admin EOD payload
            'members' => $members,
            'memberSections' => $memberSections,
            'productRows' => $productRows,
            'missedByPage' => $missedByPage,
            'pageNameOwners' => $pageNameOwners,
            'inboxCounts' => $inboxCounts,
            'activeBcPagesCount' => $activeBcPagesCount,
            'totalPagesCount' => $allPages->count(),
            'defaultIssues' => $defaultIssues,
            'defaultConsolidatedNames' => $teamNamesList,
            'reportDate' => $reportDate,
            'reportDateFormatted' => $dayStartLocal->format('F j, Y'),
            'prevDateShort' => $prevStartLocal->format('M j'),
            'isToday' => $isToday,
            'endTimeShort' => $isToday ? $dayEndLocal->format('g:i A') : '11:59 PM',
            'frozenEndIso' => $dayEndLocal->toIso8601String(),
            'windowLabel' => '12:00 AM – '.$dayEndLocal->format('g:i A').' PHT',
            'generatedAtLabel' => $nowLocal->format('M j, Y · g:i:s A').' PHT',
            'todayStats' => $todayStats,
            'yesterdayStats' => $yesterdayStats,
        ];
    }

    private function buildMemberEodData(Request $request, ?string $frozenEndIso = null): array
    {
        $tz = config('kpi.timezone', 'Asia/Manila');
        /** @var User $me */
        $me = $request->user();
        $isAdmin = $me->isAdmin();

        $allPages = Cache::remember('kpi:all_pages', 900, fn () => DashboardController::lightPagesQuery()->get());
        $members = $isAdmin
            ? Cache::remember('kpi:member_accounts', 900, fn () => User::where('role', 'member')->orderBy('name')->get(['id', 'name', 'email']))
            : collect();

        $targetMember = $me;
        if ($isAdmin && $members->isNotEmpty()) {
            $reqMemberId = (int) $request->input('member_id', $members->first()->id);
            $targetMember = User::find($reqMemberId) ?? $members->first();
        }

        if ($targetMember->isAdmin()) {
            $assignedPages = $allPages;
        } else {
            $assignedDbIds = Cache::remember(
                "kpi:user_pages:{$targetMember->id}",
                900,
                fn () => $targetMember->pages()->pluck('pages.id')->all()
            );
            $assignedPages = $allPages->whereIn('id', $assignedDbIds)->values();
        }

        $pageIds = $assignedPages->pluck('fb_page_id')->map(fn ($id) => (string) $id)->values()->all();

        $nowLocal = now($tz);
        $reportDate = $request->input('date', $nowLocal->toDateString());
        try {
            $dayStartLocal = Carbon::parse($reportDate, $tz)->startOfDay();
        } catch (\Throwable) {
            $reportDate = $nowLocal->toDateString();
            $dayStartLocal = $nowLocal->copy()->startOfDay();
        }

        $isToday = $dayStartLocal->toDateString() === $nowLocal->toDateString();
        if ($frozenEndIso) {
            try {
                $dayEndLocal = Carbon::parse($frozenEndIso, $tz);
            } catch (\Throwable) {
                $dayEndLocal = $isToday ? $nowLocal->copy() : $dayStartLocal->copy()->endOfDay();
            }
        } else {
            $dayEndLocal = $isToday ? $nowLocal->copy() : $dayStartLocal->copy()->endOfDay();
        }

        $prevStartLocal = $dayStartLocal->copy()->subDay()->startOfDay();
        $prevEndLocal = $dayStartLocal->copy()->subDay()->endOfDay();

        $todayStats = $this->computeWindow($pageIds, $dayStartLocal->copy()->utc(), $dayEndLocal->copy()->utc());
        $yesterdayStats = $this->computeWindow($pageIds, $prevStartLocal->copy()->utc(), $prevEndLocal->copy()->utc());

        $activeSequences = ! empty($pageIds)
            ? Sequence::whereIn('page_id', $pageIds)->where('published_steps', '>', 0)->count()
            : 0;

        $perPageOrders = ! empty($pageIds)
            ? PosOrder::whereIn('page_id', $pageIds)
                ->whereBetween('ordered_at', [$dayStartLocal->copy()->utc(), $dayEndLocal->copy()->utc()])
                ->where('is_valid', true)
                ->selectRaw("page_id, count(*) as orders, coalesce(sum(total), 0) as sales, count(*) filter (where source_type = 'botcake_ai') as ai_orders")
                ->groupBy('page_id')
                ->get()
                ->keyBy('page_id')
            : collect();

        $perPageBc = ! empty($pageIds)
            ? Broadcast::whereIn('page_id', $pageIds)
                ->whereBetween('sent_at', [$dayStartLocal->copy()->utc(), $dayEndLocal->copy()->utc()])
                ->where('status', '!=', 'SCHEDULED')
                ->selectRaw('page_id, count(*) as bc_count, coalesce(sum(delivered), 0) as delivered, coalesce(sum(failed), 0) as failed, coalesce(sum(phone_count), 0) as phones')
                ->groupBy('page_id')
                ->get()
                ->keyBy('page_id')
            : collect();

        $assignedBreakdown = $assignedPages->map(function ($p) use ($perPageOrders, $perPageBc) {
            $o = $perPageOrders[$p->fb_page_id] ?? null;
            $b = $perPageBc[$p->fb_page_id] ?? null;

            return [
                'fb_page_id' => (string) $p->fb_page_id,
                'name' => $p->name,
                'product' => $p->product,
                'orders' => (int) ($o->orders ?? 0),
                'sales' => (float) ($o->sales ?? 0),
                'ai_orders' => (int) ($o->ai_orders ?? 0),
                'bc_count' => (int) ($b->bc_count ?? 0),
                'bc_delivered' => (int) ($b->delivered ?? 0),
                'bc_failed' => (int) ($b->failed ?? 0),
                'bc_phones' => (int) ($b->phones ?? 0),
            ];
        })->sortByDesc(fn ($r) => [$r['orders'], $r['sales'], $r['bc_count']])->values();

        $prodNames = $assignedPages->pluck('product')->filter()->unique()->values();
        if ($prodNames->count() <= 3 && $prodNames->isNotEmpty()) {
            $coveragePrefix = $prodNames->implode(', ').' Pages ('.$assignedPages->count().' assigned)';
        } else {
            $coveragePrefix = $assignedPages->count().' Assigned Pages';
        }
        $defaultCoverage = "{$coveragePrefix}, Broadcast Monitoring, Sequence Funnels, and Botcake AI Monitoring";
        $sidebarPages = $isAdmin ? $allPages : $assignedPages;

        $savedSubmission = (! $isAdmin && Schema::hasTable('eod_submissions'))
            ? EodSubmission::where('user_id', $targetMember->id)->whereDate('report_date', $dayStartLocal->toDateString())->first()
            : null;

        $inboxCounts = $this->computeInboxCounts($pageIds, $dayStartLocal->copy()->utc(), $dayEndLocal->copy()->utc());

        return [
            'pages' => $sidebarPages,
            'allPages' => $allPages,
            'products' => $sidebarPages->pluck('product')->filter()->unique()->sort()->values(),
            'teams' => $sidebarPages->pluck('team')->filter()->unique()->sort()->values(),
            'product' => null,
            'team' => null,
            'pageId' => 'all',
            'from' => $reportDate,
            'to' => $reportDate,
            'targetMember' => $targetMember,
            'members' => $members,
            'assignedPages' => $assignedPages,
            'assignedBreakdown' => $assignedBreakdown,
            'savedSubmission' => $savedSubmission,
            'inboxCounts' => $inboxCounts,
            'reportDate' => $reportDate,
            'reportDateFormatted' => $dayStartLocal->format('F j, Y'),
            'isToday' => $isToday,
            'frozenEndIso' => $dayEndLocal->toIso8601String(),
            'windowLabel' => '12:00 AM – '.$dayEndLocal->format('g:i A').' PHT',
            'generatedAtLabel' => $nowLocal->format('M j, Y · g:i:s A').' PHT',
            'defaultCoverage' => $defaultCoverage,
            'todayStats' => $todayStats,
            'yesterdayStats' => $yesterdayStats,
            'activeSequences' => $activeSequences,
        ];
    }

    private function computeWindow(array $pageIds, Carbon $startUtc, Carbon $endUtc): array
    {
        if (empty($pageIds)) {
            return [
                'pages' => 0, 'bc_checked' => 0, 'bc_successful' => 0, 'bc_failures' => 0,
                'bc_targeted' => 0, 'bc_delivered' => 0, 'bc_not_delivered' => 0,
                'bc_blocked' => 0, 'bc_api_limit' => 0, 'bc_phones' => 0, 'deliv_rate' => 0.0,
                'orders' => 0, 'sales' => 0.0, 'canceled' => 0,
                'ai_orders' => 0, 'ai_sales' => 0.0,
                'bc_orders' => 0, 'bc_sales' => 0.0, 'bc_assisted' => 0,
                'seq_orders' => 0, 'seq_sales' => 0.0, 'seq_assisted' => 0,
                'agent_orders' => 0, 'agent_sales' => 0.0, 'handoff_inquiries' => 0, 'organic_orders' => 0,
            ];
        }

        $bcRows = Broadcast::whereIn('page_id', $pageIds)
            ->whereBetween('sent_at', [$startUtc, $endUtc])
            ->where('status', '!=', 'SCHEDULED')
            ->get(['id', 'status', 'targeted', 'delivered', 'failed', 'blocked_count', 'api_limit_count', 'phone_count']);

        $bcChecked = $bcRows->count();
        $bcSuccessful = $bcRows->filter(function ($b) {
            if ($b->targeted > 0 && $b->delivered === 0) {
                return false;
            }
            if ($b->api_limit_count > 0 && $b->targeted > 0 && ($b->delivered / $b->targeted) < 0.7) {
                return false;
            }

            return $b->delivered > 0 || $b->targeted === 0;
        })->count();

        $bcTargeted = (int) $bcRows->sum('targeted');
        $bcDelivered = (int) $bcRows->sum('delivered');

        $ordAgg = PosOrder::whereIn('page_id', $pageIds)
            ->whereBetween('ordered_at', [$startUtc, $endUtc])
            ->selectRaw("
                count(*) filter (where is_valid) as orders,
                coalesce(sum(total) filter (where is_valid), 0) as sales,
                count(*) filter (where not is_valid) as canceled,
                count(*) filter (where is_valid and source_type = 'botcake_ai') as ai_orders,
                coalesce(sum(total) filter (where is_valid and source_type = 'botcake_ai'), 0) as ai_sales,
                count(*) filter (where is_valid and source_type = 'broadcast') as bc_orders,
                coalesce(sum(total) filter (where is_valid and source_type = 'broadcast'), 0) as bc_sales,
                count(*) filter (where is_valid and assisted_type = 'broadcast') as bc_assisted,
                count(*) filter (where is_valid and source_type = 'sequence') as seq_orders,
                coalesce(sum(total) filter (where is_valid and source_type = 'sequence'), 0) as seq_sales,
                count(*) filter (where is_valid and assisted_type = 'sequence') as seq_assisted,
                count(*) filter (where is_valid and source_type = 'agent') as agent_orders,
                coalesce(sum(total) filter (where is_valid and source_type = 'agent'), 0) as agent_sales,
                count(*) filter (where is_valid and source_type = 'agent' and coalesce(closed_by, '') != 'Telesales') as handoff_inquiries,
                count(*) filter (where is_valid and coalesce(source_type, 'organic') = 'organic') as organic_orders
            ")
            ->first();

        return [
            'pages' => count($pageIds),
            'bc_checked' => $bcChecked,
            'bc_successful' => $bcSuccessful,
            'bc_failures' => max(0, $bcChecked - $bcSuccessful),
            'bc_targeted' => $bcTargeted,
            'bc_delivered' => $bcDelivered,
            'bc_not_delivered' => (int) $bcRows->sum('failed'),
            'bc_blocked' => (int) $bcRows->sum('blocked_count'),
            'bc_api_limit' => (int) $bcRows->sum('api_limit_count'),
            'bc_phones' => (int) $bcRows->sum('phone_count'),
            'deliv_rate' => $bcTargeted > 0 ? ($bcDelivered / $bcTargeted * 100) : 0.0,
            'orders' => (int) ($ordAgg->orders ?? 0),
            'sales' => (float) ($ordAgg->sales ?? 0),
            'canceled' => (int) ($ordAgg->canceled ?? 0),
            'ai_orders' => (int) ($ordAgg->ai_orders ?? 0),
            'ai_sales' => (float) ($ordAgg->ai_sales ?? 0),
            'bc_orders' => (int) ($ordAgg->bc_orders ?? 0),
            'bc_sales' => (float) ($ordAgg->bc_sales ?? 0),
            'bc_assisted' => (int) ($ordAgg->bc_assisted ?? 0),
            'seq_orders' => (int) ($ordAgg->seq_orders ?? 0),
            'seq_sales' => (float) ($ordAgg->seq_sales ?? 0),
            'seq_assisted' => (int) ($ordAgg->seq_assisted ?? 0),
            'agent_orders' => (int) ($ordAgg->agent_orders ?? 0),
            'agent_sales' => (float) ($ordAgg->agent_sales ?? 0),
            'handoff_inquiries' => (int) ($ordAgg->handoff_inquiries ?? 0),
            'organic_orders' => (int) ($ordAgg->organic_orders ?? 0),
        ];
    }

    private function computeInboxCounts(array $pageIds, Carbon $startUtc, Carbon $endUtc): array
    {
        if (empty($pageIds) || ! Schema::hasTable('inbox_conversations')) {
            return ['unanswered' => 0, 'human_assisted' => 0];
        }

        $unanswered = InboxConversation::whereIn('page_id', $pageIds)
            ->where('last_sender_type', 'customer')
            ->where('has_order_today', false)
            ->whereBetween('last_message_at', [$startUtc, $endUtc])
            ->count();

        $humanAssisted = InboxConversation::whereIn('page_id', $pageIds)
            ->where('last_sender_type', 'human_agent')
            ->whereBetween('last_message_at', [$startUtc, $endUtc])
            ->count();

        return [
            'unanswered' => (int) $unanswered,
            'human_assisted' => (int) $humanAssisted,
        ];
    }
}
