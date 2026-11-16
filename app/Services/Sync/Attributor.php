<?php

namespace App\Services\Sync;

use App\Models\Broadcast;
use App\Models\Page;
use App\Models\PosOrder;
use App\Models\Sequence;
use App\Services\PancakeClient;
use Illuminate\Support\Carbon;

/**
 * Credits each POS order to exactly one source using the brief's rules:
 * last touch wins; broadcast window 24h, sequence window 48h; Botcake AI if the
 * AI was the last to talk before the order; human agent / telesales if a person
 * closed it (earlier touch saved as "assisted by"); otherwise Organic.
 */
class Attributor
{
    private const BOTCAKE_APP_ID = 556376998159104;

    private PancakeClient $pancake;

    private array $sequenceNames;

    private $broadcasts;

    public function __construct(private Page $page)
    {
        $this->pancake = new PancakeClient($page->fb_page_id, $page->pancake_token);
        $this->sequenceNames = Sequence::where('page_id', $page->fb_page_id)->pluck('name', 'id')->all();
        $this->broadcasts = Broadcast::where('page_id', $page->fb_page_id)
            ->whereNotNull('sent_at')->orderBy('sent_at')->get(['id', 'name', 'sent_at']);
    }

    public function attribute(PosOrder $order): void
    {
        $result = $this->resolve($order);
        $order->fill($result + ['attributed_at' => now()])->save();
    }

    private function resolve(PosOrder $order): array
    {
        $cfg = config('kpi.attribution');
        $orderAt = $order->ordered_at->copy()->utc();

        $touches = $order->conversation_id ? $this->touches($order->conversation_id, $orderAt) : [];

        $lastAutomation = null;
        $lastTouch = null;
        foreach ($touches as $t) {
            $lastTouch = $t;
            if (in_array($t['type'], ['broadcast', 'sequence'], true)) {
                $lastAutomation = $t;
            }
        }

        // Is the last automation touch still inside its window?
        $automationInWindow = null;
        if ($lastAutomation) {
            $hours = $lastAutomation['type'] === 'broadcast' ? $cfg['broadcast_hours'] : $cfg['sequence_hours'];
            if ($lastAutomation['at']->diffInHours($orderAt, true) <= $hours) {
                $automationInWindow = $lastAutomation;
            }
        }

        $assisted = fn (?array $t) => [
            'assisted_type' => $t['type'] ?? null,
            'assisted_id' => $t['id'] ?? null,
            'assisted_name' => $t['name'] ?? null,
        ];

        // 1) Telesales marker in POS note → Agent / Telesales, but only if the call happened
        //    BEFORE the order. EasyDial notes added after the order are confirmation/upsell calls.
        foreach ($cfg['telesales_markers'] as $marker) {
            if ($order->note && str_contains($order->note, $marker)
                && preg_match('/'.preg_quote($marker, '/').'\s*(\d{4}-\d{2}-\d{2} \d{1,2}:\d{2})/', $order->note, $m)
                && Carbon::parse($m[1], config('kpi.timezone'))->lessThanOrEqualTo($orderAt)) {
                return ['source_type' => 'agent', 'source_id' => null, 'source_name' => 'Telesales',
                    'closed_by' => 'Telesales', 'touched_at' => $lastTouch['at'] ?? null] + $assisted($automationInWindow);
            }
        }

        if (! $lastTouch) {
            return ['source_type' => 'organic', 'source_id' => null, 'source_name' => 'Organic / Unattributed',
                'closed_by' => null, 'touched_at' => null] + $assisted(null);
        }

        // 2) Human agent talked last → Agent
        if ($lastTouch['type'] === 'agent') {
            return ['source_type' => 'agent', 'source_id' => null, 'source_name' => 'Agent',
                'closed_by' => $lastTouch['name'], 'touched_at' => $lastTouch['at']] + $assisted($automationInWindow);
        }

        // 3) Botcake AI talked last → Botcake AI
        if ($lastTouch['type'] === 'botcake_ai') {
            return ['source_type' => 'botcake_ai', 'source_id' => null, 'source_name' => 'Botcake AI',
                'closed_by' => 'BotcakeAI', 'touched_at' => $lastTouch['at']] + $assisted($automationInWindow);
        }

        // 4) Broadcast / sequence inside window
        if ($automationInWindow) {
            return ['source_type' => $automationInWindow['type'], 'source_id' => $automationInWindow['id'],
                'source_name' => $automationInWindow['name'], 'closed_by' => null,
                'touched_at' => $automationInWindow['at']] + $assisted(null);
        }

        return ['source_type' => 'organic', 'source_id' => null, 'source_name' => 'Organic / Unattributed',
            'closed_by' => null, 'touched_at' => $lastTouch['at'] ?? null] + $assisted($lastAutomation);
    }

