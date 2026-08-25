<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'price',
        'stock',
        'is_active',
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
            'stock' => 'integer',
            'is_active' => 'boolean',
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
}
