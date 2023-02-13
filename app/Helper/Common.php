<?php

namespace App\Helper;

class Common
{

    /** Return success response
     * @param $date
     * @return string
     */

    public static function dateColor($date, $past = true): string
    {
        if (is_null($date)) {
            return '--';
        }

        $formattedDate = $date->translatedFormat(company()->date_format);
        $todayText = __('app.today');

        if ($date->setTimezone(company()->timezone)->isToday()) {
            return '<span class="text-success">' . $todayText . '</span>';
        }

        if ($date->endOfDay()->isPast() && $past) {
            return '<span class="text-danger">' . $formattedDate . '</span>';
        }

        return '<span>' . $formattedDate . '</span>';
    }

    public static function active(): string
    {
        return '<i class="fa fa-circle mr-1 text-light-green f-10"></i>' . __('app.active');
    }

    public static function inactive(): string
    {
        return '<i class="fa fa-circle mr-1 text-red f-10"></i>' . __('app.inactive');
    }

    public static function encryptDecrypt($string, $action = 'encrypt')
    {
        $encryptMethod = 'AES-256-CBC';
        [$key, $iv] = self::cryptoMaterials();

        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encryptMethod, $key, 0, $iv);

            return base64_encode($output);
        }

        if ($action == 'decrypt') {
            $decoded = openssl_decrypt(base64_decode($string), $encryptMethod, $key, 0, $iv);

            // Fall back to the legacy shared key so existing URLs keep working
            if ($decoded === false) {
                [$legacyKey, $legacyIv] = self::legacyCryptoMaterials();
                $decoded = openssl_decrypt(base64_decode($string), $encryptMethod, $legacyKey, 0, $legacyIv);
            }

            if ($decoded === false) {
                throw new \Exception('Unable to decrypt string');
            }

            return $decoded;
        }

        throw new \Exception('No action provided for Common::encryptDecrypt');
    }

    /**
     * Derive encryption materials from the install-specific APP_KEY.
     */
    protected static function cryptoMaterials(): array
    {
        $appKey = (string) config('app.key');

        if ($appKey === '') {
            throw new \Exception('APP_KEY is missing. Run php artisan key:generate.');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        $material = hash('sha256', $appKey, true);

        return [substr($material, 0, 32), substr($material, 0, 16)];
    }

    /**
     * Legacy shared materials (pre-security fix). Kept only for decrypt fallback.
     */
    protected static function legacyCryptoMaterials(): array
    {
        $secret_key = 'worksuite';
        $secret_iv = 'froiden';

        return [hash('sha256', $secret_key), substr(hash('sha256', $secret_iv), 0, 16)];
    }

    /**
     * Reject path traversal and absolute paths after decryption.
     */
    public static function assertSafeStoragePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || preg_match('#^[a-zA-Z]:#', $path)) {
            abort(404);
        }

        return $path;
    }

    public static function safeString($string)
    {
        if (empty($string) || $string == null) {
            return '';
        }

        if (!is_string($string) || strlen($string) > 255) {
            abort(400, 'Invalid search term.');
        }
        return $string;
    }
}
