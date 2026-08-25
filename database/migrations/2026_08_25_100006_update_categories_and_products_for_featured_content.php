<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dropping + re-adding rather than renameColumn(): renaming needs
        // doctrine/dbal on some drivers and there's no real data in these
        // columns yet to preserve (both were plain pasted URLs, never a
        // real upload) — simplest is a clean cut.
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['banner_image_url', 'mobile_image_url']);
        });

        Schema::table('categories', function (Blueprint $table) {
            // These now store the disk path (like product_images.path), not
            // a pasted URL — real file uploads, matching how product images
            // already worked. The model exposes banner_image_url/
            // mobile_image_url as computed accessors built from the path,
            // so nothing downstream needs to know the column name changed.
            $table->string('banner_image_path')->nullable()->after('meta_description');
            $table->string('mobile_image_path')->nullable()->after('banner_image_path');

            // Null = not shown in the customer-facing nav menu. A number =
            // its position there. Curated manually from the admin, not tied
            // to any actual season/date — "de temporada" just describes the
            // use case (swap which categories are highlighted, e.g. for a
            // season or promotion), not a schedule.
            $table->unsignedInteger('featured_order')->nullable()->after('mobile_image_path');
        });

        Schema::table('products', function (Blueprint $table) {
            // Same idea as categories.featured_order: null = not on the
            // curated "selected products" list, a number = its position
            // there (home page + the "selected products" block on a
            // product's own detail page).
            $table->unsignedInteger('featured_order')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['banner_image_path', 'mobile_image_path', 'featured_order']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('banner_image_url')->nullable();
            $table->string('mobile_image_url')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('featured_order');
        });
    }
};
