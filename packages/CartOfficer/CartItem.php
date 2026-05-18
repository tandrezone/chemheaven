<?php

declare(strict_types=1);

namespace CartOfficer;

/**
 * Represents a single line item inside the shopping cart.
 */
class CartItem
{
    public string $productId;
    public string $productVariant;
    public string $productName;
    public float  $price;
    public int    $quantity;

    public function __construct(
        string $productId,
        string $productVariant,
        string $productName,
        float  $price,
        int    $quantity = 1
    ) {
        $this->productId      = $productId;
        $this->productVariant = $productVariant;
        $this->productName    = $productName;
        $this->price          = $price;
        $this->quantity       = max(1, $quantity);
    }

    /** Line total (price × quantity). */
    public function lineTotal(): float
    {
        return $this->price * $this->quantity;
    }

    /** Unique key used as the session array index. */
    public function key(): string
    {
        return $this->productId . '_' . $this->productVariant;
    }

    /** Serialise to a plain array (stored in $_SESSION). */
    public function toArray(): array
    {
        return [
            'product_id'      => $this->productId,
            'product_variant' => $this->productVariant,
            'product_name'    => $this->productName,
            'price'           => $this->price,
            'quantity'        => $this->quantity,
        ];
    }

    /** Hydrate from a plain array (read from $_SESSION). */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['product_id']      ?? ''),
            (string) ($data['product_variant'] ?? ''),
            (string) ($data['product_name']    ?? ''),
            (float)  ($data['price']           ?? 0.0),
            (int)    ($data['quantity']         ?? 1)
        );
    }
}
