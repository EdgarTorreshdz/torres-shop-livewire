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
 * Each line is keyed by "{product_id}:{color_id}" (color_id 0 = no color
 * chosen, for a colorless product) rather than just product_id — the same
 * product in two different colors has to be two separate cart lines, with
 * their own quantity and (since a color can have its own price) their own
 * unit price.
 */
class Cart
{
    private const SESSION_KEY = 'cart';

    /** @return array<string, array{product_id: int, color_id: ?int, quantity: int}> keyed by line key */
    public static function raw(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public static function lineKey(int $productId, ?int $colorId): string
    {
        return "{$productId}:".($colorId ?? 0);
    }

    public static function add(int $productId, ?int $colorId, int $quantity = 1): void
    {
        $items = self::raw();
        $key = self::lineKey($productId, $colorId);

        $items[$key] = [
            'product_id' => $productId,
            'color_id' => $colorId,
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
     * Hydrated cart lines with their product (and color, if any) loaded,
     * quantity/price/subtotal computed from *current* database values —
     * the same "never trust a stale number" rule the checkout transaction
     * re-applies server-side. Silently drops lines whose product no
     * longer exists/was deactivated, or whose chosen color was deleted,
     * rather than erroring the whole cart out.
     */
    public static function items(): Collection
    {
        $raw = self::raw();

        if (empty($raw)) {
            return collect();
        }

        $productIds = array_unique(array_column($raw, 'product_id'));

        $products = Product::query()
            ->with('colors')
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

                $color = $line['color_id'] ? $product->colors->firstWhere('id', $line['color_id']) : null;

                // A color was chosen but no longer exists (deleted since
                // the item was added) — drop the line rather than silently
                // falling back to the base product/price, which the
                // customer never actually selected.
                if ($line['color_id'] && ! $color) {
                    return null;
                }

                $availableStock = $color ? $color->stock : $product->stock;
                $unitPrice = $color?->effective_price ?? $product->price;
                $quantity = min($line['quantity'], max($availableStock, 0));

                return (object) [
                    'key' => self::lineKey($product->id, $color?->id),
                    'product' => $product,
                    'color' => $color,
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
