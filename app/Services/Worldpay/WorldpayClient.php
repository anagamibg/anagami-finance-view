<?php

namespace App\Services\Worldpay;

use App\Models\PaymentGatewayCredentials;
use App\Models\SuperAdmin\GlobalPaymentGatewayCredentials;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WorldpayClient
{

    private const MEDIA_TYPE = 'application/vnd.worldpay.payments-v7+json';

    public function __construct(
        private string $username,
        private string $password,
        private string $merchantEntity,
        private bool $sandbox
    ) {
    }

    public static function fromCredentials(PaymentGatewayCredentials|GlobalPaymentGatewayCredentials $credentials): self
    {
        $username = (string) ($credentials->worldpay_username ?: config('services.worldpay.username'));
        $password = (string) ($credentials->worldpay_password ?: config('services.worldpay.password'));
        $entity = (string) ($credentials->worldpay_merchant_entity ?: config('services.worldpay.merchant_entity') ?: 'default');

        if ($username === '' || $password === '') {
            // AFV (upgrade 6.0.19): the message reaches the payer through Reply::error() - translated (S7)
            throw new RuntimeException(__('ui-billing.worldpay.credentials_incomplete'));
        }

        return new self(
            $username,
            $password,
            $entity,
            ($credentials->worldpay_environment ?: config('services.worldpay.environment')) !== 'live'
        );
    }

    public function checkoutScriptUrl(): string
    {
        return $this->baseUrl() . '/access-checkout/v2/checkout.js';
    }

    public function baseUrl(): string
    {
        return $this->sandbox ? 'https://try.access.worldpay.com' : 'https://access.worldpay.com';
    }

    public function createCardPayment(
        string $reference,
        string $currency,
        int $minorAmount,
        string $sessionHref,
        array $deviceData,
        string $returnUrl,
        string $narrative,
        bool $storeCard
    ): array {
        $instruction = [
            'method' => 'card',
            'requestAutoSettlement' => ['enabled' => true],
            'narrative' => ['line1' => WorldpaySupport::narrative($narrative)],
            'value' => [
                'currency' => strtoupper($currency),
                'amount' => $minorAmount,
            ],
            'paymentInstrument' => [
                'type' => 'checkout',
                'sessionHref' => $sessionHref,
            ],
            'threeDS' => [
                'type' => 'integrated',
                'mode' => 'always',
                'challenge' => [
                    'returnUrl' => $returnUrl,
                    'preference' => $storeCard ? 'challengeMandated' : 'noPreference',
                ],
                'deviceData' => $deviceData,
            ],
        ];

        if ($storeCard) {
            $instruction['customerAgreement'] = [
                'type' => 'subscription',
                'storedCardUsage' => 'first',
            ];
            $instruction['tokenCreation'] = ['type' => 'worldpay'];
        }

        return $this->post($this->baseUrl() . '/api/payments', [
            'transactionReference' => $reference,
            'merchant' => ['entity' => $this->merchantEntity],
            'instruction' => $instruction,
        ]);
    }

    public function merchantInitiated(
        string $reference,
        string $currency,
        int $minorAmount,
        string $tokenHref,
        string $schemeReference,
        string $narrative
    ): array {
        return $this->post($this->baseUrl() . '/api/payments', [
            'transactionReference' => $reference,
            'merchant' => ['entity' => $this->merchantEntity],
            'instruction' => [
                'method' => 'card',
                'requestAutoSettlement' => ['enabled' => true],
                'narrative' => ['line1' => WorldpaySupport::narrative($narrative)],
                'value' => [
                    'currency' => strtoupper($currency),
                    'amount' => $minorAmount,
                ],
                'paymentInstrument' => [
                    'type' => 'token',
                    'href' => $tokenHref,
                ],
                'customerAgreement' => [
                    'type' => 'subscription',
                    'storedCardUsage' => 'subsequent',
                    'schemeReference' => $schemeReference,
                ],
            ],
        ]);
    }

    public function postAction(string $url, array $body): array
    {
        if (!WorldpaySupport::allowedActionUrl($url)) {
            // AFV (upgrade 6.0.19): translated, see fromCredentials()
            throw new RuntimeException(__('ui-billing.worldpay.action_url_rejected'));
        }

        return $this->post($url, $body);
    }

    private function post(string $url, array $body): array
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->withHeaders(['Accept' => self::MEDIA_TYPE])
            ->withBody(json_encode($body), self::MEDIA_TYPE)
            ->timeout(45)
            ->post($url);

        return $this->decode($response);
    }

    private function decode(Response $response): array
    {
        $decoded = $response->json();

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $decoded['_httpStatus'] = $response->status();

        return $decoded;
    }

}
