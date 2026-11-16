<?php

namespace App\Support;

use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token-bound equivalent of the web Shipment Scanner's session verification.
 *
 * The web scanner stores "this rider just scanned this exact shipment in this
 * mode" in the browser session for 15 minutes and forgets it after one
 * successful action. Mobile requests have no session, so the same record is
 * bound to the Rider and to the exact Sanctum personal access token that
 * performed the scan. Another device, another token, another rider, a stale
 * scan, or a second action all fail closed.
 */
final class RiderApiScanVerification
{
    public const TTL_SECONDS = 900;

    public static function put(Request $request, Rider $rider, int $shipmentId, ?string $mode): void
    {
        self::release($request, $rider);
        Cache::put(self::key($request, $rider), [
            'shipment_id' => $shipmentId,
            'mode' => $mode,
            'verified_at' => now()->timestamp,
        ], self::TTL_SECONDS);
    }

    public static function forget(Request $request, Rider $rider): void
    {
        Cache::forget(self::key($request, $rider));
    }

    /** Returns the verification only when it is fresh and for this exact shipment. */
    public static function get(Request $request, Rider $rider, ?int $shipmentId = null): ?array
    {
        $value = Cache::get(self::key($request, $rider));
        if (! is_array($value)
            || ! is_numeric($value['verified_at'] ?? null)
            || now()->timestamp - (int) $value['verified_at'] > self::TTL_SECONDS) {
            return null;
        }
        if ($shipmentId !== null && (int) ($value['shipment_id'] ?? 0) !== $shipmentId) {
            return null;
        }

        return $value;
    }

    /**
     * Claims the verification for one transition. Returns false when another
     * request already claimed it (replay / double submit).
     */
    public static function claim(Request $request, Rider $rider): bool
    {
        return Cache::add(self::key($request, $rider).':claim', 1, self::TTL_SECONDS);
    }

    public static function release(Request $request, Rider $rider): void
    {
        Cache::forget(self::key($request, $rider).':claim');
    }

    public static function consume(Request $request, Rider $rider): void
    {
        self::forget($request, $rider);
        self::release($request, $rider);
    }

    private static function key(Request $request, Rider $rider): string
    {
        $token = $request->user()?->currentAccessToken();
        $binding = $token instanceof PersonalAccessToken
            ? 'pat:'.$token->getKey()
            : 'session:'.hash('sha256', (string) ($request->hasSession() ? $request->session()->getId() : 'none'));

        return 'rider_api_scan:'.$rider->id.':'.$binding;
    }
}
