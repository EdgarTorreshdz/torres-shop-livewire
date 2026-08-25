<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\View\View;

class CategoryController extends Controller
{
    // Plain Blade for the same reason as ProductController::show() — real
    // per-category SEO tags in the first response, not expressible through
    // a Livewire full-page component's #[Layout] attribute.
    public function show(string $slug): View
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        return view('storefront.category-show', [
            'category' => $category,
            'products' => $category->products()->with('images')->where('is_active', true)->orderBy('name')->paginate(9),
            'seoTitle' => $category->meta_title ?: $category->name,
            'seoDescription' => $category->meta_description ?: $category->description,
        ]);
    }
}
