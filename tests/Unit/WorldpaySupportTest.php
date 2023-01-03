<?php

namespace Tests\Unit;

use App\Services\Worldpay\WorldpaySupport;
use PHPUnit\Framework\TestCase;

class WorldpaySupportTest extends TestCase
{

    public function test_minor_amounts_follow_currency_exponents(): void
    {
        $this->assertSame(1050, WorldpaySupport::minorAmount(10.50, 'gbp'));
        $this->assertSame(1050, WorldpaySupport::minorAmount(1050, 'JPY'));
        $this->assertSame(1234, WorldpaySupport::minorAmount(1.234, 'KWD'));
        $this->assertSame(10.5, WorldpaySupport::majorAmount(1050, 'GBP'));
    }

    public function test_narrative_is_short_and_alphanumeric(): void
    {
        $this->assertSame('Acme Ltd', WorldpaySupport::narrative('Acme Ltd!!!'));
        $this->assertSame(24, strlen(WorldpaySupport::narrative(str_repeat('A', 40))));
        $this->assertSame('Payment', WorldpaySupport::narrative('***'));
    }

    public function test_reference_round_trip(): void
    {
        $reference = WorldpaySupport::reference('invoice', 42);
        $subscription = WorldpaySupport::reference('subscription', 7);

        $this->assertSame(['type' => 'invoice', 'id' => 42], WorldpaySupport::parseReference($reference));
        $this->assertSame(['type' => 'subscription', 'id' => 7], WorldpaySupport::parseReference($subscription));
        $this->assertNull(WorldpaySupport::parseReference('not-a-reference'));
    }

    public function test_already_paid_invoice_cannot_start_another_charge(): void
    {
        $this->assertFalse(WorldpaySupport::canStartCharge(0, 'unpaid'));
        $this->assertFalse(WorldpaySupport::canStartCharge(25, 'paid'));
        $this->assertTrue(WorldpaySupport::canStartCharge(25, 'partial'));
    }

    public function test_outcomes_cover_success_decline_and_3ds(): void
    {
        $this->assertTrue(WorldpaySupport::isSuccess('authorized'));
        $this->assertTrue(WorldpaySupport::isSuccess('sentForSettlement'));
        $this->assertTrue(WorldpaySupport::isFailed('refused'));
        $this->assertTrue(WorldpaySupport::isDeviceData('3dsDeviceDataRequired'));
        $this->assertTrue(WorldpaySupport::isChallenge('3dsChallenged'));
    }

    public function test_duplicate_webhook_does_not_record_again(): void
    {
        $this->assertSame('skip', WorldpaySupport::recordDecision('complete', 'authorized'));
        $this->assertSame('skip', WorldpaySupport::recordDecision('complete', 'sentForSettlement'));
        $this->assertSame('complete', WorldpaySupport::recordDecision(null, 'authorized'));
        $this->assertSame('complete', WorldpaySupport::recordDecision('pending', 'sentForSettlement'));
        $this->assertSame('failed', WorldpaySupport::recordDecision(null, 'refused'));
        $this->assertSame('pending', WorldpaySupport::recordDecision(null, 'sentForAuthorization'));
        $this->assertSame('ignore', WorldpaySupport::recordDecision(null, '3dsChallenged'));
    }

    public function test_webhook_signature_must_match(): void
    {
        $body = '{"eventId":"abc"}';
        $secret = 'shared-secret';
        $hex = '1/SHA256/' . hash_hmac('sha256', $body, $secret);
        $base64 = '2/SHA256/' . base64_encode(hash_hmac('sha256', $body, $secret, true));

        $this->assertTrue(WorldpaySupport::signatureMatches($body, $hex, $secret));
        $this->assertTrue(WorldpaySupport::signatureMatches($body, $hex . ',' . $base64, $secret));
        $this->assertFalse(WorldpaySupport::signatureMatches($body, '1/SHA256/nope', $secret));
        $this->assertFalse(WorldpaySupport::signatureMatches($body, null, $secret));
        $this->assertFalse(WorldpaySupport::signatureMatches($body, $hex, ''));
    }

    public function test_action_urls_stay_on_worldpay(): void
    {
        $this->assertTrue(WorldpaySupport::allowedActionUrl('https://try.access.worldpay.com/api/payments/abc/3dsDeviceData'));
        $this->assertTrue(WorldpaySupport::allowedActionUrl('https://access.worldpay.com/api/payments/abc/3dsChallenges'));
        $this->assertFalse(WorldpaySupport::allowedActionUrl('http://try.access.worldpay.com/api/payments/abc'));
        $this->assertFalse(WorldpaySupport::allowedActionUrl('https://example.com/api/payments/abc'));
    }

    public function test_normalize_reads_api_and_webhook_payloads(): void
    {
        $api = WorldpaySupport::normalize([
            'outcome' => 'authorized',
            'paymentId' => 'pay_1',
            'schemeReference' => 'MCCOLXT1C0104',
            'token' => ['href' => 'https://try.access.worldpay.com/tokens/abc'],
            'value' => ['currency' => 'GBP', 'amount' => 1050],
        ]);

        $this->assertSame('authorized', $api['outcome']);
        $this->assertSame('MCCOLXT1C0104', $api['scheme_reference']);
        $this->assertSame('https://try.access.worldpay.com/tokens/abc', $api['token_href']);
        $this->assertSame(1050, $api['minor']);

        $webhook = WorldpaySupport::normalize([
            'eventDetails' => [
                'type' => 'refused',
                'transactionReference' => 'wsi-1-abc',
                'scheme' => ['reference' => 'SCHEME1'],
                'amount' => ['value' => 1050, 'currencyCode' => 'GBP'],
                'refusalDescription' => 'Card refused',
            ],
        ]);

        $this->assertSame('refused', $webhook['outcome']);
        $this->assertSame('SCHEME1', $webhook['scheme_reference']);
        $this->assertSame('Card refused', $webhook['message']);
    }

}
