<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BotcakeClient
{
    public function __construct(private string $pageId, private string $token) {}

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('kpi.botcake.base_url')."/pages/{$this->pageId}")
            ->withHeaders(['access-token' => $this->token])
            ->acceptJson()
            ->timeout(60)
            ->retry(3, 1000, throw: false);
    }

    private function get(string $path, array $query = []): array
    {
        $res = $this->http()->get($path, $query);
        if (! $res->successful()) {
            throw new \RuntimeException("Botcake {$path} failed: HTTP {$res->status()} ".substr($res->body(), 0, 200));
        }

        return $res->json() ?? [];
    }

    /** Broadcasts newest first, cursor-paginated; stops once older than $since. */
    public function broadcasts(?\DateTimeInterface $since = null): array
    {
        $all = [];
        $cursor = null;
        for ($i = 0; $i < 100; $i++) {
            $json = $this->get('/broadcasts', array_filter(['limit' => 100, 'cursor' => $cursor]));
            $rows = $json['data'] ?? [];
            foreach ($rows as $r) {
                $all[$r['id']] = $r;
            }
            $cursor = $json['meta']['next_cursor'] ?? null;
            $oldest = end($rows)['created_at'] ?? null;
            if (! $rows || ! $cursor || ($since && $oldest && strtotime($oldest) < $since->getTimestamp())) {
                break;
            }
        }

        return array_values($all);
    }

    public function broadcast(int $id): array
    {
        return $this->get("/broadcasts/{$id}")['data'] ?? [];
    }

    /** Undocumented endpoint that includes total_phone_number. */
    public function broadcastStatistic(int $id): array
    {
        return $this->get("/broadcasts/{$id}/statistic");
    }

    /** Official Meta delivery failure reasons per error code for a broadcast. */
    public function broadcastErrors(int $id): array
    {
        try {
            return $this->get("/broadcasts/{$id}/errors")['data'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function sequences(): array
    {
        return $this->get('/sequences')['data'] ?? [];
    }

    public function sequenceSteps(int $sequenceId): array
    {
        return $this->get("/sequences/{$sequenceId}/steps")['data'] ?? [];
    }

    public function sequenceStatistics(int $sequenceId): array
    {
        return $this->get("/sequences/{$sequenceId}/statistics")['data'] ?? [];
    }

    /** Customers who received one sequence step (200 per page). */
    public function sequenceStepCustomers(int $sequenceId, int $messageId, int $page = 1): array
    {
        return $this->get("/sequences/{$sequenceId}/statistics/{$messageId}", ['page' => $page])['data'] ?? [];
    }

    public function tagStatistic(int $startTs, int $endTs): array
    {
        return $this->get('/get_tag_statistic', ['start_date' => $startTs, 'end_date' => $endTs])['data'] ?? [];
    }
}
