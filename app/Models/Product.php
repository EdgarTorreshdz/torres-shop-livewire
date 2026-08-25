<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'meta_title',
        'meta_description',
        'price',
        'wholesale_price',
        'cost',
        'stock',
        'color',
        'material',
        'is_active',
        'featured_order',
    ];

    protected function casts(): array
    {
        return [
            // category_id needs an explicit cast: the sqlsrv driver returns
            // plain (non-primary-key) numeric columns as strings, unlike
            // Eloquent's auto-cast primary keys — comparing an uncast FK
            // against a PK with === false-positives as a mismatch.
            'category_id' => 'integer',
            'price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'featured_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * price - cost, or null when cost isn't set (an admin who hasn't
     * bothered entering a production cost yet shouldn't see a misleading
     * "$0 margin" — null reads as "unknown", not "no profit"). Admin-only
     * figures: never rendered on any customer-facing view, same as
     * cost/wholesale_price themselves.
     */
    protected function marginAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cost === null ? null : $this->price - $this->cost,
        );
    }

    /**
     * Margin as a percentage of the sale price, rounded to one decimal.
     * Null under the same "cost not set" condition as marginAmount(), plus
     * when price is 0 (division by zero).
     */
    protected function marginPercent(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->cost === null || (float) $this->price === 0.0)
                ? null
                : round((($this->price - $this->cost) / $this->price) * 100, 1),
        );
    }
}
