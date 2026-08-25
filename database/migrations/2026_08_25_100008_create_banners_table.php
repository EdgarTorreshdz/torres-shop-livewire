<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url');

            // Three independent uploads (not just responsive resizes of one
            // image, like Category::banner_image_path already does) because
            // a wide desktop hero and a tall mobile hero usually need
            // different framing/cropping of the same promo, not just a
            // smaller copy. Same "store the disk path, expose a *_url
            // accessor" pattern as Category — see App\Models\Banner. Each
            // path still gets ResponsiveImage's WebP variants for free.
            $table->string('desktop_image_path')->nullable();
            $table->string('tablet_image_path')->nullable();
            $table->string('mobile_image_path')->nullable();

            $table->boolean('is_active')->default(false);

            // Manual display order for the home carousel when more than one
            // banner is active — lower first, same idea as
            // Category::featured_order but always editable (no separate
            // curation screen needed for something this small).
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
