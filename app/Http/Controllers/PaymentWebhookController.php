<?php

// app/Http/Controllers/PaymentWebhookController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    /**
     * Handle payment webhook (idempotent & out-of-order safe)
     */
    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => 'required|string|max:255',
            'order_id' => 'required|integer|exists:orders,id',
            'status' => 'required|in:success,failure',
            'payload' => 'nullable|array',
        ]);

        $idempotencyKey = $validated['idempotency_key'];
        $orderId = $validated['order_id'];
        $status = $validated['status'];
        $payload = $validated['payload'] ?? [];

        try {
            // Use idempotency key to prevent duplicate processing
            $result = DB::transaction(function () use ($idempotencyKey, $orderId, $status, $payload) {
                // Check if this webhook was already processed
                $existingWebhook = PaymentWebhook::where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingWebhook) {
                    // Webhook already processed - return existing result
                    return [
                        'already_processed' => true,
                        'order_status' => $existingWebhook->order->status,
                        'webhook_id' => $existingWebhook->id,
                    ];
                }

                // Lock order to prevent race conditions
                $order = Order::lockForUpdate()->find($orderId);

                if (!$order) {
                    throw new \Exception('Order not found');
                }

                // Record webhook
                $webhook = PaymentWebhook::create([
                    'idempotency_key' => $idempotencyKey,
                    'order_id' => $orderId,
                    'status' => $status,
                    'payload' => $payload,
                    'processed_at' => now(),
                ]);

                // Process payment based on status
                if ($status === 'success') {
                    $order->markAsPaid();
                } else {
                    $order->cancel();
                }

                return [
                    'already_processed' => false,
                    'order_status' => $order->fresh()->status,
                    'webhook_id' => $webhook->id,
                ];
            });

            return response()->json([
                'success' => true,
                'already_processed' => $result['already_processed'],
                'order_status' => $result['order_status'],
                'webhook_id' => $result['webhook_id'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}