<?php

// app/Http/Controllers/ProductController.php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    /**
     * Get product details with accurate available stock
     */
    public function show(int $id): JsonResponse
    {
        // Cache key includes version for cache invalidation
        $cacheKey = "product.{$id}";
        
        $product = Cache::remember($cacheKey, 5, function () use ($id) {
            return Product::find($id);
        });

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Always get fresh stock count (critical for accuracy)
        $freshProduct = Product::find($id);

        return response()->json([
            'id' => $freshProduct->id,
            'name' => $freshProduct->name,
            'price' => (float) $freshProduct->price,
            'available_stock' => $freshProduct->available_stock,
            'total_stock' => $freshProduct->total_stock,
        ]);
    }


    public function create(Product $p): JsonResponse
    {

        $product = new Product();
        $product->name = $p->name;
        $product->price = $p->price;
        $product->available_stock = $p->available_stock;
        $product->total_stock = $p->total_stock;
        $product->save();
        return response()->json([
            'message' => 'Create product endpoint',
        ]);
    }
}