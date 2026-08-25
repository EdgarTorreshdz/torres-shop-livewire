<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Plain Blade controller (not a Livewire/Volt full-page component) on
     * purpose: this page's whole reason to exist is real per-product SEO
     * meta tags in the first server response — a Livewire full-page
     * component can't express that (its #[Layout] attribute params are a
     * PHP attribute argument, so they must be compile-time constants, not
     * `$this->product->meta_title`). The one bit of real interactivity
     * (quantity + add-to-cart) is an embedded Livewire component instead —
     * see resources/views/livewire/storefront/add-to-cart.blade.php.
     */
    public function show(string $slug): View
    {
        $product = Product::with(['category', 'images', 'colors.images'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $relatedProducts = $product->category_id
            ? Product::with(['images', 'colors.images'])
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(12)
                ->get()
            : collect();

        $featuredProducts = Product::with(['images', 'colors.images'])
            ->where('is_active', true)
            ->whereNotNull('featured_order')
            ->where('id', '!=', $product->id)
            ->orderBy('featured_order')
            ->get();

        return view('storefront.product-show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'featuredProducts' => $featuredProducts,
            'seoTitle' => $product->meta_title ?: $product->name,
            'seoDescription' => $product->meta_description ?: $product->description,
        ]);
    }
}
