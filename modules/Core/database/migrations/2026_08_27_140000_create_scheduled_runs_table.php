<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per scheduled-task run, so "did the cron actually run?" becomes a
 * question the app can answer.
 *
 * deployment.md §4 already warns in writing that if `orders:expire-abandoned`
 * stops running, stock stays reserved forever. Nothing detected that it had
 * stopped — silence looked exactly like "no abandoned orders to expire".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('runtime_ms')->nullable();
            $table->boolean('ok')->default(false);
            $table->text('failure')->nullable();

            // The heartbeat check asks "when did this command last succeed?" —
            // that lookup drives the index.
            $table->index(['command', 'ok', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_runs');
    }
};
