<?php

namespace App\Services\SuperAdmin;

use App\Models\Company;
use App\Models\SuperAdmin\GlobalInvoice;
use App\Models\SuperAdmin\GlobalSubscription;
use App\Models\SuperAdmin\Package;
use App\Models\User;
use App\Notifications\SuperAdmin\CompanyUpdatedPlan;
use App\Services\Worldpay\WorldpayAttempt;
use App\Services\Worldpay\WorldpaySupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class WorldpaySubscriptionService
{

    /**
     * @return array{0: string, 1: float}
     */
    public function priceFor(Package $package, string $type): array
    {
        $isLifetime = ($package->package ?? null) === 'lifetime' || $type === 'lifetime';
        $packageType = $isLifetime ? 'lifetime' : ($type === 'annual' ? 'annual' : 'monthly');
        $amount = $isLifetime
            ? (float) ($package->price ?? 0)
            : (float) ($packageType === 'annual' ? $package->annual_price : $package->monthly_price);

        return [$packageType, $amount];
    }

    /**
     * Apply a terminal Worldpay result to a SaaS subscription once.
     *
     * @return array{decision: string, message: string}
     */
    public function settle(string $reference, array $body, ?array $attempt = null): array
    {
        $normalized = WorldpaySupport::normalize($body);
        $parsed = WorldpaySupport::parseReference($reference);
        $attempt = $attempt ?: WorldpayAttempt::find($reference);
        $type = $attempt['type'] ?? ($parsed['type'] ?? null);

        if ($type !== 'subscription') {
            return ['decision' => 'ignore', 'message' => 'Unknown Worldpay reference.'];
        }

        $subscriptionId = (int) ($attempt['id'] ?? $parsed['id'] ?? 0);
        $subscription = GlobalSubscription::find($subscriptionId);

        if (!$subscription || $subscription->gateway_name !== 'worldpay') {
            return ['decision' => 'ignore', 'message' => 'Subscription not found for this Worldpay payment.'];
        }

        if (!empty($attempt['company_id']) && (int) $attempt['company_id'] !== (int) $subscription->company_id) {
            return ['decision' => 'ignore', 'message' => 'Subscription not found for this Worldpay payment.'];
        }

        if ($this->paidInvoice($reference)) {
            $this->release($subscription->id);

            return ['decision' => 'skip', 'message' => ''];
        }

        if (WorldpaySupport::isDeviceData($normalized['outcome']) || WorldpaySupport::isChallenge($normalized['outcome'])) {
            return ['decision' => 'ignore', 'message' => $normalized['message']];
        }

        if ($normalized['outcome'] === 'sentForAuthorization') {
            WorldpayAttempt::markOpen('subscription', $subscription->id, $reference);

            return ['decision' => 'pending', 'message' => __('modules.worldpay.processing')];
        }

        if (!WorldpaySupport::isSuccess($normalized['outcome']) && !WorldpaySupport::isFailed($normalized['outcome'])) {
            return ['decision' => 'ignore', 'message' => $normalized['message']];
        }

        $claim = 'worldpay_settle:' . $reference;

        if (!Cache::add($claim, 1, now()->addMinutes(5))) {
            if ($this->paidInvoice($reference)) {
                $this->release($subscription->id);

                return ['decision' => 'skip', 'message' => ''];
            }

            return ['decision' => 'pending', 'message' => __('modules.worldpay.processing')];
        }

        try {
            if (WorldpaySupport::isFailed($normalized['outcome'])) {
                Cache::forget($claim);
                $this->release($subscription->id);

                return ['decision' => 'failed', 'message' => $normalized['message']];
            }

            $subscription->refresh();

            if ($subscription->subscription_status !== 'active') {
                try {
                    app(GatewaySubscriptionService::class)->cancelForCompany((int) $subscription->company_id, (int) $subscription->id);
                } catch (\Throwable $exception) {
                    Log::warning('Worldpay could not cancel the previous gateway subscription.', [
                        'subscription_id' => $subscription->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $activated = false;
            $recorded = false;

            DB::transaction(function () use ($subscription, $reference, $attempt, $normalized, &$activated, &$recorded) {
                $locked = GlobalSubscription::where('id', $subscription->id)->lockForUpdate()->first();

                if (!$locked || $this->paidInvoice($reference)) {
                    $recorded = (bool) $locked;

                    return;
                }

                if ($locked->subscription_status === 'active') {
                    $this->recordRenewal($locked, $reference, $attempt ?: [], $normalized);
                    $recorded = true;

                    return;
                }

                $this->activate($locked, $reference, $attempt ?: [], $normalized);
                $activated = true;
                $recorded = true;
            });

            if (!$recorded && !$this->paidInvoice($reference)) {
                Cache::forget($claim);
                $this->release($subscription->id);

                return ['decision' => 'failed', 'message' => __('modules.worldpay.errorMessage')];
            }

            if ($activated) {
                $this->notifyPlanChange($subscription);
            }

            $this->release($subscription->id);

            return ['decision' => 'complete', 'message' => $normalized['message']];
        } catch (\Throwable $exception) {
            Cache::forget($claim);
            $this->release($subscription->id);
            Log::warning('Worldpay subscription settlement failed.', [
                'reference' => $reference,
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);

            return ['decision' => 'failed', 'message' => __('modules.worldpay.errorMessage')];
        }
    }

    private function activate(GlobalSubscription $subscription, string $reference, array $attempt, array $normalized): void
    {
        $package = Package::findOrFail($subscription->package_id);

        GlobalSubscription::where('company_id', $subscription->company_id)
            ->where('subscription_status', 'active')
            ->where('id', '!=', $subscription->id)
            ->update(['subscription_status' => 'inactive', 'ends_at' => now()]);

        $subscription->subscription_status = 'active';
        $subscription->subscribed_on_date = now();
        $subscription->ends_at = null;
        $subscription->transaction_id = $reference;
        $this->rememberCard($subscription, $attempt, $normalized);
        $subscription->save();

        $this->storeInvoice($subscription, $package, $reference, $this->amount($attempt, $normalized, $package, $subscription->package_type));

        $company = Company::findOrFail($subscription->company_id);
        $company->package_id = $subscription->package_id;
        $company->package_type = $subscription->package_type;
        $company->status = 'active';
        $company->licence_expire_on = null;
        $company->save();
    }

    private function notifyPlanChange(GlobalSubscription $subscription): void
    {
        $company = Company::find($subscription->company_id);

        if (!$company) {
            return;
        }

        try {
            $generatedBy = User::allSuperAdmin();
            $allAdmins = User::allAdmins($company->id);
            Notification::send($generatedBy, new CompanyUpdatedPlan($company, $subscription->package_id));
            Notification::send($allAdmins, new CompanyUpdatedPlan($company, $subscription->package_id));
        } catch (\Throwable $exception) {
            Log::warning('Worldpay plan-change notification failed.', [
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function recordRenewal(GlobalSubscription $subscription, string $reference, array $attempt, array $normalized): void
    {
        $package = Package::findOrFail($subscription->package_id);
        $this->rememberCard($subscription, $attempt, $normalized);
        $subscription->save();

        $this->storeInvoice($subscription, $package, $reference, $this->amount($attempt, $normalized, $package, $subscription->package_type));

        $company = Company::find($subscription->company_id);

        if ($company && $company->status !== 'active') {
            $company->status = 'active';
            $company->licence_expire_on = null;
            $company->save();
        }
    }

    private function storeInvoice(GlobalSubscription $subscription, Package $package, string $reference, float $amount): void
    {
        if ($this->paidInvoice($reference)) {
            return;
        }

        $invoice = new GlobalInvoice();
        $invoice->company_id = $subscription->company_id;
        $invoice->package_id = $subscription->package_id;
        $invoice->currency_id = $subscription->currency_id;
        $invoice->global_subscription_id = $subscription->id;
        $invoice->pay_date = now()->format('Y-m-d');
        $invoice->next_pay_date = $subscription->package_type === 'lifetime'
            ? null
            : now()->{($subscription->package_type === 'monthly') ? 'addMonth' : 'addYear'}()->format('Y-m-d');
        $invoice->status = 'active';
        $invoice->package_type = $subscription->package_type;
        $invoice->gateway_name = 'worldpay';
        $invoice->total = $amount;
        $invoice->transaction_id = $reference;
        $invoice->save();
    }

    private function rememberCard(GlobalSubscription $subscription, array $attempt, array $normalized): void
    {
        if (empty($attempt['store_card']) && $subscription->subscription_status !== 'active') {
            return;
        }

        if (!empty($normalized['token_href'])) {
            $subscription->worldpay_token = $normalized['token_href'];
        }

        if (!empty($normalized['scheme_reference'])) {
            $subscription->worldpay_scheme_reference = $normalized['scheme_reference'];
        }

        if (!empty($attempt['store_card']) && (empty($normalized['token_href']) || empty($normalized['scheme_reference'])) && empty($subscription->worldpay_token)) {
            Log::warning('Worldpay subscription was authorized without a token or scheme reference.', [
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    private function amount(array $attempt, array $normalized, Package $package, string $packageType): float
    {
        if (isset($attempt['amount'])) {
            return (float) $attempt['amount'];
        }

        if (($normalized['minor'] ?? 0) > 0 && !empty($normalized['currency'])) {
            return WorldpaySupport::majorAmount((int) $normalized['minor'], (string) $normalized['currency']);
        }

        return $this->priceFor($package, $packageType)[1];
    }

    private function paidInvoice(string $reference): bool
    {
        return GlobalInvoice::where('gateway_name', 'worldpay')
            ->where('transaction_id', $reference)
            ->where('status', 'active')
            ->exists();
    }

    private function release(int $subscriptionId): void
    {
        WorldpayAttempt::clearOpen('subscription', $subscriptionId);
        WorldpayAttempt::release('subscription', $subscriptionId);
    }

}
