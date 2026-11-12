<?php

namespace App\Support;

class BuyerPurchases
{
    /** Order snapshots contain both public-disk paths and already resolved URLs. */
    public static function image(?string $path): ?string
    {
        if (\App\Support\DemoProductImages::canonical($path)) return \App\Support\DemoProductImages::url($path);
        $path = trim((string) $path);
        if ($path === '') return null;
        if (preg_match('~^https?://~i', $path)) return $path;
        if (str_contains($path, '..') || str_contains($path, ':') || str_starts_with($path, '//')) return null;
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) $path = substr($path, 8);
        if (str_starts_with($path, 'images/') && is_file(public_path($path))) return asset($path);
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        return $disk->exists($path) ? $disk->url($path) : null;
    }

    public static function tabs(): array
    {
        return ['all' => 'All', 'to-pay' => 'To Pay', 'to-ship' => 'To Ship', 'to-receive' => 'To Receive', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    }

    public static function category(array $order): string
    {
        $status = strtolower($order['status']);
        if (in_array($status, ['completed', 'cancelled'], true)) {
            return $status;
        }
        // COD is payable on delivery; it must never strand a purchase in To Pay.
        if ($status === 'placed' && strtolower($order['payment_method']) !== 'cod'
            && in_array(strtolower($order['payment_status']), ['unpaid', 'pending', 'failed'], true)) {
            return 'to-pay';
        }
        return in_array($status, ['placed', 'confirmed', 'preparing', 'ready_for_pickup'], true)
            ? 'to-ship' : 'to-receive';
    }
}
