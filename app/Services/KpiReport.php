<?php

namespace App\Services;

use App\Models\Broadcast;
use App\Models\Page;
use App\Models\PosOrder;
use App\Models\Sequence;
use App\Models\SequenceStepSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class KpiReport
{
    public Carbon $fromUtc;

    public Carbon $toUtc;

    /** @var array<int, string> */
    public array $pageIds;

    /** @var array<string, string> */
    private array $pageNames;

    public function __construct(public string $pageId, public string $from, public string $to, ?Collection $pages = null)
    {
        $tz = config('kpi.timezone');
        $this->fromUtc = Carbon::parse($from, $tz)->startOfDay()->utc();
        $this->toUtc = Carbon::parse($to, $tz)->endOfDay()->utc();

        $pages ??= Page::where('active', true)->get();
        $this->pageNames = $pages->pluck('name', 'fb_page_id')->all();
        // Keep IDs strictly as strings (PHP's array_keys casts numeric string keys to int, breaking Postgres varchar IN (...) with emulated prepares).
        $this->pageIds = $pageId === 'all'
            ? $pages->pluck('fb_page_id')->map(fn ($id) => (string) $id)->values()->all()
            : [(string) $pageId];
    }

    private function scopePages($query, string $col = 'page_id')
    {
        $ids = array_map('strval', $this->pageIds);

        return count($ids) === 1
            ? $query->where($col, $ids[0])
            : $query->whereIn($col, $ids);
    }

    private function orders()
    {
        return $this->scopePages(PosOrder::query())
            ->whereBetween('ordered_at', [$this->fromUtc, $this->toUtc]);
    }

    /** Per-page rollup across all tracked pages for the selected date range. */
    public function pageBreakdown(Collection $pages): Collection
    {
        $pIds = $pages->pluck('fb_page_id')->map(fn ($id) => (string) $id)->values()->all();
        $orderAgg = PosOrder::whereIn('page_id', $pIds)
            ->whereBetween('ordered_at', [$this->fromUtc, $this->toUtc])
            ->selectRaw("
                page_id,
                count(*) filter (where is_valid) as valid_orders,
                coalesce(sum(total) filter (where is_valid), 0) as sales,
                count(*) filter (where not is_valid) as canceled,
                count(*) filter (where is_valid and source_type = 'broadcast') as bc_orders,
                count(*) filter (where is_valid and source_type = 'sequence') as seq_orders,
                count(*) filter (where is_valid and source_type = 'botcake_ai') as ai_orders,
                count(*) filter (where is_valid and source_type is null) as unattributed
            ")
            ->groupBy('page_id')
            ->get()
            ->keyBy('page_id');

        $bcAgg = Broadcast::whereIn('page_id', $pIds)
            ->whereBetween('sent_at', [$this->fromUtc, $this->toUtc])
            ->where('status', '!=', 'SCHEDULED')
            ->selectRaw('page_id, count(*) as bc_count, coalesce(sum(delivered), 0) as delivered, coalesce(sum(phone_count), 0) as phones')
            ->groupBy('page_id')
            ->get()
            ->keyBy('page_id');

        return $pages->map(function (Page $p) use ($orderAgg, $bcAgg) {
            $o = $orderAgg[$p->fb_page_id] ?? null;
            $b = $bcAgg[$p->fb_page_id] ?? null;
            $validOrders = (int) ($o->valid_orders ?? 0);
            $sales = (float) ($o->sales ?? 0);

            return [
                'fb_page_id' => (string) $p->fb_page_id,
                'name' => $p->name,
                'avatar' => $p->avatar_url,
                'product' => $p->product,
                'team' => $p->team,
                'has_botcake' => isset($p->has_botcake) ? (bool) $p->has_botcake : ! empty($p->botcake_token),
                'has_pancake' => isset($p->has_pancake) ? (bool) $p->has_pancake : ! empty($p->pancake_token),
                'orders' => $validOrders,
                'sales' => $sales,
                'aov' => $validOrders > 0 ? $sales / $validOrders : null,
                'canceled' => (int) ($o->canceled ?? 0),
                'bc_orders' => (int) ($o->bc_orders ?? 0),
                'seq_orders' => (int) ($o->seq_orders ?? 0),
                'ai_orders' => (int) ($o->ai_orders ?? 0),
                'unattributed' => (int) ($o->unattributed ?? 0),
                'bc_count' => (int) ($b->bc_count ?? 0),
                'bc_delivered' => (int) ($b->delivered ?? 0),
                'bc_phones' => (int) ($b->phones ?? 0),
            ];
        })->sortByDesc(fn ($r) => [$r['orders'], $r['sales'], $r['bc_phones']])->values();
    }

    public function timeline(): array
    {
        $tz = config('kpi.timezone');
        $start = Carbon::parse($this->from, $tz)->startOfDay();
        $end = Carbon::parse($this->to, $tz)->endOfDay();
        $spanDays = (int) round($start->diffInDays($end->copy()->startOfDay())) + 1;

        $rows = $this->scopePages(PosOrder::where('is_valid', true))
            ->whereBetween('ordered_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->get(['ordered_at', 'source_type', 'total']);

        $labels = [];
        $series = array_fill_keys(array_keys(PosOrder::SOURCES) + ['pending' => 'Not yet attributed'], []);
        $hasPending = $rows->contains(fn ($r) => $r->source_type === null);
        if (! $hasPending) {
            unset($series['pending']);
        }
        $sales = [];

        if ($spanDays <= 2) {
            $stepHours = $spanDays === 1 ? 2 : 4;
            for ($slot = $start->copy(); $slot <= $end; $slot->addHours($stepHours)) {
                $slotEnd = $slot->copy()->addHours($stepHours);
                $labels[] = $spanDays === 1 ? $slot->format('ga') : $slot->format('M j ga');
                $bucket = $rows->filter(function ($o) use ($tz, $slot, $slotEnd) {
                    $local = $o->ordered_at->copy()->setTimezone($tz);

                    return $local->gte($slot) && $local->lt($slotEnd);
                });
                foreach (array_keys($series) as $src) {
                    $series[$src][] = $src === 'pending'
                        ? $bucket->whereNull('source_type')->count()
                        : $bucket->where('source_type', $src)->count();
                }
                $sales[] = (float) $bucket->sum('total');
            }

            return [
                'mode' => 'intraday',
                'subtitle' => $spanDays === 1 ? '2-hour intervals (Manila)' : '4-hour intervals across both days',
                'labels' => $labels,
                'series' => $series,
                'sales' => $sales,
            ];
        }

        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $d->format('M j');
            $dayRows = $rows->filter(fn ($o) => $o->ordered_at->copy()->setTimezone($tz)->toDateString() === $key);
            foreach (array_keys($series) as $src) {
                $series[$src][] = $src === 'pending'
                    ? $dayRows->whereNull('source_type')->count()
                    : $dayRows->where('source_type', $src)->count();
            }
            $sales[] = (float) $dayRows->sum('total');
        }

        return [
            'mode' => 'daily',
            'subtitle' => "{$spanDays}-day breakdown",
            'labels' => $labels,
            'series' => $series,
            'sales' => $sales,
        ];
    }

    public function comparison(?string $dataSince, ?array $curSales = null, ?Collection $curBroadcasts = null): ?array
    {
        if (! $dataSince) {
            return null;
        }
        $tz = config('kpi.timezone');
        $start = Carbon::parse($this->from, $tz)->startOfDay();
        $end = Carbon::parse($this->to, $tz)->endOfDay();
        $spanDays = (int) round($start->diffInDays($end->copy()->startOfDay())) + 1;

        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subDays($spanDays - 1)->startOfDay();
        $sinceLocal = Carbon::parse($dataSince)->setTimezone($tz)->startOfDay();

        if ($prevStart->lt($sinceLocal)) {
            return null;
        }

        $prevAgg = $this->scopePages(PosOrder::where('is_valid', true))
            ->whereBetween('ordered_at', [$prevStart->copy()->utc(), $prevEnd->copy()->utc()])
            ->selectRaw("count(*) as orders, coalesce(sum(total), 0) as sales, count(*) filter (where source_type = 'botcake_ai') as ai_orders")
            ->first();

        $pOrders = (int) ($prevAgg->orders ?? 0);
        $pSales = (float) ($prevAgg->sales ?? 0);
        $pAi = (int) ($prevAgg->ai_orders ?? 0);
        $pBcPhone = (int) $this->scopePages(Broadcast::query())
            ->whereBetween('sent_at', [$prevStart->copy()->utc(), $prevEnd->copy()->utc()])
            ->where('status', '!=', 'SCHEDULED')
            ->sum('phone_count');

        $pct = fn (float $curr, float $prev): ?float => $prev > 0 ? ($curr - $prev) / $prev : null;

        $curSales ??= $this->salesBySource();
        $curOrders = $curSales['total_orders'];
        $curRev = $curSales['total_sales'];
        $curAov = $curOrders > 0 ? $curRev / $curOrders : 0;
        $prevAov = $pOrders > 0 ? $pSales / $pOrders : 0;
        $curBcPhone = $curBroadcasts ? (int) $curBroadcasts->sum('phone_count') : (int) $this->scopePages(Broadcast::query())
            ->whereBetween('sent_at', [$this->fromUtc, $this->toUtc])
            ->where('status', '!=', 'SCHEDULED')
            ->sum('phone_count');
        $curAi = $curSales['sources']['botcake_ai']['orders'] ?? 0;

        return [
            'label' => $spanDays === 1 && $this->from === now($tz)->toDateString() ? 'vs yesterday' : "vs prev {$spanDays}d",
            'sales' => $pct($curRev, $pSales),
            'orders' => $pct($curOrders, $pOrders),
            'aov' => $pct($curAov, $prevAov),
            'bc_phone' => $pct($curBcPhone, $pBcPhone),
            'ai_orders' => $pct($curAi, $pAi),
        ];
    }

    public function salesBySource(): array
    {
        $rows = $this->orders()->where('is_valid', true)
            ->selectRaw("coalesce(source_type, 'pending') as source, count(*) as orders, sum(total) as sales")
            ->groupBy('source')->get()->keyBy('source');

        $out = [];
        foreach (PosOrder::SOURCES + ['pending' => 'POS Only (needs Pancake token)'] as $key => $label) {
            if ($key === 'pending' && ! isset($rows[$key])) {
                continue;
            }
            $orders = (int) ($rows[$key]->orders ?? 0);
            $sales = (float) ($rows[$key]->sales ?? 0);
            $out[$key] = [
                'label' => $label,
                'orders' => $orders,
                'sales' => $sales,
                'aov' => $orders > 0 ? $sales / $orders : null,
            ];
        }

        $canceled = $this->orders()->where('is_valid', false)->count();

        return [
            'sources' => $out,
            'total_orders' => array_sum(array_column($out, 'orders')),
            'total_sales' => array_sum(array_column($out, 'sales')),
            'canceled' => $canceled,
        ];
    }

    /** @var array<string, Collection> */
    private array $creditCache = [];

    private function creditMap(string $type, bool $assisted = false): Collection
    {
        $ck = $type.':'.($assisted ? '1' : '0');
        if (isset($this->creditCache[$ck])) {
            return $this->creditCache[$ck];
        }
        $typeCol = $assisted ? 'assisted_type' : 'source_type';
        $idCol = $assisted ? 'assisted_id' : 'source_id';

        return $this->creditCache[$ck] = $this->scopePages(PosOrder::where('is_valid', true))
            ->where($typeCol, $type)->whereNotNull($idCol)
            ->when($type === 'sequence' || $assisted, fn ($q) => $q->whereBetween('ordered_at', [$this->fromUtc, $this->toUtc]))
            ->selectRaw("{$idCol} as sid, count(*) as orders, sum(total) as sales")
            ->groupBy($idCol)->get()->keyBy('sid');
    }

    public static function segmentOf(string $name): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $name));
        if (preg_match('/^esme?ringhoy/i', $clean)) {
            return 'Esmeringhoy';
        }
        if (preg_match('/^within winning/i', $clean)) {
            return 'Within Winning';
        }
        if (preg_match('/^broadcast/i', $clean)) {
            return 'Broadcast';
        }
        $stripped = trim(preg_replace('/[-_\s]*\d+\s*$/', '', $clean));

        return $stripped !== '' ? $stripped : $clean;
    }

    public function broadcasts(bool $withErrors = false): Collection
    {
        $credited = $this->creditMap('broadcast');
        $assisted = $this->creditMap('broadcast', true);

        $cols = ['id', 'page_id', 'name', 'status', 'send_method', 'sent_at', 'targeted', 'delivered', 'failed', 'blocked_count', 'api_limit_count', 'seen', 'clicked', 'phone_count', 'failed_reason'];
        if ($withErrors) {
            $cols[] = 'error_breakdown';
        }

        return $this->scopePages(Broadcast::select($cols))
            ->whereBetween('sent_at', [$this->fromUtc, $this->toUtc])
            ->where('status', '!=', 'SCHEDULED')
            ->orderByDesc('sent_at')->get()
            ->map(function (Broadcast $b) use ($credited, $assisted) {
                $b->page_name = $this->pageNames[$b->page_id] ?? $b->page_id;
                $b->segment = self::segmentOf($b->name);
                $b->orders = (int) ($credited[$b->id]->orders ?? 0);
                $b->sales = (float) ($credited[$b->id]->sales ?? 0);
                $b->assisted = (int) ($assisted[$b->id]->orders ?? 0);
                $b->assisted_sales = (float) ($assisted[$b->id]->sales ?? 0);
                $b->total_orders = $b->orders + $b->assisted;
                $b->total_sales = $b->sales + $b->assisted_sales;
                $b->success_rate = $b->targeted ? $b->delivered / $b->targeted : null;
                $b->lead_rate = $b->delivered ? $b->phone_count / $b->delivered : null;
                $b->cvr = $b->delivered ? $b->total_orders / $b->delivered : null;

                return $b;
            });
    }

    public function broadcastSegments(Collection $broadcasts): Collection
    {
        return $broadcasts->groupBy('segment')->map(function (Collection $items, string $seg) {
            $targeted = $items->sum('targeted');
            $delivered = $items->sum('delivered');
            $orders = $items->sum('orders');
            $phone = $items->sum('phone_count');

            return [
                'name' => $seg,
                'count' => $items->count(),
                'targeted' => $targeted,
                'delivered' => $delivered,
                'failed' => $items->sum('failed'),
                'phone' => $phone,
                'orders' => $orders,
                'sales' => (float) $items->sum('sales'),
                'success_rate' => $targeted ? $delivered / $targeted : null,
                'cvr' => $delivered ? $orders / $delivered : null,
            ];
        })->sortByDesc('delivered')->values();
    }

    public function sequences(): Collection
    {
        $credited = $this->creditMap('sequence');
        $assisted = $this->creditMap('sequence', true);

        $fromDate = Carbon::parse($this->from)->toDateString();
        $toDate = Carbon::parse($this->to)->toDateString();

        $endSnap = $this->snapshotsOn('<=', $toDate);
        $startSnap = $this->snapshotsOn('<', $fromDate);

        return $this->scopePages(Sequence::where('published_steps', '>', 0))
            ->with('steps')->get()
            ->map(function (Sequence $s) use ($credited, $assisted, $endSnap, $startSnap) {
                $s->page_name = $this->pageNames[$s->page_id] ?? $s->page_id;
                $steps = $s->steps->values();
                $first = $steps->first();

                $delta = function (string $field, $stepIds) use ($endSnap, $startSnap) {
                    $sum = 0;
                    $known = false;
                    foreach ($stepIds as $id) {
                        if (isset($endSnap[$id]) && isset($startSnap[$id])) {
                            $sum += max(0, $endSnap[$id]->{$field} - $startSnap[$id]->{$field});
                            $known = true;
                        }
                    }

                    return $known ? $sum : null;
                };

                $s->entered = $first ? $delta('sends', [$first->id]) : null;
                $s->phone_in_range = $delta('phone_count', $steps->pluck('id'));
                $s->entered_total = $first->sends ?? 0;
                $s->completed_total = $steps->last()->sends ?? 0;
                $s->completion_rate = $s->entered_total > 0 ? $s->completed_total / $s->entered_total : null;
                $s->phone_total = $steps->sum('phone_count');

                $s->dropoff = null;
                $maxDrop = 0;
                foreach ($steps as $i => $step) {
                    $step->drop = $i > 0 ? max(0, $steps[$i - 1]->sends - $step->sends) : 0;
                    if ($step->drop > $maxDrop) {
                        $maxDrop = $step->drop;
                        $s->dropoff = $step;
                    }
                }

                $s->orders = (int) ($credited[$s->id]->orders ?? 0);
                $s->sales = (float) ($credited[$s->id]->sales ?? 0);
                $s->assisted = (int) ($assisted[$s->id]->orders ?? 0);
                $s->assisted_sales = (float) ($assisted[$s->id]->sales ?? 0);
                $base = $s->entered ?? null;
                $s->cvr = $base ? $s->orders / $base : null;

                return $s;
            })
            ->sortByDesc(fn ($s) => [$s->orders + $s->assisted, $s->subscribers])->values();
    }

    private function snapshotsOn(string $op, string $date): Collection
    {
        $latest = SequenceStepSnapshot::where('date', $op, $date)->max('date');
        if (! $latest) {
            return collect();
        }

        return \Illuminate\Support\Facades\DB::table('sequence_step_snapshots')
            ->whereDate('date', $latest)
            ->select(['step_id', 'sends', 'phone_count'])
            ->get()
            ->keyBy('step_id');
    }

    public function botcakeAi(): array
    {
        $agg = $this->orders()->where('is_valid', true)->selectRaw("
            count(*) filter (where source_type = 'botcake_ai') as orders,
            coalesce(sum(total) filter (where source_type = 'botcake_ai'), 0) as sales,
            count(*) filter (where source_type = 'botcake_ai' and assisted_type is not null) as assisted,
            count(*) filter (where source_type = 'agent' and coalesce(closed_by, '') != 'Telesales') as handoff
        ")->first();

        return [
            'orders' => (int) ($agg->orders ?? 0),
            'sales' => (float) ($agg->sales ?? 0),
            'assisted_by_automation' => (int) ($agg->assisted ?? 0),
            'handoff' => (int) ($agg->handoff ?? 0),
        ];
    }

    public function orderList(array $filters, int $limit = 500): Collection
    {
        $cols = [
            'id', 'page_id', 'page_name', 'conversation_id', 'psid', 'phone', 'customer_name',
            'total', 'status', 'status_name', 'is_valid', 'ordered_at', 'items_summary',
            'source_type', 'source_id', 'source_name', 'assisted_type', 'assisted_id', 'assisted_name', 'closed_by',
        ];
        $q = $this->orders()->select($cols)->orderByDesc('ordered_at');

        if (! empty($filters['source_type'])) {
            $col = ! empty($filters['assisted']) ? 'assisted_type' : 'source_type';
            if ($filters['source_type'] === 'pending') {
                $q->whereNull('source_type');
            } else {
                $q->where($col, $filters['source_type']);
            }
        }
        if (! empty($filters['source_id'])) {
            $col = ! empty($filters['assisted']) ? 'assisted_id' : 'source_id';
            $q->where($col, $filters['source_id']);
            if (($filters['source_type'] ?? null) === 'broadcast' && empty($filters['assisted'])) {
                $q = $this->scopePages(PosOrder::select($cols))->where('source_type', 'broadcast')
                    ->where('source_id', $filters['source_id'])->orderByDesc('ordered_at');
            }
        }
        if (isset($filters['valid']) && $filters['valid'] !== '') {
            $q->where('is_valid', (bool) $filters['valid']);
        }

        return $q->limit($limit)->get();
    }
}