    /**
     * Touchpoints (sorted oldest → newest) before the order, within the longest window.
     *
     * @return array<int, array{type:string,id:?int,name:?string,at:Carbon}>
     */
    private function touches(string $conversationId, Carbon $orderAt): array
    {
        $cfg = config('kpi.attribution');
        $lookback = $orderAt->copy()->subHours(max($cfg['broadcast_hours'], $cfg['sequence_hours']));

        // Load messages newest → oldest until we pass the lookback. Old orders may need many batches
        // (30 messages each) because the chat kept going after the order.
        $messages = [];
        for ($batch = 0; $batch < 30; $batch++) {
            $rows = $this->pancake->messages($conversationId, count($messages));
            if (! $rows) {
                break;
            }
            $messages = array_merge($rows, $messages);
            $oldest = Carbon::parse($rows[0]['inserted_at'], 'UTC');
            if ($oldest->lessThan($lookback) || count($rows) < 30) {
                break;
            }
        }

        $touches = [];
        foreach ($messages as $m) {
            $at = Carbon::parse($m['inserted_at'], 'UTC');
            if ($at->greaterThan($orderAt) || $at->lessThan($lookback)) {
                continue;
            }
            $from = $m['from'] ?? [];
            if (($from['id'] ?? null) !== $this->page->fb_page_id) {
                continue; // customer message
            }

            $touch = $this->classify($from, $at);
            if ($touch) {
                $touches[] = $touch + ['at' => $at];
            }
        }

        usort($touches, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $touches;
    }

    private function classify(array $from, Carbon $at): ?array
    {
        $admin = $from['admin_name'] ?? null;
        $sourceType = $from['source_type'] ?? null;

        if ($admin === config('kpi.attribution.ai_sender')) {
            return ['type' => 'botcake_ai', 'id' => null, 'name' => 'Botcake AI'];
        }

        if ($admin === 'Botcake') {
            if ($sourceType === 'sequence' && ! empty($from['source_id'])) {
                $id = (int) $from['source_id'];

                return ['type' => 'sequence', 'id' => $id, 'name' => $this->sequenceNames[$id] ?? "Sequence {$id}"];
            }
            if ($sourceType === 'broadcast') {
                $id = (int) ($from['source_id'] ?? $from['broadcast_id'] ?? 0);
                $b = $this->broadcasts->firstWhere('id', $id) ?? $this->broadcastAt($at);

                return $b ? ['type' => 'broadcast', 'id' => $b->id, 'name' => $b->name]
                    : ['type' => 'broadcast', 'id' => $id ?: null, 'name' => "Broadcast {$id}"];
            }

            return null; // keyword replies, flows etc. are not touchpoints
        }

        if ($admin === 'POS') {
            return null;
        }

        if ($admin === null) {
            // Untagged message sent through the Botcake app = broadcast; match by send time.
            if ((int) ($from['app_id'] ?? 0) === self::BOTCAKE_APP_ID && ($b = $this->broadcastAt($at))) {
                return ['type' => 'broadcast', 'id' => $b->id, 'name' => $b->name];
            }

            return null;
        }

        // Any other admin name = a human agent.
        return ['type' => 'agent', 'id' => null, 'name' => $admin];
    }

    /** Most recent broadcast started up to 6h before $at (broadcasts send over time). */
    private function broadcastAt(Carbon $at): ?Broadcast
    {
        return $this->broadcasts
            ->filter(fn ($b) => $b->sent_at->lessThanOrEqualTo($at->copy()->addMinutes(2))
                && $b->sent_at->greaterThanOrEqualTo($at->copy()->subHours(6)))
            ->last();
    }
}
