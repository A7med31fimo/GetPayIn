<?php

// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'total_stock',
        'reserved_stock',
        'version',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total_stock' => 'integer',
        'reserved_stock' => 'integer',
        'version' => 'integer',
    ];

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get available stock (total - reserved)
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->total_stock - $this->reserved_stock);
    }

    /**
     * Reserve stock with optimistic locking
     * 
     * @throws \Exception if stock insufficient or concurrent modification
     */
    public function reserveStock(int $quantity): void
    {
        $currentVersion = $this->version;
        
        $updated = self::where('id', $this->id)
            ->where('version', $currentVersion)
            ->where('total_stock', '>=', \DB::raw('reserved_stock + ' . $quantity))
            ->update([
                'reserved_stock' => \DB::raw('reserved_stock + ' . $quantity),
                'version' => \DB::raw('version + 1'),
            ]);

        if ($updated === 0) {
            // Either version changed (concurrent update) or insufficient stock
            $fresh = self::lockForUpdate()->find($this->id);
            
            if ($fresh->available_stock < $quantity) {
                throw new \Exception('Insufficient stock available');
            }
            
            throw new \Exception('Concurrent modification detected, please retry');
        }

        $this->refresh();
    }

    /**
     * Release reserved stock
     */
    public function releaseStock(int $quantity): void
    {
        self::where('id', $this->id)
            ->update([
                'reserved_stock' => \DB::raw('GREATEST(0, reserved_stock - ' . $quantity . ')'),
                'version' => \DB::raw('version + 1'),
            ]);

        $this->refresh();
    }

    /**
     * Commit reserved stock to sold
     */
    public function commitStock(int $quantity): void
    {
        self::where('id', $this->id)
            ->update([
                'total_stock' => \DB::raw('total_stock - ' . $quantity),
                'reserved_stock' => \DB::raw('GREATEST(0, reserved_stock - ' . $quantity . ')'),
                'version' => \DB::raw('version + 1'),
            ]);

        $this->refresh();
    }
}