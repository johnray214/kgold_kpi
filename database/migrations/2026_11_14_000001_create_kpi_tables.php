<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('fb_page_id')->unique();
            $t->string('name');
            $t->string('product')->nullable();
            $t->text('botcake_token')->nullable();   // encrypted cast
            $t->text('pancake_token')->nullable();   // encrypted cast
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $t) {
            $t->unsignedBigInteger('id')->primary();          // Botcake broadcast id
            $t->string('page_id')->index();
            $t->string('name');
            $t->string('status')->nullable();                 // FINISHED / SENDING / SCHEDULED
            $t->string('send_method')->nullable();
            $t->timestampTz('remote_created_at')->nullable();
            $t->timestampTz('scheduled_at')->nullable();
            $t->timestampTz('sent_at')->nullable()->index();   // scheduled_at ?? created_at
            $t->unsignedInteger('targeted')->default(0);      // Botcake "sent" = recipients scheduled
            $t->unsignedInteger('delivered')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->unsignedInteger('seen')->default(0);
            $t->unsignedInteger('clicked')->default(0);
            $t->unsignedInteger('phone_count')->default(0);
            $t->string('failed_reason')->nullable();
            $t->timestampTz('stats_synced_at')->nullable();
            $t->timestamps();
        });

        Schema::create('sequences', function (Blueprint $t) {
            $t->unsignedBigInteger('id')->primary();          // Botcake sequence id
            $t->string('page_id')->index();
            $t->string('name');
            $t->unsignedInteger('steps_count')->default(0);
            $t->unsignedInteger('published_steps')->default(0);
            $t->unsignedInteger('subscribers')->default(0);
            $t->timestampTz('remote_created_at')->nullable();
            $t->timestamps();
        });

        Schema::create('sequence_steps', function (Blueprint $t) {
            $t->unsignedBigInteger('id')->primary();          // Botcake message_id / step_id
            $t->unsignedBigInteger('sequence_id')->index();
            $t->unsignedInteger('position')->default(0);
            $t->string('name');
            $t->string('status')->nullable();
            $t->unsignedInteger('sends')->default(0);         // cumulative
            $t->unsignedInteger('delivery')->default(0);
            $t->unsignedInteger('seen')->default(0);
            $t->unsignedInteger('clicked')->default(0);
            $t->unsignedInteger('phone_count')->default(0);
            $t->timestamps();
        });

        // End-of-day copy of cumulative step stats so we can compute "per day" numbers.
        Schema::create('sequence_step_snapshots', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->unsignedBigInteger('sequence_id')->index();
            $t->unsignedBigInteger('step_id');
            $t->unsignedInteger('sends')->default(0);
            $t->unsignedInteger('delivery')->default(0);
            $t->unsignedInteger('seen')->default(0);
            $t->unsignedInteger('clicked')->default(0);
            $t->unsignedInteger('phone_count')->default(0);
            $t->timestamps();
            $t->unique(['date', 'step_id']);
        });

        Schema::create('pos_orders', function (Blueprint $t) {
            $t->unsignedBigInteger('id')->primary();          // POS order id
            $t->string('page_id')->nullable()->index();
            $t->string('page_name')->nullable();
            $t->string('conversation_id')->nullable();
            $t->string('psid')->nullable()->index();
            $t->string('phone')->nullable()->index();
            $t->string('customer_name')->nullable();
            $t->decimal('total', 12, 2)->default(0);
            $t->integer('status')->nullable();
            $t->string('status_name')->nullable();
            $t->boolean('is_valid')->default(true);           // false if canceled/deleted
            $t->timestampTz('ordered_at')->index();
            $t->text('items_summary')->nullable();
            $t->text('note')->nullable();
            $t->string('ad_id')->nullable();

            // Attribution (one source per order)
            $t->string('source_type')->nullable()->index();   // broadcast|sequence|botcake_ai|agent|organic
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('source_name')->nullable();
            $t->timestampTz('touched_at')->nullable();
            $t->string('assisted_type')->nullable();
            $t->unsignedBigInteger('assisted_id')->nullable();
            $t->string('assisted_name')->nullable();
            $t->string('closed_by')->nullable();              // agent name / BotcakeAI
            $t->timestampTz('attributed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('sync_runs', function (Blueprint $t) {
            $t->id();
            $t->string('task');
            $t->string('status');                             // ok | error
            $t->text('message')->nullable();
            $t->timestampTz('started_at');
            $t->timestampTz('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['sync_runs', 'pos_orders', 'sequence_step_snapshots', 'sequence_steps', 'sequences', 'broadcasts', 'pages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
