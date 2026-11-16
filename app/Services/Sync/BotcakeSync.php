<?php

namespace App\Services\Sync;

use App\Models\Broadcast;
use App\Models\Page;
use App\Models\Sequence;
use App\Models\SequenceStep;
use App\Models\SequenceStepSnapshot;
use App\Services\BotcakeClient;
use Illuminate\Support\Carbon;

class BotcakeSync
{
    private BotcakeClient $client;

    public function __construct(private Page $page)
    {
        $this->client = new BotcakeClient($page->fb_page_id, $page->botcake_token);
    }

    /** Pull broadcast list + stats. Re-fetches stats for anything recent or unfinished. */
    public function syncBroadcasts(int $recentDays = 3, int $historyDays = 14): int
    {
        $count = 0;
        $recentCutoff = now()->subDays($recentDays);
        $historyCutoff = now()->subDays($historyDays);

        foreach ($this->client->broadcasts($historyCutoff) as $row) {
            $createdAt = isset($row['created_at']) ? Carbon::parse($row['created_at']) : null;
            $scheduledAt = ! empty($row['scheduled_at']) ? Carbon::parse($row['scheduled_at']) : null;
            $sentAt = $scheduledAt ?? $createdAt;
            if ($sentAt && $sentAt->lessThan($historyCutoff) && ($row['status'] ?? null) !== 'SCHEDULED') {
                continue;
            }

            $b = Broadcast::firstOrNew(['id' => $row['id']]);
            $b->fill([
                'page_id' => $this->page->fb_page_id,
                'name' => $row['name'] ?? '(no name)',
                'status' => $row['status'] ?? null,
                'send_method' => $row['send_method'] ?? null,
                'remote_created_at' => $createdAt,
                'scheduled_at' => $scheduledAt,
                'sent_at' => $scheduledAt ?? $createdAt,
            ]);

            $needsStats = $b->stats_synced_at === null
                || $b->status !== 'FINISHED'
                || ($b->failed > 0 && $b->error_breakdown === null)
                || ($b->sent_at && $b->sent_at->greaterThan($recentCutoff));

            if ($needsStats && $b->status !== 'SCHEDULED') {
                $detail = $this->client->broadcast($b->id);
                $stats = $detail['stats'] ?? [];
                $extra = $this->client->broadcastStatistic($b->id);

                $targeted = (int) ($stats['sent'] ?? $extra['total_count'] ?? 0);
                $delivered = (int) ($stats['delivered'] ?? $extra['delivery'] ?? 0);
                $failed = (int) ($stats['failed'] ?? 0);
                if ($failed === 0 && $targeted > $delivered && $b->status === 'FINISHED') {
                    $failed = $targeted - $delivered;
                }

                $errors = $failed > 0 ? $this->client->broadcastErrors($b->id) : [];
                $blockedCount = 0;
                $apiLimitCount = 0;
                foreach ($errors as $err) {
                    $code = (int) ($err['code'] ?? 0);
                    $cnt = (int) ($err['count'] ?? 0);
                    if ($code === 551) {
                        $blockedCount += $cnt;
                    } elseif ($code === 613) {
                        $apiLimitCount += $cnt;
                    }
                }

                $topReason = null;
                if ($failed > 0) {
                    $realErrors = array_values(array_filter(
                        $errors,
                        fn ($e) => ! ((int) ($e['code'] ?? 0) === 2022 && ($b->send_method === 'utility_message' || (int) ($e['count'] ?? 0) > $failed))
                    ));
                    $top = $realErrors[0] ?? $errors[0] ?? null;
                    if ($top) {
                        $topCode = (int) ($top['code'] ?? 0);
                        $topReason = match ($topCode) {
                            613 => 'API rate limit exceeded (#613)',
                            551 => 'Blocked / account unavailable (#551)',
                            2022 => 'Permission / window error (#2022)',
                            100 => 'Account unavailable (#100)',
                            default => preg_replace('/^\(#\d+\)\s*/', '', (string) ($top['description'] ?? ''))." (#{$topCode})",
                        };
                    } else {
                        $topReason = config('kpi.failed_reason_label');
                    }
                }

                $b->fill([
                    'targeted' => $targeted,
                    'delivered' => $delivered,
                    'failed' => $failed,
                    'blocked_count' => $blockedCount,
                    'api_limit_count' => $apiLimitCount,
                    'seen' => (int) ($stats['read'] ?? $extra['seen'] ?? 0),
                    'clicked' => (int) ($stats['clicked'] ?? $extra['is_clicked'] ?? 0),
                    'phone_count' => (int) ($extra['total_phone_number'] ?? 0),
                    'failed_reason' => $topReason ? mb_substr($topReason, 0, 250) : null,
                    'error_breakdown' => $errors,
                    'stats_synced_at' => now(),
                ]);
            }

            $b->save();
            $count++;
        }

        return $count;
    }

    /** Pull sequences, step order and cumulative step stats; snapshot today's numbers. */
    public function syncSequences(): int
    {
        $today = now(config('kpi.timezone'))->toDateString();
        $count = 0;

        foreach ($this->client->sequences() as $row) {
            $seq = Sequence::updateOrCreate(['id' => $row['id']], [
                'page_id' => $this->page->fb_page_id,
                'name' => $row['name'] ?? "(unnamed sequence {$row['id']})",
                'steps_count' => (int) ($row['steps'] ?? 0),
                'published_steps' => (int) ($row['published_steps'] ?? 0),
                'subscribers' => (int) ($row['subscribers'] ?? 0),
                'remote_created_at' => isset($row['created_at']) ? Carbon::parse($row['created_at']) : null,
            ]);
            $count++;

            if ($seq->published_steps === 0) {
                continue;
            }

            $positions = [];
            foreach ($this->client->sequenceSteps($seq->id) as $i => $step) {
                $positions[$step['step_id']] = ['position' => $i + 1, 'status' => $step['status'] ?? null, 'name' => $step['name']];
            }

            foreach ($this->client->sequenceStatistics($seq->id) as $stat) {
                $id = $stat['message_id'];
                $values = [
                    'sends' => (int) ($stat['sends'] ?? 0),
                    'delivery' => (int) ($stat['delivery'] ?? 0),
                    'seen' => (int) ($stat['seen'] ?? 0),
                    'clicked' => (int) ($stat['is_clicked'] ?? 0),
                    'phone_count' => (int) ($stat['total_phone_number'] ?? 0),
                ];

                SequenceStep::updateOrCreate(['id' => $id], $values + [
                    'sequence_id' => $seq->id,
                    'position' => $positions[$id]['position'] ?? 999,
                    'status' => $positions[$id]['status'] ?? null,
                    'name' => $stat['name'] ?? ($positions[$id]['name'] ?? "Step {$id}"),
                ]);

                SequenceStepSnapshot::updateOrCreate(
                    ['date' => $today, 'step_id' => $id],
                    $values + ['sequence_id' => $seq->id]
                );
            }
        }

        return $count;
    }
}
