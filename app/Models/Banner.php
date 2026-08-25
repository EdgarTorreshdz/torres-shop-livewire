<?php

namespace App\Models;

use App\Services\ResponsiveImage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'url',
        'desktop_image_path',
        'tablet_image_path',
        'mobile_image_path',
        'is_active',
        'sort_order',
    ];

    protected $appends = [
        'desktop_image_url', 'tablet_image_url', 'mobile_image_url',
        'desktop_srcset', 'tablet_srcset', 'mobile_srcset',
    ];

    protected $hidden = ['desktop_image_path', 'tablet_image_path', 'mobile_image_path'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function desktopImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->desktop_image_path ? Storage::disk('public')->url($this->desktop_image_path) : null,
        );
    }

    protected function tabletImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tablet_image_path ? Storage::disk('public')->url($this->tablet_image_path) : null,
        );
    }

    protected function mobileImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->mobile_image_path ? Storage::disk('public')->url($this->mobile_image_path) : null,
        );
    }

    protected function desktopSrcset(): Attribute
    {
        return Attribute::make(
            get: fn () => ResponsiveImage::srcset($this->desktop_image_path),
        );
    }

    protected function tabletSrcset(): Attribute
    {
        return Attribute::make(
            get: fn () => ResponsiveImage::srcset($this->tablet_image_path),
        );
    }

    protected function mobileSrcset(): Attribute
    {
        return Attribute::make(
            get: fn () => ResponsiveImage::srcset($this->mobile_image_path),
        );
    }
}
