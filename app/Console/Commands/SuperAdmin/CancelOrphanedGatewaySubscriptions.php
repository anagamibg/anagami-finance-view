<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\SuperAdmin\GlobalSubscription;
use App\Services\SuperAdmin\GatewaySubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CancelOrphanedGatewaySubscriptions extends Command
{
    protected $signature = 'billing:cancel-orphaned-subscriptions
                            {--dry-run : List orphaned subscriptions without cancelling them}
                            {--company= : Limit to a single company id}
                            {--gateway= : Limit to one gateway (mollie, stripe, paypal, razorpay, paystack, payfast, authorize)}
                            {--list-all : Show every uncancelled local gateway subscription (does not cancel)}';

    protected $description = 'Cancel remote recurring subscriptions that are still charging after the company left the paid plan.';

    public function handle(GatewaySubscriptionService $gateways): int
    {
        $query = GlobalSubscription::with('company.package')
            ->whereNotIn('gateway_name', ['offline'])
            ->whereNull('ends_at');

        if ($this->option('company')) {
            $query->where('company_id', $this->option('company'));
        }

        if ($this->option('gateway')) {
            $query->where('gateway_name', $this->option('gateway'));
        }

        $openSubscriptions = $query->get();

        if ($this->option('list-all')) {
            $this->renderTable($openSubscriptions);
            $this->info($openSubscriptions->count() . ' uncancelled local gateway subscription(s).');

            return self::SUCCESS;
        }

        $orphans = $openSubscriptions->filter(function (GlobalSubscription $subscription) use ($gateways) {
            return $gateways->shouldStopRecurring($subscription);
        });

        if ($orphans->isEmpty()) {
            $this->info('No orphaned gateway subscriptions found.');

            return self::SUCCESS;
        }

        $this->renderTable($orphans);

        if ($this->option('dry-run')) {
            $this->warn('Dry run: ' . $orphans->count() . ' subscription(s) would be cancelled.');

            return self::SUCCESS;
        }

        foreach ($orphans as $subscription) {
            $gateways->cancel($subscription);
        }

        $this->info('Cancelled ' . $orphans->count() . ' gateway subscription(s).');

        return self::SUCCESS;
    }

    private function renderTable(Collection $subscriptions): void
    {
        $this->table(
            ['Local ID', 'Gateway', 'Company ID', 'Company', 'Current package', 'Remote id', 'Local status'],
            $subscriptions->map(function (GlobalSubscription $subscription) {
                return [
                    $subscription->id,
                    $subscription->gateway_name,
                    $subscription->company_id,
                    $subscription->company?->company_name,
                    $subscription->company?->package?->name,
                    $subscription->transaction_id ?: $subscription->customer_id,
                    $subscription->subscription_status,
                ];
            })->all()
        );
    }
}
