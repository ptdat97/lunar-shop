<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow definitions: when a registered trigger (a Hooks::* domain event)
 * fires, evaluate conditions (a serialised RuleSet) against the trigger context
 * and, if they pass, run an action (registered by id) with its config. Generic
 * and business-free — the engine only orchestrates; triggers/actions/fields are
 * contributed by modules/plugins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('trigger');                 // a Hooks::* action name
            $table->json('conditions')->nullable();     // RuleSet::toArray() JSON
            $table->string('action');                   // registered action id
            $table->json('action_config')->nullable();  // per-action settings
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['trigger', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
