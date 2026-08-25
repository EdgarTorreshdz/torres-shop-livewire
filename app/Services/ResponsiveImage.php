<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Stores an uploaded image once at its original size/format (so nothing
 * downstream that already expects "the path" breaks), plus a handful of
 * smaller WebP copies next to it — one per breakpoint in self::WIDTHS —
 * so a phone never has to download a 4000px-wide product photo just to
 * show it at 400px. Rendered via srcset()/pick the matching <img
 * srcset="..." sizes="..."> so the browser (not the server) decides which
 * file to actually fetch, based on the viewport and pixel density it has
 * at the moment.
 *
 * No extra DB column for the variants: their paths are derived from the
 * original path by a fixed naming convention (see variantPath()), so any
 * model that already stores one path string (ProductImage::path,
 * Category::banner_image_path/mobile_image_path) gets responsive variants
 * for free just by calling srcset() with that path.
 */
class ResponsiveImage
{
    /** width in pixels => breakpoint label, smallest first */
    public const WIDTHS = [
        480 => 'sm',
        768 => 'md',
        1200 => 'lg',
    ];

    private const QUALITY = 75;

    /**
     * Store the original file on $disk under $directory (same as
     * `$file->store($directory, $disk)` would), then generate a WebP copy
     * at each width in self::WIDTHS next to it. scaleDown() never
     * upscales — a source image smaller than a given breakpoint just
     * yields a duplicate at its own size for that breakpoint rather than
     * a blurry enlargement; harmless, and simpler than special-casing it.
     *
     * Returns the original's path, unchanged from what
     * `UploadedFile::store()` already returned everywhere this used to
     * be called directly.
     */
    public static function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $path = $file->store($directory, $disk);

        $manager = new ImageManager(Driver::class);
        $source = $manager->decodePath($file->getRealPath());

        foreach (self::WIDTHS as $width => $label) {
            $encoded = (clone $source)->scaleDown(width: $width)->encode(new WebpEncoder(quality: self::QUALITY));
            Storage::disk($disk)->put(self::variantPath($path, $label), (string) $encoded);
        }

        return $path;
    }

    /**
     * Delete the original plus every generated variant. Always call this
     * instead of Storage::delete($path) directly for any path that went
     * through store() above — otherwise the variants are orphaned on
     * disk forever (the DB only ever knew about the original path).
     */
    public static function delete(?string $path, string $disk = 'public'): void
    {
        if (! $path) {
            return;
        }

        Storage::disk($disk)->delete($path);

        foreach (self::WIDTHS as $width => $label) {
            Storage::disk($disk)->delete(self::variantPath($path, $label));
        }
    }

    /**
     * Build a <img srcset> value ("url 480w, url 768w, url 1200w") from a
     * stored original path. Returns null if $path is null (so callers can
     * just do `:srcset="ResponsiveImage::srcset($model->path)"` without a
     * null check first) or if none of the variants actually exist on disk
     * — e.g. an image stored before this class existed, which only ever
     * had the original file. Checking existence here, rather than trusting
     * the naming convention blindly, means srcset() can never point the
     * browser at a 404.
     */
    public static function srcset(?string $path, string $disk = 'public'): ?string
    {
        if (! $path) {
            return null;
        }

        $entries = collect(self::WIDTHS)
            ->filter(fn (string $label) => Storage::disk($disk)->exists(self::variantPath($path, $label)))
            ->map(fn (string $label, int $width) => Storage::disk($disk)->url(self::variantPath($path, $label))." {$width}w");

        return $entries->isEmpty() ? null : $entries->implode(', ');
    }

    private static function variantPath(string $path, string $label): string
    {
        $info = pathinfo($path);
        $dir = $info['dirname'] === '.' ? '' : $info['dirname'].'/';

        return "{$dir}{$info['filename']}-{$label}.webp";
    }
}
