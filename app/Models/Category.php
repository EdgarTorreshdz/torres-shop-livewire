<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner_image_path',
        'mobile_image_path',
        'meta_title',
        'meta_description',
        'featured_order',
    ];

    protected $appends = ['banner_image_url', 'mobile_image_url'];

    protected $hidden = ['banner_image_path', 'mobile_image_path'];

    protected function casts(): array
    {
        return ['featured_order' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function bannerImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->banner_image_path ? Storage::disk('public')->url($this->banner_image_path) : null,
        );
    }

    protected function mobileImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->mobile_image_path ? Storage::disk('public')->url($this->mobile_image_path) : null,
        );
    }
}
