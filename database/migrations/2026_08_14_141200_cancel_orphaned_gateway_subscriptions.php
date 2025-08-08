<?php

use App\Models\SuperAdmin\GlobalSubscription;
use App\Services\SuperAdmin\GatewaySubscriptionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAX_RUNTIME_SECONDS = 60;

    /**
     * Cancel remote gateway mandates that kept billing after a company left the paid plan.
     * Remote calls are time-boxed so migrate cannot hang on a slow or unreachable provider.
     */
    public function up(): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        $startedAt = microtime(true);

        try {
            $gateways = app(GatewaySubscriptionService::class);

            GlobalSubscription::query()
                ->with('company.package')
                ->whereNull('ends_at')
                ->whereNotIn('gateway_name', ['offline'])
                ->orderBy('id')
                ->chunkById(25, function ($subscriptions) use ($gateways, $startedAt) {
                    $allowRemote = !$this->overTimeBudget($startedAt);

                    foreach ($subscriptions as $subscription) {
                        try {
                            if (!$gateways->shouldStopRecurring($subscription)) {
                                continue;
                            }

                            if ($allowRemote && !$this->overTimeBudget($startedAt)) {
                                $gateways->cancel($subscription);
                            } else {
                                $this->endLocally($subscription);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Orphaned gateway subscription cancel failed during migrate', [
                                'global_subscription_id' => $subscription->id,
                                'gateway' => $subscription->gateway_name,
                                'company_id' => $subscription->company_id,
                                'message' => $e->getMessage(),
                            ]);

                            $this->endLocally($subscription);
                        }
                    }
                });
        } catch (\Throwable $e) {
            // Never block the rest of migrate if credentials, network, or models fail.
            Log::warning('Orphaned gateway subscription migration skipped', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        // Non-reversible billing fix.
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('global_subscriptions')
            && Schema::hasTable('companies')
            && Schema::hasTable('packages');
    }

    private function overTimeBudget(float $startedAt): bool
    {
        return (microtime(true) - $startedAt) >= self::MAX_RUNTIME_SECONDS;
    }

    private function endLocally(GlobalSubscription $subscription): void
    {
        if ($subscription->ends_at) {
            return;
        }

        try {
            $subscription->ends_at = now();
            $subscription->subscription_status = 'inactive';
            $subscription->save();
        } catch (\Throwable $e) {
            Log::warning('Failed to mark gateway subscription ended locally during migrate', [
                'global_subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
};
