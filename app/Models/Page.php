<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $guarded = [];

    protected $hidden = ['botcake_token', 'pancake_token'];

    protected function casts(): array
    {
        return [
            'botcake_token' => 'encrypted',
            'pancake_token' => 'encrypted',
            'active' => 'boolean',
        ];
    }

    public function getAvatarUrlAttribute(): string
    {
        return kpi_page_avatar($this->fb_page_id);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'page_user')->withTimestamps();
    }
}
