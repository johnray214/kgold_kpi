<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'remote_created_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'stats_synced_at' => 'datetime',
            'error_breakdown' => 'array',
        ];
    }
}
