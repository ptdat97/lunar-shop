<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns / RMA. A customer opens a return request against a paid order,
 * selecting which lines + quantities and a reason. Staff approve/reject in the
 * admin; an approved+refunded request ties back to the gateway refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('lunar_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('lunar_customers')->nullOnDelete();
            $table->string('reference')->unique();          // RMA-XXXX
            $table->string('status')->default('requested'); // requested|approved|rejected|refunded|completed
            $table->string('reason')->nullable();           // return reason (enum-ish)
            $table->text('comment')->nullable();            // customer note
            $table->text('staff_note')->nullable();         // admin note on decision
            $table->unsignedBigInteger('refund_amount')->nullable(); // minor units, once refunded
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('return_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained('lunar_order_lines')->cascadeOnDelete();
            $table->unsignedInteger('quantity');            // how many of the line are returned
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_lines');
        Schema::dropIfExists('return_requests');
    }
};
