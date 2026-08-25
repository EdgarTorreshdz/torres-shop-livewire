<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner_image_url',
        'mobile_image_url',
        'meta_title',
        'meta_description',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
