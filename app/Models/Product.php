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

    /**
     * The product's own base gallery — deliberately excludes images that
     * belong to a specific color (product_images.product_color_id set).
     * Without this filter, a color's photos would double up in here *and*
     * in ProductColor::images(), since both still share the same
     * product_id. This is what renders when no color is selected (or the
     * product has none at all) — see ProductColor::images() for a color's
     * own gallery.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->whereNull('product_color_id')->orderBy('sort_order');
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * The sizes this product is actually sold in — derived from its
     * variants rather than stored in a separate product/size pivot, which
     * would be a second place for the same fact to live (and disagree).
     * Picking a size for a product IS creating its variant rows; see
     * admin.products.variants.index.
     */
    protected function availableSizes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->variants
                ->map->size
                ->filter()
                ->unique('id')
                ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                ->values(),
        );
    }

    /**
     * Aggregate stock across variants when this product has any — a product
     * with variants delegates availability to them entirely, the same way a
     * real store's "in stock" depends on whether *any* color/size
     * combination has inventory, not a separate top-level number that could
     * disagree with them. Falls back to the product's own `stock` column
     * for a product with no variants at all, so nothing that predates this
     * feature has to change.
     */
    protected function totalStock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->variants->isNotEmpty() ? $this->variants->sum('stock') : $this->stock,
        );
    }

    protected function isInStock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total_stock > 0,
        );
    }

    /**
     * The image shown where only one representative photo fits (the shop
     * grid, carousels, the admin list) — the product's own gallery first,
     * falling back to its first color's first photo for a product that
     * only ever had color-specific images uploaded (no base gallery of
     * its own).
     */
    protected function displayImage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->images->first() ?? $this->colors->first()?->images->first(),
        );
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
