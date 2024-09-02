<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\Company;
use App\Models\SuperAdmin\GlobalInvoice;
use App\Models\SuperAdmin\GlobalPaymentGatewayCredentials;
use App\Models\SuperAdmin\GlobalSubscription;
use App\Models\SuperAdmin\Package;
use App\Services\SuperAdmin\GatewaySubscriptionService;
use App\Services\SuperAdmin\WorldpaySubscriptionService;
use App\Services\Worldpay\WorldpayAttempt;
use App\Services\Worldpay\WorldpayClient;
use App\Services\Worldpay\WorldpaySupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ChargeWorldpaySubscriptions extends Command
{

    protected $signature = 'worldpay-subscription-charge';

    protected $description = 'Charge stored Worldpay cards for due SaaS subscriptions.';

    public function handle(WorldpaySubscriptionService $billing, GatewaySubscriptionService $gateways): int
    {
        $credentials = GlobalPaymentGatewayCredentials::first();

        if (!$credentials || $credentials->worldpay_status !== 'active') {
            return self::SUCCESS;
        }

        $subscriptions = GlobalSubscription::where('gateway_name', 'worldpay')
            ->where('subscription_status', 'active')
            ->whereNull('ends_at')
            ->whereNotNull('worldpay_token')
            ->whereNotNull('worldpay_scheme_reference')
            ->where('package_type', '!=', 'lifetime')
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->chargeOne($subscription, $credentials, $billing, $gateways);
        }

        return self::SUCCESS;
    }

    private function chargeOne(
        GlobalSubscription $subscription,
        GlobalPaymentGatewayCredentials $credentials,
        WorldpaySubscriptionService $billing,
        GatewaySubscriptionService $gateways
    ): void {
        if ($gateways->shouldStopRecurring($subscription)) {
            $gateways->cancel($subscription);

            return;
        }

        $invoice = GlobalInvoice::where('global_subscription_id', $subscription->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$invoice || !$invoice->next_pay_date || $invoice->next_pay_date->isFuture()) {
            return;
        }

        if (WorldpayAttempt::openReference('subscription', $subscription->id)) {
            return;
        }

        $package = Package::with('currency')->find($subscription->package_id);
        $company = Company::find($subscription->company_id);

        if (!$package || !$company) {
            return;
        }

        [, $amount] = $billing->priceFor($package, (string) $subscription->package_type);

        if ($amount <= 0) {
            return;
        }

        $reference = WorldpaySupport::reference('subscription', $subscription->id);

        if (!WorldpayAttempt::acquire('subscription', $subscription->id, $reference)) {
            return;
        }

        $currency = strtoupper((string) (optional($package->currency)->currency_code ?: 'USD'));
        $attempt = [
            'type' => 'subscription',
            'id' => $subscription->id,
            'company_id' => (int) $subscription->company_id,
            'amount' => $amount,
            'currency' => $currency,
            'package_id' => $package->id,
            'package_type' => $subscription->package_type,
            'store_card' => true,
        ];
        WorldpayAttempt::store($reference, $attempt);

        try {
            $body = WorldpayClient::fromCredentials($credentials)->merchantInitiated(
                $reference,
                $currency,
                WorldpaySupport::minorAmount($amount, $currency),
                (string) $subscription->worldpay_token,
                (string) $subscription->worldpay_scheme_reference,
                (string) ($company->company_name ?: $package->name)
            );
            $settlement = $billing->settle($reference, $body, $attempt);
            $this->line('Worldpay subscription #' . $subscription->id . ' ' . $settlement['decision']);
        } catch (RuntimeException $exception) {
            WorldpayAttempt::release('subscription', $subscription->id);
            Log::warning('Worldpay subscription renewal failed.', [
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            WorldpayAttempt::release('subscription', $subscription->id);
            Log::warning('Worldpay subscription renewal failed.', [
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

}
