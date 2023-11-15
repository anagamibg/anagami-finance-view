<?php

namespace App\Services\Worldpay;

class WorldpaySupport
{

    public const SUCCESS_OUTCOMES = ['authorized', 'sentForSettlement'];

    public const FAILED_OUTCOMES = [
        'refused',
        'fraudHighRisk',
        '3dsAuthenticationFailed',
        '3dsUnavailable',
        'sentForCancellation',
        'cancelled',
        'error',
    ];

    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private const THREE_DECIMAL = ['BHD', 'IQD', 'JOD', 'KWD', 'OMR', 'TND'];

    private const ALLOWED_HOSTS = [
        'try.access.worldpay.com',
        'access.worldpay.com',
    ];

    public static function minorAmount(float $amount, string $currency): int
    {
        $exponent = self::exponent($currency);

        return (int) round($amount * (10 ** $exponent));
    }

    public static function majorAmount(int $minor, string $currency): float
    {
        $exponent = self::exponent($currency);

        return round($minor / (10 ** $exponent), $exponent);
    }

    public static function narrative(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $name) ?? '';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');

        if ($clean === '') {
            $clean = 'Payment';
        }

        return substr($clean, 0, 24);
    }

    public static function reference(string $type, int $id): string
    {
        $prefix = match ($type) {
            'order' => 'wso',
            'subscription' => 'wss',
            default => 'wsi',
        };

        return $prefix . '-' . $id . '-' . strtolower(bin2hex(random_bytes(8)));
    }

    public static function parseReference(string $reference): ?array
    {
        if (!preg_match('/^ws([ios])-(\d+)-[a-z0-9]+$/', $reference, $matches)) {
            return null;
        }

        $types = [
            'i' => 'invoice',
            'o' => 'order',
            's' => 'subscription',
        ];

        return [
            'type' => $types[$matches[1]],
            'id' => (int) $matches[2],
        ];
    }

    public static function canStartCharge(float $amountDue, ?string $status = null): bool
    {
        if ($status === 'paid') {
            return false;
        }

        return $amountDue > 0;
    }

    public static function isSuccess(string $outcome): bool
    {
        return in_array($outcome, self::SUCCESS_OUTCOMES, true);
    }

    public static function isFailed(string $outcome): bool
    {
        return in_array($outcome, self::FAILED_OUTCOMES, true);
    }

    public static function isDeviceData(string $outcome): bool
    {
        return $outcome === '3dsDeviceDataRequired';
    }

    public static function isChallenge(string $outcome): bool
    {
        return $outcome === '3dsChallenged';
    }

    /**
     * Decide what to do with a Worldpay result for a reference we already know.
     * A completed payment for the same reference is left untouched.
     */
    public static function recordDecision(?string $existingStatus, string $outcome): string
    {
        if ($existingStatus === 'complete') {
            return 'skip';
        }

        if (self::isSuccess($outcome)) {
            return 'complete';
        }

        if (self::isFailed($outcome)) {
            return 'failed';
        }

        if ($outcome === 'sentForAuthorization') {
            return 'pending';
        }

        return 'ignore';
    }

    public static function signatureMatches(string $rawBody, ?string $header, string $secret): bool
    {
        if ($header === null || trim($header) === '' || $secret === '') {
            return false;
        }

        foreach (array_map('trim', explode(',', $header)) as $part) {
            $bits = explode('/', $part);

            if (count($bits) < 3 || strtoupper($bits[1]) !== 'SHA256') {
                continue;
            }

            $given = $bits[2];
            $hex = hash_hmac('sha256', $rawBody, $secret);
            $base64 = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

            if (hash_equals($hex, strtolower($given)) || hash_equals($base64, $given)) {
                return true;
            }
        }

        return false;
    }

    public static function allowedActionUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        return in_array(strtolower($parts['host']), self::ALLOWED_HOSTS, true);
    }

    public static function normalize(array $body): array
    {
        $details = (isset($body['eventDetails']) && is_array($body['eventDetails'])) ? $body['eventDetails'] : $body;
        $currency = (string) ($details['amount']['currencyCode'] ?? $details['value']['currency'] ?? '');
        $minor = $details['amount']['value'] ?? $details['value']['amount'] ?? 0;

        return [
            'outcome' => (string) ($details['outcome'] ?? $details['type'] ?? ''),
            'payment_id' => $details['paymentId'] ?? null,
            'scheme_reference' => self::schemeReference($body) ?: self::schemeReference($details),
            'token_href' => self::tokenHref($body) ?: self::tokenHref($details),
            'currency' => strtoupper($currency),
            'minor' => (int) $minor,
            'message' => self::messageFrom($details),
        ];
    }

    public static function tokenHref(array $body): ?string
    {
        $candidates = [
            $body['token']['href'] ?? null,
            $body['token']['tokenPaymentInstrument']['href'] ?? null,
            $body['_links']['tokens:token']['href'] ?? null,
            $body['_links']['token']['href'] ?? null,
            $body['paymentInstrument']['href'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    public static function schemeReference(array $body): ?string
    {
        $candidates = [
            $body['schemeReference'] ?? null,
            $body['scheme']['reference'] ?? null,
            $body['customerAgreement']['schemeReference'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    public static function messageFrom(array $body): string
    {
        if (!empty($body['refusalDescription']) && is_string($body['refusalDescription'])) {
            return $body['refusalDescription'];
        }

        if (!empty($body['validationErrors'][0]['message']) && is_string($body['validationErrors'][0]['message'])) {
            return $body['validationErrors'][0]['message'];
        }

        if (!empty($body['message']) && is_string($body['message'])) {
            return $body['message'];
        }

        $outcome = (string) ($body['outcome'] ?? $body['type'] ?? '');

        return $outcome !== '' ? $outcome : 'Payment could not be completed.';
    }

    public static function deviceData(array $input): array
    {
        return [
            'acceptHeader' => 'text/html',
            'userAgentHeader' => substr((string) ($input['userAgentHeader'] ?? 'Mozilla/5.0'), 0, 512),
            'browserLanguage' => substr((string) ($input['browserLanguage'] ?? 'en-GB'), 0, 16),
            'browserScreenHeight' => max(1, (int) ($input['browserScreenHeight'] ?? 800)),
            'browserScreenWidth' => max(1, (int) ($input['browserScreenWidth'] ?? 400)),
            'browserJavaEnabled' => filter_var($input['browserJavaEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'browserColorDepth' => substr((string) ($input['browserColorDepth'] ?? '24'), 0, 8),
            'timeZone' => substr((string) ($input['timeZone'] ?? '0'), 0, 8),
            'browserJavascriptEnabled' => true,
            'channel' => 'browser',
        ];
    }

    private static function exponent(string $currency): int
    {
        $currency = strtoupper($currency);

        if (in_array($currency, self::ZERO_DECIMAL, true)) {
            return 0;
        }

        if (in_array($currency, self::THREE_DECIMAL, true)) {
            return 3;
        }

        return 2;
    }

}
