<?php

namespace Modules\Promotion\Console;

use Illuminate\Console\Command;
use Lunar\Models\Customer;
use Modules\Promotion\Services\MembershipService;

/**
 * One-off (idempotent) re-sync of every customer's membership tier.
 *
 * Membership spend previously ignored `payment-offline`, so customers who only
 * ever paid cash on delivery never advanced a tier. After that fix, run this
 * once to settle existing customers onto the tier their history earns them.
 * Safe to re-run: syncCustomer only attaches/detaches when the tier changed.
 */
class BackfillMembershipTiers extends Command
{
    protected $signature = 'membership:backfill {--dry-run : Report changes without writing}';

    protected $description = 'Re-sync every customer into the membership tier their lifetime spend earns';

    public function handle(MembershipService $membership): int
    {
        if (! $membership->enabled()) {
            $this->warn('Membership is disabled — nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        Customer::query()->chunkById(200, function ($customers) use ($membership, $dryRun, &$changed) {
            foreach ($customers as $customer) {
                $before = $membership->currentTier($customer)['handle'] ?? null;
                $after = $membership->tierForSpend($membership->lifetimeSpend($customer))['handle'] ?? null;

                if ($before === $after) {
                    continue;
                }

                $changed++;
                $this->line(sprintf(
                    '  #%d %s: %s → %s',
                    $customer->id,
                    trim($customer->first_name.' '.$customer->last_name),
                    $before ?? 'none',
                    $after ?? 'none',
                ));

                if (! $dryRun) {
                    $membership->syncCustomer($customer);
                }
            }
        });

        $this->info($dryRun
            ? "Dry run: {$changed} customer(s) would change tier."
            : "Backfilled {$changed} customer(s).");

        return self::SUCCESS;
    }
}
