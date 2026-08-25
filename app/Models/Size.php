<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global size catalog, shared across every product — a product doesn't own
 * its sizes, it picks which ones from here apply to it (which materializes
 * as product_variants rows). Keeps "M" a single thing instead of one
 * free-typed string per product.
 */
class Size extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
