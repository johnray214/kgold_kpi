<?php

namespace App\Services\Sync;

use App\Models\Page;
use App\Models\PosOrder;
use App\Services\PosClient;
use Illuminate\Support\Carbon;

class PosSync
{
    public function __construct(private Page $page, private ?PosClient $client = null)
    {
        $this->client ??= PosClient::forPage((string) $page->fb_page_id);
    }

    public function sync(Carbon $from, Carbon $to): int
    {
        if (! $this->client) {
            return 0; // Page is not linked to any connected POS shop
        }

        $excluded = config('kpi.pos.excluded_statuses');
        $count = 0;

        foreach ($this->client->orders($this->page->fb_page_id, $from->timestamp, $to->timestamp) as $o) {
            $conversationId = $o['conversation_id'] ?? null;
            $psid = null;
            if ($conversationId && str_contains($conversationId, '_')) {
                [, $psid] = explode('_', $conversationId, 2);
            }

            $items = collect($o['items'] ?? [])
                ->map(fn ($i) => trim(($i['variation_info']['name'] ?? 'Item').' x'.($i['quantity'] ?? 1)))
                ->implode(', ');

            $status = isset($o['status']) ? (int) $o['status'] : null;
            $total = (float) ($o['total_price_after_sub_discount'] ?? 0);
            if ($total <= 0) {
                $total = (float) ($o['total_price'] ?? ($o['cod'] ?? ($o['money_to_collect'] ?? 0)));
            }

            // Exclude canceled/deleted statuses AND empty ₱0 auto-created phone-lead draft shells (no items & ₱0)
            $isEmptyDraft = $total <= 0 && trim($items) === '';
            $isValid = ! in_array($status, $excluded, true) && ! $isEmptyDraft;

            $order = PosOrder::firstOrNew(['id' => $o['id']]);
            $order->fill([
                'page_id' => $o['page_id'] ?? $this->page->fb_page_id,
                'page_name' => $o['page']['name'] ?? $this->page->name,
                'conversation_id' => $conversationId,
                'psid' => $psid,
                'phone' => $o['bill_phone_number'] ?? null,
                'customer_name' => $o['bill_full_name'] ?? ($o['customer']['name'] ?? null),
                'total' => $total,
                'status' => $status,
                'status_name' => $isEmptyDraft ? 'draft (no items)' : ($o['status_name'] ?? null),
                'is_valid' => $isValid,
                'ordered_at' => Carbon::parse($o['inserted_at'], 'UTC'),
                'items_summary' => mb_substr($items, 0, 1000),
                'note' => $o['note'] ?? null,
                'ad_id' => $o['ad_id'] ?? null,
            ]);
            $order->save();
            $count++;
        }

        return $count;
    }
}
