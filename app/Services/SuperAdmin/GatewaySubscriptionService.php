<?php

namespace App\Services\SuperAdmin;

use App\Models\Company;
use App\Models\SuperAdmin\GlobalInvoice;
use App\Models\SuperAdmin\GlobalPaymentGatewayCredentials;
use App\Models\SuperAdmin\GlobalSubscription;
use App\Models\SuperAdmin\PaypalInvoice;
use App\Traits\SuperAdmin\PaystackSettings;
use App\Traits\SuperAdmin\StripeSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PayPal\Api\Agreement;
use PayPal\Api\AgreementStateDescriptor;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Razorpay\Api\Api;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\controller as AnetController;

class GatewaySubscriptionService
{
    use StripeSettings, PaystackSettings;

    /**
     * Stop every remote recurring mandate for a company.
     * Local package changes do not cancel Stripe, PayPal, Razorpay, Paystack, PayFast, Authorize, Mollie or Worldpay.
     */
    public function cancelForCompany(int $companyId, ?int $exceptSubscriptionId = null, ?string $exceptGateway = null): int
    {
        $cancelled = app(MollieSubscriptionService::class)->cancelForCompany($companyId, $exceptSubscriptionId);

        $query = GlobalSubscription::where('company_id', $companyId)
            ->whereIn('gateway_name', ['razorpay', 'paystack', 'payfast', 'authorize', 'worldpay'])
            ->whereNull('ends_at');

        if ($exceptSubscriptionId) {
            $query->where('id', '!=', $exceptSubscriptionId);
        }

        if ($exceptGateway) {
            $query->where('gateway_name', '!=', $exceptGateway);
        }

        foreach ($query->get() as $subscription) {
            if ($this->cancel($subscription)) {
                $cancelled++;
            }
        }

        if ($exceptGateway !== 'stripe') {
            $cancelled += $this->cancelStripe($companyId);
        }

        if ($exceptGateway !== 'paypal') {
            $cancelled += $this->cancelPaypalAgreements($companyId);
        }

        return $cancelled;
    }

    public function cancel(GlobalSubscription $subscription): bool
    {
        return match ($subscription->gateway_name) {
            'mollie' => app(MollieSubscriptionService::class)->cancel($subscription),
            'stripe' => $this->cancelStripe((int) $subscription->company_id) > 0 || $this->markEnded($subscription),
            'paypal' => $this->cancelPaypalSubscription($subscription),
            'razorpay' => $this->cancelRazorpaySubscription($subscription),
            'paystack' => $this->cancelPaystackSubscription($subscription),
            'payfast' => $this->cancelPayfastSubscription($subscription),
            'authorize' => $this->cancelAuthorizeSubscription($subscription),
            default => $this->markEnded($subscription),
        };
    }

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

    private function cancelStripe(int $companyId): int
    {
        $this->setStripConfigs();
        $company = Company::find($companyId);
        $cancelled = 0;

        if (!$company) {
            return 0;
        }

        try {
            $this->setStripeHttpTimeout();

            foreach ($company->subscriptions as $subscription) {
                if ((method_exists($subscription, 'canceled') && $subscription->canceled())
                    || (method_exists($subscription, 'ended') && $subscription->ended())) {
                    continue;
                }

                if (method_exists($subscription, 'cancelNow')) {
                    $subscription->cancelNow();
                } else {
                    $subscription->cancel();
                }

                $cancelled++;
            }
        } catch (\Exception $e) {
            $this->logFailure('stripe', $companyId, $e->getMessage());
        }

        $this->markGatewayEnded($companyId, 'stripe');

        return $cancelled;
    }

