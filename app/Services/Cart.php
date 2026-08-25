<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Session-backed cart — no cart table, no login required. Everything the
 * rest of the app touches goes through this class so the storage shape
 * (currently `session('cart')` = [product_id => quantity]) can change
 * without hunting down every place that reads/writes it directly.
 */
class Cart
{
    private const SESSION_KEY = 'cart';

    /** @return array<int, int> product_id => quantity */
    public static function raw(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public static function add(int $productId, int $quantity = 1): void
    {
        $items = self::raw();
        $items[$productId] = ($items[$productId] ?? 0) + $quantity;
        Session::put(self::SESSION_KEY, $items);
    }

    public static function update(int $productId, int $quantity): void
    {
        $items = self::raw();

        if ($quantity <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $quantity;
        }

        Session::put(self::SESSION_KEY, $items);
    }

    public static function remove(int $productId): void
    {
        self::update($productId, 0);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function count(): int
    {
        return array_sum(self::raw());
    }

    /**
     * Hydrated cart lines with their product loaded and a subtotal
     * computed from the product's *current* price — the same "never trust
     * a stale number" rule the checkout transaction re-applies server-side.
     * Silently drops lines whose product no longer exists or was
     * deactivated, rather than erroring the whole cart out.
     */
    public static function items(): Collection
    {
        $raw = self::raw();

        if (empty($raw)) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', array_keys($raw))
            ->where('is_active', true)
            ->get()
            ->map(fn (Product $product) => (object) [
                'product' => $product,
                'quantity' => min($raw[$product->id], max($product->stock, 0)),
                'subtotal' => $product->price * min($raw[$product->id], max($product->stock, 0)),
            ]);
    }

    public static function total(): float
    {
        return (float) self::items()->sum('subtotal');
    }
}
