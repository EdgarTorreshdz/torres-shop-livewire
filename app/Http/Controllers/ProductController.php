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
        $product = Product::with(['category', 'images'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('storefront.product-show', [
            'product' => $product,
            'seoTitle' => $product->meta_title ?: $product->name,
            'seoDescription' => $product->meta_description ?: $product->description,
        ]);
    }
}
