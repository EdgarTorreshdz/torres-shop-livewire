<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'color_name',
        'size_name',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            // See Product::casts() — sqlsrv returns plain FK columns as
            // strings unless cast explicitly.
            'order_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * "Rojo / M", "Rojo", "M" or '' — built from the snapshots taken at
     * purchase time, not from the live variant (which may have been
     * renamed or deleted since). Lives here rather than being re-derived
     * in each of the three screens that list order lines.
     */
    protected function variantLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => collect([$this->color_name, $this->size_name])->filter()->implode(' / '),
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
