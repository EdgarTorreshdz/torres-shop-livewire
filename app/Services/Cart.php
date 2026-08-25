<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Session-backed cart — no cart table, no login required. Everything the
 * rest of the app touches goes through this class so the storage shape
 * can change without hunting down every place that reads/writes it
 * directly.
 *
 * Each line is keyed by "{product_id}:{variant_id}" (variant_id 0 = the
 * product has no variants at all) rather than just product_id: the same
 * product in two different color/size combinations has to be two separate
 * lines, each with its own quantity, stock ceiling and unit price.
 */
class Cart
{
    private const SESSION_KEY = 'cart';

    /** @return array<string, array{product_id: int, variant_id: ?int, quantity: int}> keyed by line key */
    public static function raw(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public static function lineKey(int $productId, ?int $variantId): string
    {
        return "{$productId}:".($variantId ?? 0);
    }

    public static function add(int $productId, ?int $variantId, int $quantity = 1): void
    {
        $items = self::raw();
        $key = self::lineKey($productId, $variantId);

        $items[$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => ($items[$key]['quantity'] ?? 0) + $quantity,
        ];

        Session::put(self::SESSION_KEY, $items);
    }

    public static function update(string $lineKey, int $quantity): void
    {
        $items = self::raw();

        if (! isset($items[$lineKey])) {
            return;
        }

        if ($quantity <= 0) {
            unset($items[$lineKey]);
        } else {
            $items[$lineKey]['quantity'] = $quantity;
        }

        Session::put(self::SESSION_KEY, $items);
    }

    public static function remove(string $lineKey): void
    {
        self::update($lineKey, 0);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function count(): int
    {
        return array_sum(array_column(self::raw(), 'quantity'));
    }

    /**
     * Hydrated cart lines with their product (and variant, if any) loaded,
     * quantity/price/subtotal computed from *current* database values —
     * the same "never trust a stale number" rule the checkout transaction
     * re-applies server-side. Silently drops lines whose product no longer
     * exists/was deactivated, or whose chosen variant was deleted, rather
     * than erroring the whole cart out.
     */
    public static function items(): Collection
    {
        $raw = self::raw();

        if (empty($raw)) {
            return collect();
        }

        $productIds = array_unique(array_column($raw, 'product_id'));

        $products = Product::query()
            ->with(['variants.color', 'variants.size'])
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        return collect($raw)
            ->map(function (array $line) use ($products) {
                $product = $products->get($line['product_id']);

                if (! $product) {
                    return null;
                }

                $variant = $line['variant_id'] ? $product->variants->firstWhere('id', $line['variant_id']) : null;

                // A variant was chosen but no longer exists (its color or
                // size was removed since) — drop the line rather than
                // silently falling back to the bare product, which the
                // customer never actually selected.
                if ($line['variant_id'] && ! $variant) {
                    return null;
                }

                $availableStock = $variant ? $variant->stock : $product->stock;
                $unitPrice = $variant?->effective_price ?? $product->price;
                $quantity = min($line['quantity'], max($availableStock, 0));

                return (object) [
                    'key' => self::lineKey($product->id, $variant?->id),
                    'product' => $product,
                    'variant' => $variant,
                    'available_stock' => $availableStock,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice * $quantity,
                ];
            })
            ->filter()
            ->values();
    }

    public static function total(): float
    {
        return (float) self::items()->sum('subtotal');
    }
}
