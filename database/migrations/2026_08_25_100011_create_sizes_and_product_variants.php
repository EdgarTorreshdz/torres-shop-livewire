<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global, reusable catalog — picked per product, not typed free-form
        // per product. Stops "M", "m" and "Mediana" from coexisting as three
        // different sizes, and keeps a real filter-by-size possible later.
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The color x size matrix. One row per combination actually sold,
        // and the ONLY place stock lives once a product has variants —
        // deliberately not a stock column on colors AND another on sizes,
        // because two independent numbers can't answer "how many Rojo/M are
        // left?" and would leave checkout guessing which one to decrement.
        //
        // Both dimensions are nullable so a product can have just colors
        // (size_id null), just sizes (product_color_id null), or both. A
        // product with neither has no variants at all and keeps using
        // products.stock, exactly as before this existed.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // No cascadeOnDelete on either of these: product_variants
            // already cascades from products via product_id, and SQL Server
            // refuses more than one cascade path into the same table
            // ("may cause cycles or multiple cascade paths") — the exact
            // error product_images.product_color_id hit one migration ago.
            // Deleting a color/size cleans its variants in application code
            // instead (see admin.products.colors.index and admin.sizes.index).
            $table->foreignId('product_color_id')->nullable()->constrained();
            $table->foreignId('size_id')->nullable()->constrained();

            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();

            // Deliberately NOT ->unique(['product_id', 'product_color_id',
            // 'size_id']): with nullable columns the drivers disagree about
            // what "duplicate" even means — SQL Server treats NULL = NULL
            // (so it would enforce it), while sqlite/mysql/pgsql treat every
            // NULL as distinct (so it would not). Uniqueness is enforced in
            // the admin instead, which generates the matrix rather than
            // letting anyone type an arbitrary combination.
            $table->index(['product_id', 'product_color_id', 'size_id'], 'product_variants_matrix_index');
        });

        // Same "snapshot at purchase time" reasoning as product_name and
        // color_name: a size renamed or removed later never rewrites an
        // order that already shipped.
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('size_name')->nullable()->after('color_name');
        });

        // Carry the stock that already lives on existing colors into the
        // matrix as (color, no size) variants, so the data the admin
        // already captured survives this change instead of silently
        // resetting to 0. Raw queries, not Eloquent: a migration has to
        // keep working even after the models move on.
        foreach (DB::table('product_colors')->get() as $color) {
            DB::table('product_variants')->insert([
                'product_id' => $color->product_id,
                'product_color_id' => $color->id,
                'size_id' => null,
                'stock' => $color->stock,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Now redundant: a color's availability is the sum of its variants.
        // Leaving it would reintroduce exactly the two-disagreeing-numbers
        // problem the matrix exists to avoid.
        Schema::table('product_colors', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }

    public function down(): void
    {
        Schema::table('product_colors', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0);
        });

        // Fold the matrix back down to a per-color total, the best
        // approximation available going backwards.
        foreach (DB::table('product_variants')->whereNotNull('product_color_id')->get()->groupBy('product_color_id') as $colorId => $variants) {
            DB::table('product_colors')->where('id', $colorId)->update([
                'stock' => $variants->sum('stock'),
            ]);
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('size_name');
        });

        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('sizes');
    }
};