    private function cancelPaypalAgreements(int $companyId): int
    {
        $agreementIds = GlobalInvoice::where('company_id', $companyId)
            ->where('gateway_name', 'paypal')
            ->whereNotNull('transaction_id')
            ->pluck('transaction_id')
            ->merge(
                PaypalInvoice::where('company_id', $companyId)
                    ->whereNotNull('transaction_id')
                    ->whereNull('end_on')
                    ->pluck('transaction_id')
            )
            ->unique()
            ->filter();

        $cancelled = 0;
        $apiContext = $this->paypalApiContext();

        if (!$apiContext) {
            $this->markGatewayEnded($companyId, 'paypal');

            return 0;
        }

        foreach ($agreementIds as $agreementId) {
            try {
                $agreement = new Agreement();
                $agreement->setId($agreementId);
                $descriptor = new AgreementStateDescriptor();
                $descriptor->setNote('Cancel the agreement');
                $agreement->cancel($descriptor, $apiContext);
                $cancelled++;
            } catch (\Exception $e) {
                $this->logFailure('paypal', $companyId, $e->getMessage(), ['agreement_id' => $agreementId]);
            }
        }

        $this->markGatewayEnded($companyId, 'paypal');

        PaypalInvoice::where('company_id', $companyId)
            ->whereNotNull('transaction_id')
            ->whereNull('end_on')
            ->update(['end_on' => now()]);

        return $cancelled;
    }

    private function cancelPaypalSubscription(GlobalSubscription $subscription): bool
    {
        $this->cancelPaypalAgreements((int) $subscription->company_id);

        return $this->markEnded($subscription);
    }

    private function cancelRazorpaySubscription(GlobalSubscription $subscription): bool
    {
        $subscriptionId = $subscription->transaction_id
            ?: GlobalInvoice::where('global_subscription_id', $subscription->id)->latest('id')->value('subscription_id');

        if ($subscriptionId) {
            try {
                $api = $this->razorpayApi();
                $remote = $api->subscription->fetch($subscriptionId);

                if (in_array($remote->status, ['active', 'authenticated', 'pending'], true)) {
                    $api->subscription->fetch($subscriptionId)->cancel(['cancel_at_cycle_end' => 0]);
                }
            } catch (\Exception $e) {
                $this->logFailure('razorpay', $subscription->company_id, $e->getMessage(), [
                    'razorpay_subscription_id' => $subscriptionId,
                ]);
            }
        }

        return $this->markEnded($subscription);
    }

