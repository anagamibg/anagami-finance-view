<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayCredentials;
use App\Services\Worldpay\WorldpayClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorldpayClientTest extends TestCase
{

    public function test_checkout_payment_posts_session_without_card_data(): void
    {
        Http::fake([
            'try.access.worldpay.com/*' => Http::response([
                'outcome' => '3dsDeviceDataRequired',
                'deviceDataCollection' => [
                    'jwt' => 'jwt',
                    'url' => 'https://centinelapistag.cardinalcommerce.com/V1/Cruise/Collect',
                    'bin' => '400000',
                ],
                '_actions' => [
                    'supply3dsDeviceData' => [
                        'href' => 'https://try.access.worldpay.com/api/payments/abc/3dsDeviceData',
                        'method' => 'POST',
                    ],
                ],
            ], 201),
        ]);

        $credentials = new PaymentGatewayCredentials([
            'worldpay_username' => 'user',
            'worldpay_password' => 'pass',
            'worldpay_merchant_entity' => 'default',
            'worldpay_environment' => 'sandbox',
        ]);

        $body = WorldpayClient::fromCredentials($credentials)->createCardPayment(
            'wsi-9-abc123',
            'GBP',
            1050,
            'https://try.access.worldpay.com/sessions/example',
            ['channel' => 'browser'],
            'https://merchant.test/worldpay/3ds-return',
            'Acme',
            true
        );

        $this->assertSame('3dsDeviceDataRequired', $body['outcome']);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $request->url() === 'https://try.access.worldpay.com/api/payments'
                && ($payload['instruction']['paymentInstrument']['type'] ?? null) === 'checkout'
                && ($payload['instruction']['customerAgreement']['storedCardUsage'] ?? null) === 'first'
                && ($payload['instruction']['tokenCreation']['type'] ?? null) === 'worldpay'
                && ($payload['instruction']['threeDS']['challenge']['preference'] ?? null) === 'challengeMandated'
                && !isset($payload['instruction']['paymentInstrument']['cardNumber']);
        });
    }

    public function test_merchant_initiated_charge_omits_3ds(): void
    {
        Http::fake([
            'access.worldpay.com/*' => Http::response([
                'outcome' => 'authorized',
                'paymentId' => 'pay_mit',
                'schemeReference' => 'MCCOLXT1C0104',
            ], 201),
        ]);

        $credentials = new PaymentGatewayCredentials([
            'worldpay_username' => 'user',
            'worldpay_password' => 'pass',
            'worldpay_merchant_entity' => 'default',
            'worldpay_environment' => 'live',
        ]);

        $body = WorldpayClient::fromCredentials($credentials)->merchantInitiated(
            'wsi-9-mit',
            'GBP',
            1050,
            'https://access.worldpay.com/tokens/abc',
            'MCCOLXT1C0104',
            'Acme'
        );

        $this->assertSame('authorized', $body['outcome']);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return ($payload['instruction']['paymentInstrument']['type'] ?? null) === 'token'
                && ($payload['instruction']['customerAgreement']['storedCardUsage'] ?? null) === 'subsequent'
                && ($payload['instruction']['customerAgreement']['schemeReference'] ?? null) === 'MCCOLXT1C0104'
                && !isset($payload['instruction']['threeDS']);
        });
    }

    public function test_action_url_outside_worldpay_is_rejected(): void
    {
        $credentials = new PaymentGatewayCredentials([
            'worldpay_username' => 'user',
            'worldpay_password' => 'pass',
            'worldpay_environment' => 'sandbox',
        ]);

        $this->expectException(\RuntimeException::class);

        WorldpayClient::fromCredentials($credentials)->postAction('https://example.com/steal', []);
    }

}
