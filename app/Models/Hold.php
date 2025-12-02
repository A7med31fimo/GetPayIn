<?php

// app/Models/Hold.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Hold extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'expires_at',
        'consumed',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
        'consumed' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if hold is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if hold is valid (not consumed and not expired)
     */
    public function isValid(): bool
    {
        return !$this->consumed && !$this->isExpired();
    }

    /**
     * Mark hold as consumed
     */
    public function consume(): void
    {
        $this->update(['consumed' => true]);
    }

    /**
     * Release expired holds and return their stock
     */
    public static function releaseExpired(): int
    {
        $expiredHolds = self::where('expires_at', '<', now())
            ->where('consumed', false)
            ->lockForUpdate()
            ->get();

        $count = 0;
        
        foreach ($expiredHolds as $hold) {
            \DB::transaction(function () use ($hold) {
                $hold->product->releaseStock($hold->quantity);
                $hold->update(['consumed' => true]);
            });
            $count++;
        }

        return $count;
    }
}