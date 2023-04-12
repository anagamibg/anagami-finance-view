<?php

namespace App\Services\Worldpay;

use Illuminate\Support\Facades\Cache;

class WorldpayAttempt
{

    public static function store(string $reference, array $data): void
    {
        Cache::put(self::key($reference), $data, now()->addMinutes(30));
    }

    public static function find(string $reference): ?array
    {
        $data = Cache::get(self::key($reference));

        return is_array($data) ? $data : null;
    }

    public static function merge(string $reference, array $data): ?array
    {
        $current = self::find($reference);

        if (!$current) {
            return null;
        }

        $current = array_merge($current, $data);
        self::store($reference, $current);

        return $current;
    }

    public static function acquire(string $type, int $id, string $reference): bool
    {
        return Cache::add(self::lockKey($type, $id), $reference, now()->addMinutes(15));
    }

    public static function release(string $type, int $id): void
    {
        Cache::forget(self::lockKey($type, $id));
    }

    public static function markOpen(string $type, int $id, string $reference): void
    {
        Cache::put(self::openKey($type, $id), $reference, now()->addDays(3));
    }

    public static function openReference(string $type, int $id): ?string
    {
        $value = Cache::get(self::openKey($type, $id));

        return is_string($value) ? $value : null;
    }

    public static function clearOpen(string $type, int $id): void
    {
        Cache::forget(self::openKey($type, $id));
    }

    private static function key(string $reference): string
    {
        return 'worldpay_attempt:' . $reference;
    }

    private static function lockKey(string $type, int $id): string
    {
        return 'worldpay_lock:' . $type . ':' . $id;
    }

    private static function openKey(string $type, int $id): string
    {
        return 'worldpay_open:' . $type . ':' . $id;
    }

}
