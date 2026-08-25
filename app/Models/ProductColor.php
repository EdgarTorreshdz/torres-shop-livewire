<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductColor extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'hex',
        'price',
        'stock',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            // See Product::casts() for why: sqlsrv returns plain FK
            // columns as strings unless cast explicitly.
            'product_id' => 'integer',
            'price' => 'decimal:2',
            'stock' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * The price actually charged for this color — its own price when set,
     * otherwise the parent product's. Every place that needs "what does
     * this color cost" (the storefront swatch price, Cart::items(),
     * checkout) goes through this instead of reading `price` directly, so
     * "no override set" and "override set to the same number" can't be
     * confused with each other by accident.
     */
    protected function effectivePrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price ?? $this->product->price,
        );
    }
}
