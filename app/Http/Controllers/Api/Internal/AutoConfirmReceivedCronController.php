<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderReceiptFinalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AutoConfirmReceivedCronController extends Controller
{
    public function __invoke(
        Request $request,
        OrderReceiptFinalizationService $service
    ): JsonResponse {
        $expectedToken = (string) env('BILIHUB_CRON_TOKEN', '');
        $providedToken = (string) $request->header('X-BiliHub-Cron-Token', '');

        if (
            $expectedToken === ''
            || $providedToken === ''
            || !hash_equals($expectedToken, $providedToken)
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (!Schema::hasColumn('orders', 'confirmed_received_at')) {
            return response()->json([
                'ok' => false,
                'message' => 'orders.confirmed_received_at is missing.',
            ], 500);
        }

        $checked = 0;
        $confirmed = 0;
        $confirmedOrderIds = [];

        Order::query()
            ->whereNull('confirmed_received_at')
            ->where('status', 'delivered')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (
                $service,
                &$checked,
                &$confirmed,
                &$confirmedOrderIds
            ) {
                foreach ($orders as $order) {
                    $checked++;

                    if (!$service->isEligibleForAutoConfirm($order)) {
                        continue;
                    }

                    $service->finalize($order, 'auto_3_days_cron');

                    $confirmed++;
                    $confirmedOrderIds[] = $order->id;
                }
            });

        return response()->json([
            'ok' => true,
            'checked' => $checked,
            'auto_confirmed' => $confirmed,
            'order_ids' => $confirmedOrderIds,
        ]);
    }
}
