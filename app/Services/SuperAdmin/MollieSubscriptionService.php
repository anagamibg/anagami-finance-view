<?php

namespace App\Services\SuperAdmin;

use App\Models\Company;
use App\Models\SuperAdmin\GlobalInvoice;
use App\Models\SuperAdmin\GlobalSubscription;
use App\Traits\SuperAdmin\MollieSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MollieSubscriptionService
{
    use MollieSettings;

    /**
     * Cancel every open Mollie recurring subscription for a company at Mollie,
     * then mark the local records as ended. Local-only status changes do not stop billing.
     */
    public function cancelForCompany(int $companyId, ?int $exceptSubscriptionId = null): int
    {
        $query = GlobalSubscription::where('company_id', $companyId)
            ->where('gateway_name', 'mollie')
            ->whereNull('ends_at');

        if ($exceptSubscriptionId) {
            $query->where('id', '!=', $exceptSubscriptionId);
        }

        $cancelled = 0;

        foreach ($query->get() as $subscription) {
            if ($this->cancel($subscription)) {
                $cancelled++;
            }
        }

        return $cancelled;
    }

    /**
     * Cancel a single Mollie subscription at the gateway.
     * Already-cancelled / missing remote subscriptions are still closed locally.
     */
    public function cancel(GlobalSubscription $subscription): bool
    {
        if ($subscription->gateway_name !== 'mollie') {
            return false;
        }

        $this->setMollieConfigs();

        $customerId = $subscription->customer_id;
        $apiKey = config('mollie.key');

        if ($customerId && $apiKey) {
            try {
                $this->cancelMollieRemote($apiKey, $customerId, $this->resolveMollieSubscriptionId($subscription));
            } catch (\Exception $e) {
                Log::warning('Mollie subscription cancel failed', [
                    'company_id' => $subscription->company_id,
                    'global_subscription_id' => $subscription->id,
                    'customer_id' => $customerId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $subscription->ends_at = now();
        $subscription->subscription_status = 'inactive';
        $subscription->save();

        return true;
    }

    private function cancelMollieRemote(string $apiKey, string $customerId, ?string $subscriptionId): void
    {
        $http = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(8)
            ->connectTimeout(3);

        if ($subscriptionId) {
            $http->delete('https://api.mollie.com/v2/customers/' . $customerId . '/subscriptions/' . $subscriptionId);

            return;
        }

        $list = $http->get('https://api.mollie.com/v2/customers/' . $customerId . '/subscriptions', ['limit' => 10]);
        $remotes = $list->json('_embedded.subscriptions') ?? [];

        foreach ($remotes as $remote) {
            if (!in_array($remote['status'] ?? '', ['active', 'pending'], true) || empty($remote['id'])) {
                continue;
            }

            $http->delete('https://api.mollie.com/v2/customers/' . $customerId . '/subscriptions/' . $remote['id']);
        }
    }

    /**
     * Recurring Mollie charges must stop if the tenant already left that paid plan.
     */
    public function shouldStopRecurring(GlobalSubscription $subscription): bool
    {
        if ($subscription->subscription_status === 'inactive' || $subscription->ends_at) {
            return true;
        }

        $company = Company::with('package')->find($subscription->company_id);

        if (!$company || !$company->package) {
            return false;
        }

        if ((int) $company->package_id !== (int) $subscription->package_id) {
            return true;
        }

        return (bool) $company->package->is_free || $company->package->default === 'yes';
    }

    private function resolveMollieSubscriptionId(GlobalSubscription $subscription): ?string
    {
        if ($this->isMollieSubscriptionId($subscription->transaction_id)) {
            return $subscription->transaction_id;
        }

        $token = GlobalInvoice::where('global_subscription_id', $subscription->id)
            ->whereNotNull('token')
            ->latest('id')
            ->value('token');

        return $this->isMollieSubscriptionId($token) ? $token : null;
    }

    private function isMollieSubscriptionId(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, 'sub_');
    }
}
