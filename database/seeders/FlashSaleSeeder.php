<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class FlashSaleSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'Item 1',
            'price' => 100,
            'total_stock' => 100,
            'reserved_stock' => 0,
            'count' => 0,
        ]);
        Product::create([
            'name' => 'Item 2',
            'price' => '200.50',
            'total_stock' => 100,
            'reserved_stock' => 0,
            'count' => 0,
        ]);
        Product::create([
            'name' => 'Item 3',
            'price' => 99.99,
            'total_stock' => 300,
            'reserved_stock' => 0,
            'count' => 0,
        ]);
        Product::create([
            'name' => 'Item 4',
            'price' => 404.99,
            'total_stock' => 100,
            'reserved_stock' => 0,
            'count' => 0,
        ]);
    }
}