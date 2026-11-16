<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PancakeClient
{
    public function __construct(private string $pageId, private string $token) {}

    private function get(string $version, string $path, array $query = []): array
    {
        $url = config('kpi.pancake.base_url')."/{$version}/pages/{$this->pageId}{$path}";
        $res = Http::acceptJson()->timeout(60)->retry(3, 1000, throw: false)
            ->get($url, $query + ['page_access_token' => $this->token]);

        if (! $res->successful()) {
            throw new \RuntimeException("Pancake {$path} failed: HTTP {$res->status()}");
        }

        return $res->json() ?? [];
    }

    /** Messages of one conversation (newest batch; pass $currentCount to load older ones). */
    public function messages(string $conversationId, int $currentCount = 0): array
    {
        $query = $currentCount > 0 ? ['current_count' => $currentCount] : [];

        return $this->get('v1', "/conversations/{$conversationId}/messages", $query)['messages'] ?? [];
    }

    public function conversations(array $query = []): array
    {
        return $this->get('v2', '/conversations', $query)['conversations'] ?? [];
    }

    public function pageStatistics(int $sinceTs, int $untilTs): array
    {
        return $this->get('v1', '/statistics/pages', ['since' => $sinceTs, 'until' => $untilTs])['data'] ?? [];
    }
}
