<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'path', 'sort_order'];

    protected $appends = ['url'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        // See Product::casts() for why: sqlsrv returns plain FK columns as
        // strings, and this one gets compared against a PK with === when
        // deleting an image scoped to its product.
        return ['product_id' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function url(): Attribute
    {
        // config/filesystems.php's 'public' disk already builds this from
        // APP_URL, so it comes out absolute (e.g.
        // http://localhost:8000/storage/products/3/8f2c1a.jpg).
        return Attribute::make(
            get: fn () => Storage::disk('public')->url($this->path),
        );
    }
}
