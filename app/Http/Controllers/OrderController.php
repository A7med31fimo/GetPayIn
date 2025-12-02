<?php

// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Create order from a valid hold
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hold_id' => 'required|integer|exists:holds,id',
        ]);

        $holdId = $validated['hold_id'];

        try {
            $order = DB::transaction(function () use ($holdId) {
                // Lock hold for update to prevent double-use
                $hold = Hold::lockForUpdate()->find($holdId);

                if (!$hold) {
                    throw new \Exception('Hold not found');
                }

                // Validate hold
                if ($hold->consumed) {
                    throw new \Exception('Hold has already been used');
                }

                if ($hold->isExpired()) {
                    // Release stock if not already released
                    $hold->product->releaseStock($hold->quantity);
                    $hold->consume();
                    throw new \Exception('Hold has expired');
                }

                // Mark hold as consumed
                $hold->consume();

                // Create order in pending state
                $order = Order::create([
                    'product_id' => $hold->product_id,
                    'hold_id' => $hold->id,
                    'quantity' => $hold->quantity,
                    'total_price' => $hold->product->price * $hold->quantity,
                    'status' => 'pending',
                ]);

                return $order;
            });

            return response()->json([
                'order_id' => $order->id,
                'product_id' => $order->product_id,
                'quantity' => $order->quantity,
                'total_price' => (float) $order->total_price,
                'status' => $order->status,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}