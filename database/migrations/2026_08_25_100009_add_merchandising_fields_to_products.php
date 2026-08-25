<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Internal reference code — not the same as `slug` (which is
            // derived from the name for URLs). Nullable: existing products
            // don't have one yet, and not every seeded/demo product needs
            // one. Uniqueness is added separately below, not chained here
            // — see why.
            $table->string('sku', 100)->nullable()->after('slug');

            // Free text, not a variants table — "Rojo, Azul, Negro" as one
            // string. A real multi-warehouse variant system (separate
            // stock/price per color+material combination) would be a much
            // bigger feature than what was asked for; this is descriptive
            // info shown on the product page, same stock/price for the
            // whole product regardless of which color/material it says.
            $table->string('color')->nullable()->after('stock');
            $table->string('material')->nullable()->after('color');

            // `price` (unchanged) already *is* precio menudeo — the price
            // a customer pays, used everywhere in the storefront/checkout.
            // These two are new, admin-only figures for margin visibility:
            // never rendered on any customer-facing page.
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('price');
            $table->decimal('cost', 10, 2)->nullable()->after('wholesale_price');
        });

        // A plain ->unique() on a nullable column breaks on sqlsrv the
        // moment more than one row has a NULL sku: SQL Server's default
        // unique index treats every NULL as equal to every other NULL
        // (this is standards-compliant per SQL Server's own docs, but
        // differs from sqlite/mysql/pgsql, which all allow multiple NULLs
        // in a unique column since NULL is never "equal" to NULL for
        // uniqueness purposes there). Reproduced for real against this
        // project's dev SQL Server the moment this ran against the 12
        // seeded products that have no sku yet — a filtered index (unique
        // only among the non-null rows) is the standard sqlsrv fix.
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::statement('create unique index products_sku_unique on products (sku) where sku is not null');
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('sku');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::statement('drop index products_sku_unique on products');
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique(['sku']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sku', 'color', 'material', 'wholesale_price', 'cost']);
        });
    }
};