    private function cancelPaystackSubscription(GlobalSubscription $subscription): bool
    {
        $this->setPaystackConfigs();
        $secret = config('paystack.secretKey');
        $code = $subscription->transaction_id;

        try {
            if (!$code && $subscription->customer_id) {
                $list = $this->timedHttp()
                    ->withToken($secret)
                    ->get('https://api.paystack.co/subscription', ['customer' => $subscription->customer_id]);

                $code = $list->json('data.0.subscription_code');
            }

            if ($code) {
                $remote = $this->timedHttp()->withToken($secret)->get('https://api.paystack.co/subscription/' . $code);
                $emailToken = $remote->json('data.email_token') ?: $subscription->token;

                if ($emailToken) {
                    $this->timedHttp()->withToken($secret)->post('https://api.paystack.co/subscription/disable', [
                        'code' => $code,
                        'token' => $emailToken,
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logFailure('paystack', $subscription->company_id, $e->getMessage(), [
                'subscription_code' => $code,
            ]);
        }

        return $this->markEnded($subscription);
    }

    private function cancelPayfastSubscription(GlobalSubscription $subscription): bool
    {
        $token = $subscription->transaction_id
            ?: GlobalInvoice::where('global_subscription_id', $subscription->id)->whereNotNull('token')->latest('id')->value('token');

        $credentials = GlobalPaymentGatewayCredentials::first();

        if ($token && $credentials) {
            $sandbox = $credentials->payfast_mode === 'sandbox';
            $merchantId = $sandbox ? $credentials->test_payfast_merchant_id : $credentials->payfast_merchant_id;
            $passphrase = $sandbox ? $credentials->test_payfast_passphrase : $credentials->payfast_passphrase;
            $timestamp = now()->format('Y-m-d\TH:i:s');
            $headers = [
                'merchant-id' => $merchantId,
                'version' => 'v1',
                'timestamp' => $timestamp,
            ];
            $headers['signature'] = $this->payfastApiSignature($headers, $passphrase);
            $url = 'https://api.payfast.co.za/subscriptions/' . $token . '/cancel' . ($sandbox ? '?testing=true' : '');

            try {
                $this->timedHttp()->withHeaders($headers)->put($url);
            } catch (\Exception $e) {
                $this->logFailure('payfast', $subscription->company_id, $e->getMessage(), ['token' => $token]);
            }
        }

        return $this->markEnded($subscription);
    }

    private function cancelAuthorizeSubscription(GlobalSubscription $subscription): bool
    {
        $subscriptionId = $subscription->transaction_id;

        if ($subscriptionId) {
            try {
                $credentials = GlobalPaymentGatewayCredentials::first();
                $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
                $merchantAuthentication->setName($credentials->authorize_api_login_id);
                $merchantAuthentication->setTransactionKey($credentials->authorize_transaction_key);

                $request = new AnetAPI\ARBCancelSubscriptionRequest();
                $request->setMerchantAuthentication($merchantAuthentication);
                $request->setRefId('ref' . time());
                $request->setSubscriptionId($subscriptionId);

                $controller = new AnetController\ARBCancelSubscriptionController($request);
                $environment = $credentials->authorize_environment === 'sandbox'
                    ? ANetEnvironment::SANDBOX
                    : ANetEnvironment::PRODUCTION;
                $controller->executeWithApiResponse($environment);
            } catch (\Exception $e) {
                $this->logFailure('authorize', $subscription->company_id, $e->getMessage(), [
                    'authorize_subscription_id' => $subscriptionId,
                ]);
            }
        }

        return $this->markEnded($subscription);
    }

    private function paypalApiContext(): ?ApiContext
    {
        $credentials = GlobalPaymentGatewayCredentials::first();

        if (!$credentials) {
            return null;
        }

        if ($credentials->paypal_mode === 'sandbox') {
            $clientId = $credentials->sandbox_paypal_client_id;
            $secret = $credentials->sandbox_paypal_secret;
        } else {
            $clientId = $credentials->paypal_client_id;
            $secret = $credentials->paypal_secret;
        }

        if (!$clientId || !$secret) {
            return null;
        }

        config(['paypal.settings.mode' => $credentials->paypal_mode]);
        $settings = config('paypal.settings', []);
        $settings['http.ConnectionTimeOut'] = 8;
        $apiContext = new ApiContext(new OAuthTokenCredential($clientId, $secret));
        $apiContext->setConfig($settings);

        return $apiContext;
    }

    private function razorpayApi(): Api
    {
        $credentials = GlobalPaymentGatewayCredentials::first();

        if ($credentials->razorpay_mode === 'test') {
            return new Api($credentials->test_razorpay_key, $credentials->test_razorpay_secret);
        }

        return new Api($credentials->live_razorpay_key, $credentials->live_razorpay_secret);
    }

    private function timedHttp()
    {
        return Http::timeout(8)->connectTimeout(3);
    }

    private function setStripeHttpTimeout(): void
    {
        if (!class_exists(\Stripe\HttpClient\CurlClient::class) || !class_exists(\Stripe\ApiRequestor::class)) {
            return;
        }

        $curl = new \Stripe\HttpClient\CurlClient();
        $curl->setTimeout(8);
        $curl->setConnectTimeout(3);
        \Stripe\ApiRequestor::setHttpClient($curl);
    }

    private function payfastApiSignature(array $headers, ?string $passphrase): string
    {
        $data = $headers;

        if ($passphrase) {
            $data['passphrase'] = $passphrase;
        }

        $query = '';

        foreach ($data as $key => $value) {
            if ($value !== '' && $value !== null) {
                $query .= $key . '=' . urlencode(trim((string) $value)) . '&';
            }
        }

        return md5(substr($query, 0, -1));
    }

    private function markGatewayEnded(int $companyId, string $gateway): void
    {
        GlobalSubscription::where('company_id', $companyId)
            ->where('gateway_name', $gateway)
            ->whereNull('ends_at')
            ->update([
                'subscription_status' => 'inactive',
                'ends_at' => now(),
            ]);
    }

    private function markEnded(GlobalSubscription $subscription): bool
    {
        $subscription->ends_at = now();
        $subscription->subscription_status = 'inactive';
        $subscription->save();

        return true;
    }

    private function logFailure(string $gateway, $companyId, string $message, array $context = []): void
    {
        Log::warning('Gateway subscription cancel failed', array_merge([
            'gateway' => $gateway,
            'company_id' => $companyId,
            'message' => $message,
        ], $context));
    }
}
