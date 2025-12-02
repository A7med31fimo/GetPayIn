<?php

// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = [
        'product_id',
        'hold_id',
        'quantity',
        'total_price',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'total_price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(Hold::class);
    }

    /**
     * Mark order as paid and commit stock
     */
    public function markAsPaid(): void
    {
        if ($this->status !== 'pending') {
            return; // Already processed
        }
        DB::transaction(function () {
            $this->update(['status' => 'paid']);
            $this->product->commitStock($this->quantity);
        });
    }

    /**
     * Cancel order and release stock
     */
    public function cancel(): void
    {
        if ($this->status !== 'pending') {
            return; // Already processed
        }

        DB::transaction(function () {
            $this->update(['status' => 'cancelled']);
            $this->product->releaseStock($this->quantity);
        });
    }
}