<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shops', function (Blueprint $t) {
            $t->id();
            $t->string('shop_id')->unique();
            $t->string('name');
            $t->text('api_key'); // encrypted cast
            $t->unsignedInteger('pages_count')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shops');
    }
};
