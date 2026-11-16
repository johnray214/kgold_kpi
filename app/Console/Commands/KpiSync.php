<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\PosOrder;
use App\Models\SyncRun;
use App\Services\Sync\Attributor;
use App\Services\Sync\BotcakeSync;
use App\Services\Sync\PosSync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class KpiSync extends Command
{
    protected $signature = 'kpi:sync
        {--days=2 : How many days back to pull (2 = yesterday & today)}
        {--from= : Backfill from this date (YYYY-MM-DD) — overrides --days and pulls older broadcasts too}
        {--page= : Optional single fb_page_id to sync}
        {--only= : botcake|pos|attribute}
        {--reattribute : Re-run attribution on orders that already have a source}';

    protected $description = 'Pull Botcake, Pancake and POS data and attribute POS orders to a source';

    public function handle(): int
    {
        $this->ensureTrackedPages();
        $only = $this->option('only');
        $tz = config('kpi.timezone');
        $fromDate = $this->option('from')
            ? Carbon::parse($this->option('from'), $tz)->startOfDay()
            : now($tz)->startOfDay()->subDays(max(1, (int) $this->option('days')) - 1);

        $pages = Page::where('active', true)
            ->when($this->option('page'), fn ($q, $pid) => $q->where('fb_page_id', $pid))
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            $this->info("== {$page->name} ({$page->fb_page_id})");

            if (! $only || $only === 'botcake') {
                if (empty($page->botcake_token)) {
                    $this->line('  botcake: skipped (no Botcake token set yet)');
                } else {
                    $this->step('botcake', function () use ($page, $fromDate) {
                        $sync = new BotcakeSync($page);
                        $daysBack = max(2, (int) ceil($fromDate->diffInDays(now(), true)) + 1);
                        $b = $sync->syncBroadcasts($daysBack, $daysBack + 1);
                        $s = $sync->syncSequences();

                        return "{$page->name}: {$b} broadcasts, {$s} sequences";
                    });
                }
            }

            if (! $only || $only === 'pos') {
                $this->step('pos', function () use ($page, $fromDate) {
                    $n = (new PosSync($page))->sync($fromDate->copy()->utc(), now()->utc());

                    return "{$page->name}: {$n} orders";
                });
            }

            if (! $only || $only === 'attribute') {
                if (empty($page->pancake_token)) {
                    $this->line('  attribute: skipped (no Pancake token set yet)');
                } else {
                    $this->step('attribute', function () use ($page, $fromDate) {
                        $attributor = new Attributor($page);
                        $cutoff = $fromDate->copy()->utc();
                        $q = PosOrder::where('page_id', $page->fb_page_id)
                            ->where('ordered_at', '>=', $cutoff)
                            ->whereNotNull('conversation_id');
                        if (! $this->option('reattribute')) {
                            $q->whereNull('attributed_at');
                        }
                        $done = 0;
                        $failed = 0;
                        foreach ($q->orderBy('ordered_at')->cursor() as $order) {
                            try {
                                $attributor->attribute($order);
                                $done++;
                            } catch (\Throwable $e) {
                                $failed++;
                                $this->warn("  order {$order->id}: {$e->getMessage()}");
                            }
                        }
                        PosOrder::where('page_id', $page->fb_page_id)
                            ->where('ordered_at', '>=', $cutoff)
                            ->whereNull('conversation_id')
                            ->whereNull('attributed_at')
                            ->update(['source_type' => 'organic', 'source_name' => 'Organic / Unattributed', 'attributed_at' => now()]);

                        return "{$page->name}: {$done} attributed".($failed ? ", {$failed} failed" : '');
                    });
                }
            }
        }

        \Illuminate\Support\Facades\Cache::forever('kpi:ver', (string) time());
        \Illuminate\Support\Facades\Cache::forget('kpi:sync_meta');

        return self::SUCCESS;
    }

    private function step(string $task, \Closure $fn): void
    {
        $run = SyncRun::create(['task' => $task, 'status' => 'running', 'started_at' => now()]);
        try {
            $msg = $fn();
            $run->update(['status' => 'ok', 'message' => $msg, 'finished_at' => now()]);
            $this->line("  {$task}: {$msg}");
        } catch (\Throwable $e) {
            $run->update(['status' => 'error', 'message' => substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
            $this->error("  {$task} failed: {$e->getMessage()}");
        }
    }

    public static function ensureTrackedPages(): void
    {
        // Only seed defaults when the pages table is completely empty so deleted pages never reappear automatically.
        if (Page::count() > 0) {
            return;
        }
        $default = config('kpi.default_page');
        foreach (config('kpi.tracked_pages', []) as $row) {
            $tokens = ($row['fb_page_id'] === ($default['fb_page_id'] ?? null))
                ? ['botcake_token' => $default['botcake_token'] ?? null, 'pancake_token' => $default['pancake_token'] ?? null]
                : [];
            Page::create($row + $tokens + ['active' => true]);
        }
    }
}
