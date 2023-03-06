<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Helper\Reply;
use App\Http\Controllers\Controller;
use App\Models\SuperAdmin\GlobalPaymentGatewayCredentials;
use App\Models\SuperAdmin\GlobalSubscription;
use App\Models\SuperAdmin\Package;
use App\Services\SuperAdmin\WorldpaySubscriptionService;
use App\Services\Worldpay\WorldpayAttempt;
use App\Services\Worldpay\WorldpayClient;
use App\Services\Worldpay\WorldpaySupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WorldpayController extends Controller
{

    public function pay(Request $request, WorldpaySubscriptionService $billing)
    {
        $request->validate([
            'plan_id' => 'required|integer',
            'type' => 'required|in:monthly,annual,lifetime',
            'session_href' => 'required|string|max:2048',
            'device_data' => 'nullable|array',
            'card_agreement' => 'nullable|in:0,1',
        ]);

        $company = company();
        $credentials = $this->credentials();

        if (!$company || !$credentials) {
            return Reply::error(__('modules.worldpay.unavailableBilling'));
        }

        if (!WorldpaySupport::allowedActionUrl((string) $request->session_href)) {
            return Reply::error(__('modules.worldpay.errorMessage'));
        }

        $package = Package::with('currency')->findOrFail($request->plan_id);

        if ($package->default === 'yes' || $package->is_free) {
            return Reply::error(__('modules.worldpay.unavailableBilling'));
        }

        [$packageType, $amount] = $billing->priceFor($package, (string) $request->type);
        $storeCard = $packageType !== 'lifetime';

        if ($amount <= 0) {
            return Reply::error(__('modules.worldpay.unavailableBilling'));
        }

        if ($storeCard && $request->card_agreement != '1') {
            return Reply::error(__('modules.worldpay.agreementRequiredSubscription'));
        }

        $subscription = GlobalSubscription::where('gateway_name', 'worldpay')
            ->where('company_id', $company->id)
            ->where('subscription_status', 'inactive')
            ->whereNull('ends_at')
            ->latest()
            ->first() ?: new GlobalSubscription();

        $subscription->company_id = $company->id;
        $subscription->package_id = $package->id;
        $subscription->currency_id = $package->currency_id ?: global_setting()->currency_id;
        $subscription->package_type = $packageType;
        $subscription->gateway_name = 'worldpay';
        $subscription->subscription_status = 'inactive';
        $subscription->subscribed_on_date = now();
        $subscription->ends_at = null;
        $subscription->save();

        if (WorldpayAttempt::openReference('subscription', $subscription->id)) {
            return Reply::error(__('modules.worldpay.processing'));
        }

        $reference = WorldpaySupport::reference('subscription', $subscription->id);

        if (!WorldpayAttempt::acquire('subscription', $subscription->id, $reference)) {
            return Reply::error(__('modules.worldpay.processing'));
        }

        $currency = strtoupper((string) (optional($package->currency)->currency_code ?: optional(global_setting()->currency)->currency_code ?: 'USD'));
        $attempt = [
            'type' => 'subscription',
            'id' => $subscription->id,
            'company_id' => (int) $company->id,
            'amount' => $amount,
            'currency' => $currency,
            'package_id' => $package->id,
            'package_type' => $packageType,
            'store_card' => $storeCard,
        ];
        WorldpayAttempt::store($reference, $attempt);

        try {
            $body = WorldpayClient::fromCredentials($credentials)->createCardPayment(
                $reference,
                $currency,
                WorldpaySupport::minorAmount($amount, $currency),
                (string) $request->session_href,
                WorldpaySupport::deviceData((array) $request->input('device_data', [])),
                route('worldpay.3ds_return', ['reference' => $reference]),
                trim($package->name . ' ' . $company->company_name),
                $storeCard
            );
        } catch (RuntimeException $exception) {
            WorldpayAttempt::release('subscription', $subscription->id);
            Log::warning('Worldpay subscription payment could not be started.', [
                'reference' => $reference,
                'message' => $exception->getMessage(),
            ]);

            return Reply::error($exception->getMessage());
        } catch (\Throwable $exception) {
            WorldpayAttempt::release('subscription', $subscription->id);
            Log::warning('Worldpay subscription payment could not be started.', [
                'reference' => $reference,
                'message' => $exception->getMessage(),
            ]);

            return Reply::error(__('modules.worldpay.errorMessage'));
        }

        return $this->respond($reference, $body, $attempt);
    }

    public function supplyDeviceData(Request $request)
    {
        $request->validate([
            'reference' => 'required|string|max:64',
            'collection_reference' => 'required|string|max:128',
        ]);

        return $this->continueAction(
            (string) $request->reference,
            'supply_href',
            ['collectionReference' => (string) $request->collection_reference]
        );
    }

    public function completeChallenge(Request $request)
    {
        $request->validate([
            'reference' => 'required|string|max:64',
        ]);

        return $this->continueAction((string) $request->reference, 'complete_href', []);
    }

    public function handleGatewayWebhook(Request $request, $hash)
    {
        $globalHash = (string) optional(global_setting())->hash;

        if ($globalHash === '' || !hash_equals($globalHash, (string) $hash)) {
            return response('Unknown account', 404);
        }

        $credentials = GlobalPaymentGatewayCredentials::first();
        $secret = (string) ($credentials->worldpay_webhook_secret ?? '');

        if (!WorldpaySupport::signatureMatches($request->getContent(), $request->header('Event-Signature'), $secret)) {
            return response('Invalid signature', 400);
        }

        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return response('Invalid payload', 400);
        }

        $reference = (string) ($payload['eventDetails']['transactionReference'] ?? '');
        $parsed = WorldpaySupport::parseReference($reference);

        if (!$parsed || $parsed['type'] !== 'subscription') {
            return response('Ignored', 200);
        }

        $attempt = WorldpayAttempt::find($reference) ?: [
            'type' => 'subscription',
            'id' => $parsed['id'],
        ];

        app(WorldpaySubscriptionService::class)->settle($reference, $payload, $attempt);

        return response('OK', 200);
    }

    private function continueAction(string $reference, string $hrefKey, array $body)
    {
        $attempt = WorldpayAttempt::find($reference);
        $company = company();

        if (!$attempt || !$company || (int) $attempt['company_id'] !== (int) $company->id || ($attempt['type'] ?? null) !== 'subscription') {
            return Reply::error(__('modules.worldpay.sessionExpired'));
        }

        $href = $attempt[$hrefKey] ?? null;
        $credentials = $this->credentials();

        if (!$credentials || !$href) {
            return Reply::error(__('modules.worldpay.sessionExpired'));
        }

        try {
            $response = WorldpayClient::fromCredentials($credentials)->postAction($href, $body);
        } catch (RuntimeException $exception) {
            return Reply::error($exception->getMessage());
        } catch (\Throwable $exception) {
            Log::warning('Worldpay subscription action failed.', [
                'reference' => $reference,
                'message' => $exception->getMessage(),
            ]);

            return Reply::error(__('modules.worldpay.errorMessage'));
        }

        return $this->respond($reference, $response, $attempt);
    }

    private function respond(string $reference, array $body, array $attempt)
    {
        $normalized = WorldpaySupport::normalize($body);

        if (WorldpaySupport::isDeviceData($normalized['outcome'])) {
            $href = $body['_actions']['supply3dsDeviceData']['href'] ?? null;

            if (!WorldpaySupport::allowedActionUrl($href)) {
                WorldpayAttempt::release($attempt['type'], $attempt['id']);

                return Reply::error(__('modules.worldpay.errorMessage'));
            }

            WorldpayAttempt::merge($reference, ['supply_href' => $href]);

            return [
                'status' => 'action',
                'action' => 'device_data',
                'reference' => $reference,
                'deviceDataCollection' => [
                    'jwt' => $body['deviceDataCollection']['jwt'] ?? '',
                    'url' => $body['deviceDataCollection']['url'] ?? '',
                    'bin' => $body['deviceDataCollection']['bin'] ?? '',
                ],
            ];
        }

        if (WorldpaySupport::isChallenge($normalized['outcome'])) {
            $href = $body['_actions']['complete3dsChallenge']['href'] ?? null;

            if (!WorldpaySupport::allowedActionUrl($href)) {
                WorldpayAttempt::release($attempt['type'], $attempt['id']);

                return Reply::error(__('modules.worldpay.errorMessage'));
            }

            WorldpayAttempt::merge($reference, ['complete_href' => $href]);

            return [
                'status' => 'action',
                'action' => 'challenge',
                'reference' => $reference,
                'challenge' => [
                    'jwt' => $body['challenge']['jwt'] ?? '',
                    'url' => $body['challenge']['url'] ?? '',
                ],
            ];
        }

        $settlement = app(WorldpaySubscriptionService::class)->settle($reference, $body, $attempt);

        if ($settlement['decision'] === 'complete' || $settlement['decision'] === 'skip') {
            session()->forget('company');
            $paidCompany = company();
            session()->put('success', __('superadmin.paymentSuccessfullyDone', [
                'package' => optional(optional($paidCompany)->package)->name ?: '',
                'planType' => optional($paidCompany)->package_type ?: ($attempt['package_type'] ?? ''),
            ]));

            return Reply::redirect(route('billing.index'));
        }

        if ($settlement['decision'] === 'pending') {
            session()->put('success', __('modules.worldpay.processing'));

            return Reply::redirect(route('billing.index'));
        }

        // AFV (upgrade 6.0.19): an "ignore" result (Worldpay error body, unknown outcome) kept the 15-minute lock,
        // so the company could not retry the payment; "failed" is already released by the service
        WorldpayAttempt::release($attempt['type'], $attempt['id']);

        return Reply::error($settlement['message'] ?: __('modules.worldpay.errorMessage'));
    }

    private function credentials(): ?GlobalPaymentGatewayCredentials
    {
        $credentials = GlobalPaymentGatewayCredentials::first();

        if (!$credentials || $credentials->worldpay_status !== 'active' || empty($credentials->worldpay_checkout_id)) {
            return null;
        }

        return $credentials;
    }

}
