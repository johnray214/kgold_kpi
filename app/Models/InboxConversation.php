<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'has_phone' => 'boolean',
            'seen' => 'boolean',
            'message_count' => 'integer',
            'tags' => 'array',
            'has_order_today' => 'boolean',
            'has_any_order' => 'boolean',
            'last_message_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
