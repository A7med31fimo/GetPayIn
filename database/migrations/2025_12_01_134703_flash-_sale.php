<?php

// database/migrations/2024_01_01_000001_create_flash_sale_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');//name of the product
            $table->decimal('price', 10,2)->default(0);//total price of the product (total allowed 8 ,after decimal 2)
            $table->unsignedInteger('total_stock');//total stock available for the product
            $table->unsignedInteger('reserved_stock')->default(0);//stock that is currently on hold
            $table->unsignedInteger('count')->default(0); //for optimistic locking
            $table->timestamps();
            
            $table->index('count');// for read performance on optimistic locking
        });

        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at');
            $table->boolean('consumed')->default(false);
            $table->timestamps();
            
            $table->index(['expires_at', 'is_paid']);//for efficient querying of expired holds
            $table->index('product_id');//for faster lookups( find all holds for a product )
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('hold_id')->nullable()->constrained();
            $table->unsignedInteger('quantity');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
            
            $table->index('status');
            $table->index('hold_id');
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignId('order_id')->constrained();
            $table->enum('status', ['success', 'failure']);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('holds');
        Schema::dropIfExists('products');
    }
};