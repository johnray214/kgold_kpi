<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_conversations', function (Blueprint $t) {
            $t->string('id', 120)->primary(); // {page_id}_{psid}
            $t->string('page_id', 64)->index();
            $t->string('page_name', 191);
            $t->string('customer_id', 80)->nullable()->index();
            $t->string('customer_name', 191)->default('Facebook User');
            $t->boolean('has_phone')->default(false);
            $t->string('phone', 64)->nullable();
            $t->text('snippet')->nullable();
            $t->boolean('seen')->default(false);
            $t->unsignedInteger('message_count')->default(0);
            $t->string('last_sender_type', 32)->default('bot')->index(); // customer | human_agent | botcake_ai | bot
            $t->string('last_admin_name', 191)->nullable();
            $t->string('assigned_to', 191)->nullable();
            $t->jsonb('tags')->default('[]');
            $t->boolean('has_order_today')->default(false)->index();
            $t->boolean('has_any_order')->default(false);
            $t->timestampTz('last_message_at')->nullable()->index();
            $t->timestampTz('synced_at')->nullable();
            $t->timestamps();

            $t->index(['page_id', 'last_sender_type', 'last_message_at'], 'inbox_conv_page_sender_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversations');
    }
};
