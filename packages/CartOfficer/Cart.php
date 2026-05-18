<?php

declare(strict_types=1);

namespace CartOfficer;

/**
 * Session-backed shopping cart.
 *
 * Usage
 * -----
 *   $cart = new Cart();
 *   $cart->add('42', 'red-L', 'Cool T-Shirt', 29.99);
 *   $cart->update('42_red-L', 3);
 *   $cart->remove('42_red-L');
 *   $items = $cart->items();
 *   $total = $cart->total();
 *   $cart->clear();
 */
class Cart
{
    private const SESSION_KEY = 'cart_officer_items';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    // -------------------------------------------------------------------------
    // Mutations
    // -------------------------------------------------------------------------

    /**
     * Add a product to the cart.  If the same productId + variant already
     * exists the quantities are summed instead of creating a duplicate entry.
     */
    public function add(
        string $productId,
        string $productVariant,
        string $productName,
        float  $price,
        int    $quantity = 1
    ): CartItem {
        $item = new CartItem($productId, $productVariant, $productName, $price, $quantity);
        $key  = $item->key();

        if (isset($_SESSION[self::SESSION_KEY][$key])) {
            $_SESSION[self::SESSION_KEY][$key]['quantity'] += $item->quantity;
            return CartItem::fromArray($_SESSION[self::SESSION_KEY][$key]);
        }

        $_SESSION[self::SESSION_KEY][$key] = $item->toArray();
        return $item;
    }

    /**
     * Update the quantity of an existing cart line,
     * removing the item when quantity reaches 0 or below.
     */
    public function update(string $key, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($key);
            return;
        }

        if (isset($_SESSION[self::SESSION_KEY][$key])) {
            $_SESSION[self::SESSION_KEY][$key]['quantity'] = $quantity;
        }
    }

    /** Remove a single line item from the cart. */
    public function remove(string $key): void
    {
        unset($_SESSION[self::SESSION_KEY][$key]);
    }

    /** Empty the cart completely. */
    public function clear(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
    }

    // -------------------------------------------------------------------------
    // Reads
    // -------------------------------------------------------------------------

    /**
     * Return all cart items as an associative array of CartItem objects.
     *
     * @return CartItem[]
     */
    public function items(): array
    {
        $result = [];
        foreach ($_SESSION[self::SESSION_KEY] as $key => $data) {
            $result[$key] = CartItem::fromArray($data);
        }
        return $result;
    }

    /** Total number of individual units across all lines. */
    public function count(): int
    {
        $count = 0;
        foreach ($_SESSION[self::SESSION_KEY] as $data) {
            $count += (int) ($data['quantity'] ?? 0);
        }
        return $count;
    }

    /** Grand total price of all lines. */
    public function total(): float
    {
        $total = 0.0;
        foreach ($_SESSION[self::SESSION_KEY] as $data) {
            $total += ((float) ($data['price'] ?? 0.0)) * ((int) ($data['quantity'] ?? 0));
        }
        return $total;
    }

    /** Return the raw session array (useful for creating order payloads). */
    public function toArray(): array
    {
        return $_SESSION[self::SESSION_KEY] ?? [];
    }

    /** True when the cart has no items. */
    public function isEmpty(): bool
    {
        return empty($_SESSION[self::SESSION_KEY]);
    }
}
