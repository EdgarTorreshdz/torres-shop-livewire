<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A real variant, not descriptive text like Product::material still
        // is: its own price (nullable — falls back to the product's price
        // when not set, see ProductColor::effective_price), its own stock
        // (independent per color, like size/color variants on a real
        // ecommerce site — a product with colors defined delegates its
        // "in stock" status entirely to them, see Product::total_stock),
        // and its own image gallery (product_images.product_color_id
        // below).
        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Optional swatch color for the picker UI, shown as a fallback
            // dot when a color doesn't have an image uploaded yet. Not
            // required — most of the time the swatch itself is the
            // color's first photo, same as Nike/most real stores.
            $table->string('hex', 7)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('product_images', function (Blueprint $table) {
            // Nullable: existing rows (and any image uploaded through the
            // product's own gallery, not a specific color's) keep this
            // null — Product::images() filters on that to keep showing
            // exactly the same "base gallery" it always has. Only images
            // uploaded through a color's own gallery get this set.
            //
            // Not cascadeOnDelete(): product_images already cascades from
            // `products` via product_id (every image row, color-specific
            // or not, keeps its product_id) — SQL Server refuses a second
            // cascade path to the same table through product_colors
            // ("may cause cycles or multiple cascade paths"), which is a
            // real constraint this hit while building it, not a
            // theoretical one. A color's images still get cleaned up —
            // just explicitly in application code (delete the files, then
            // the rows, then the color), same as products/trash.blade.php
            // already does for a force-deleted product's images.
            $table->foreignId('product_color_id')->nullable()->after('product_id')->constrained();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Same "snapshot at purchase time" reasoning as product_name:
            // stays accurate even if the color is later renamed or
            // deleted. No FK to product_colors — deliberately just a
            // plain string, so a deleted color never has to touch old
            // orders at all.
            $table->string('color_name')->nullable()->after('product_name');
        });

        // The free-text `color` column (added when Product first grew
        // merchandising fields) is superseded by the real product_colors
        // relation above — keeping both would leave two different,
        // disagreeing answers to "what color is this?" in the same admin
        // form. `material` stays: it was never asked to become a variant
        // dimension, still applies to the whole product regardless of
        // which color.
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('color')->nullable()->after('stock');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('color_name');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_color_id');
        });

        Schema::dropIfExists('product_colors');
    }
};
