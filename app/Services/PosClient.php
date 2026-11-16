<?php

namespace App\Services;

use App\Models\PosShop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class PosClient
{
    private string $baseUrl;

    public function __construct(private string $apiKey, private string $shopId)
    {
        $this->baseUrl = config('kpi.pos.base_url');
    }

    public static function fromConfig(): self
    {
        return new self((string) config('kpi.pos.api_key'), (string) config('kpi.pos.shop_id'));
    }

    /** Returns all unique configured + DB-stored POS API keys. */
    public static function allApiKeys(): array
    {
        $keys = array_filter([
            trim((string) config('kpi.pos.api_key')),
            trim((string) config('kpi.pos.herbal_api_key')),
        ]);

        try {
            if (Schema::hasTable('pos_shops')) {
                foreach (PosShop::where('active', true)->get() as $shop) {
                    $k = trim((string) $shop->api_key);
                    if ($k !== '') {
                        $keys[] = $k;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return array_values(array_unique($keys));
    }

    /**
     * Maps every Facebook page_id across all connected POS shops (KGOLD MAIN + KGOLD - HERBAL + any future shop)
     * to its exact shop_id, api_key, shop_name, and page_name.
     *
     * @return array<string, array{shop_id:string,api_key:string,shop_name:string,page_name:string}>
     */
    public static function directory(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('kpi:pos_directory');
        }

        return Cache::remember('kpi:pos_directory', 600, function () {
            $baseUrl = config('kpi.pos.base_url');
            $map = [];

            foreach (self::allApiKeys() as $apiKey) {
                try {
                    $res = Http::acceptJson()->timeout(20)->get("{$baseUrl}/shops", ['api_key' => $apiKey]);
                    if (! $res->successful()) {
                        continue;
                    }
                    foreach ($res->json('shops') ?? [] as $shop) {
                        $sid = (string) ($shop['id'] ?? '');
                        $sname = trim((string) ($shop['name'] ?? "Shop {$sid}"));
                        if ($sid === '') {
                            continue;
                        }
                        foreach ($shop['pages'] ?? [] as $sp) {
                            $pid = (string) ($sp['id'] ?? '');
                            if ($pid === '') {
                                continue;
                            }
                            $map['id_'.$pid] = [
                                'shop_id' => $sid,
                                'api_key' => $apiKey,
                                'shop_name' => $sname,
                                'page_name' => trim((string) ($sp['name'] ?? '')),
                            ];
                        }
                    }
                } catch (\Throwable) {
                }
            }

            return $map;
        });
    }

    /** Resolves the right PosClient (Main vs Herbal vs any future shop) for a specific Facebook Page ID. */
    public static function forPage(string $fbPageId): ?self
    {
        $dir = self::directory();
        $entry = $dir['id_'.$fbPageId] ?? null;
        if (! $entry) {
            return null;
        }

        return new self($entry['api_key'], $entry['shop_id']);
    }

    /**
     * Orders for one page between two unix timestamps (by inserted_at).
     * Yields order arrays page by page.
     */
    public function orders(string $pageId, int $startTs, int $endTs): \Generator
    {
        for ($page = 1; $page <= 200; $page++) {
            $res = Http::acceptJson()->timeout(90)->retry(3, 2000, throw: false)
                ->get("{$this->baseUrl}/shops/{$this->shopId}/orders", [
                    'api_key' => $this->apiKey,
                    'page_id' => $pageId,
                    'startDateTime' => $startTs,
                    'endDateTime' => $endTs,
                    'page_size' => 100,
                    'page_number' => $page,
                ]);

            if (! $res->successful()) {
                throw new \RuntimeException("POS orders failed (shop {$this->shopId}): HTTP {$res->status()}");
            }

            $json = $res->json();
            foreach ($json['data'] ?? [] as $order) {
                yield $order;
            }

            if ($page >= ($json['total_pages'] ?? 1) || empty($json['data'])) {
                break;
            }
        }
    }
}
