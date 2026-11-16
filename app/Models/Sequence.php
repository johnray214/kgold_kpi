<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sequence extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['remote_created_at' => 'datetime'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class)->orderBy('position');
    }
}
