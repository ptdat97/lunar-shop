<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Checkout\Services\GatewayReconciler;

/**
 * One capture per (order, gateway, gateway-transaction-id).
 *
 * The application already serialises callbacks with `lockForUpdate`
 * ({@see GatewayReconciler}), but the return URL and
 * the IPN arrive on separate connections and a lock only helps code that takes
 * it. This makes the invariant the database's job: a duplicate capture cannot be
 * written even by a future caller that forgets.
 *
 * Scoped to `type = 'capture'` via a functional index (MySQL 8+). Refunds are
 * deliberately excluded — a partial refund falls back to `refund-{order_id}`
 * when the gateway returns no reference, so refund rows legitimately repeat.
 */
return new class extends Migration
{
    private const NAME = 'lunar_transactions_capture_unique';

    public function up(): void
    {
        if (! $this->isMysql() || $this->indexExists()) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX '.self::NAME.' ON lunar_transactions '.
            "((CASE WHEN type = 'capture' THEN order_id ELSE NULL END), ".
            "(CASE WHEN type = 'capture' THEN driver ELSE NULL END), ".
            "(CASE WHEN type = 'capture' THEN reference ELSE NULL END))"
        );
    }

    public function down(): void
    {
        if ($this->isMysql() && $this->indexExists()) {
            DB::statement('DROP INDEX '.self::NAME.' ON lunar_transactions');
        }
    }

    private function isMysql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    private function indexExists(): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            ['lunar_transactions', self::NAME]
        ) !== null;
    }
};
