<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sellable color x size combination. The only place stock lives for a
 * product that has any variants at all — see the migration's comment for
 * why stock isn't kept on colors and sizes separately.
 *
 * Either dimension can be null: a product with only colors has variants
 * with size_id null, one with only sizes has product_color_id null.
 */
class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'product_color_id', 'size_id', 'stock'];

    protected function casts(): array
    {
        return [
            // See Product::casts() — sqlsrv returns plain FK columns as
            // strings unless cast explicitly, and these get compared
            // against primary keys with === all over the cart/checkout.
            'product_id' => 'integer',
            'product_color_id' => 'integer',
            'size_id' => 'integer',
            'stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    /**
     * Price actually charged for this combination. Size never changes the
     * price on its own (nothing asked for per-size pricing, and an unused
     * nullable price column would just be speculation) — it falls through
     * to the color's own price when it has one, then the product's.
     */
    protected function effectivePrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->color?->effective_price ?? $this->product->price,
        );
    }

    /** "Rojo / M", "Rojo", "M", or '' — whichever dimensions this variant actually has. */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn () => collect([$this->color?->name, $this->size?->name])->filter()->implode(' / '),
        );
    }
}
