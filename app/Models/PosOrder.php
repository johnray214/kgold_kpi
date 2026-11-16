<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosOrder extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    public const SOURCES = [
        'broadcast' => 'Broadcast',
        'sequence' => 'Sequence',
        'botcake_ai' => 'Botcake AI',
        'agent' => 'Agent / Telesales',
        'organic' => 'Organic / Unattributed',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'touched_at' => 'datetime',
            'attributed_at' => 'datetime',
            'is_valid' => 'boolean',
            'total' => 'decimal:2',
        ];
    }
}
