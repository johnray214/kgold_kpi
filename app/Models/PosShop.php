<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosShop extends Model
{
    protected $guarded = [];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'active' => 'boolean',
        ];
    }
}
