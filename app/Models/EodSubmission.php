<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EodSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'payload' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
