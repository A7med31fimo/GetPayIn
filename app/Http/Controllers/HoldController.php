<?php

// app/Http/Controllers/HoldController.php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Hold;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HoldController extends Controller
{
    /**
     * Create a temporary hold on product stock
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        $productId = $validated['product_id'];
        $quantity = $validated['qty'];

        try {
            $hold = DB::transaction(function () use ($productId, $quantity) {
                // Lock product row for update
                $product = Product::lockForUpdate()->find($productId);

                if (!$product) {
                    throw new \Exception('Product not found');
                }

                // Check available stock
                if ($product->available_stock < $quantity) {
                    throw new \Exception('Insufficient stock available');
                }

                // Reserve stock with optimistic locking
                $product->reserveStock($quantity);

                // Create hold with 2-minute expiration
                $expiresAt = Carbon::now()->addMinutes(2);
                
                $hold = Hold::create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'expires_at' => $expiresAt,
                    'consumed' => false,
                ]);

                return $hold;
            });

            return response()->json([
                'hold_id' => $hold->id,
                'expires_at' => $hold->expires_at->toIso8601String(),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}