<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_h_escapes_html(): void
{
    $escaped = h("<script>alert('x')</script>");
    assert_true($escaped === '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', 'h() should escape HTML special chars');
}

function test_format_price_uses_two_decimals(): void
{
    assert_true(format_price(1234.5) === '€1,234.50', 'format_price() should format with separators and 2 decimals');
}

function test_generate_uuid_returns_rfc4122_v4_shape(): void
{
    $uuid = generate_uuid();
    assert_true((bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $uuid), 'generate_uuid() should return v4 UUID');
}

function test_cart_get_ignores_non_array_session_value(): void
{
    $_SESSION = ['cart' => 'invalid'];
    assert_true(cart_get() === [], 'cart_get() should return [] when session cart is malformed');
}

function test_cart_count_ignores_invalid_lines_and_negative_qty(): void
{
    $_SESSION = [
        'cart' => [
            '1' => ['quantity' => 2],
            '2' => ['quantity' => '3'],
            '3' => ['quantity' => -5],
            '4' => ['foo' => 'bar'],
            '5' => 'bad-line',
        ],
    ];

    assert_true(cart_count() === 5, 'cart_count() should only include positive quantities');
}

function test_cart_total_ignores_invalid_lines_negative_price_and_negative_qty(): void
{
    $_SESSION = [
        'cart' => [
            '1' => ['price' => 10.5, 'quantity' => 2],
            '2' => ['price' => '3.25', 'quantity' => '4'],
            '3' => ['price' => -9.0, 'quantity' => 1],
            '4' => ['price' => 1.0, 'quantity' => -2],
            '5' => ['foo' => 'bar'],
            '6' => 'bad-line',
        ],
    ];

    assert_true(abs(cart_total() - 34.0) < 0.00001, 'cart_total() should only include valid non-negative priced lines with positive qty');
}

function test_cart_update_caps_to_999_and_removes_when_zero(): void
{
    $_SESSION = [
        'cart' => [
            '42' => ['quantity' => 1],
        ],
    ];

    cart_update(42, 5000);
    assert_true($_SESSION['cart']['42']['quantity'] === 999, 'cart_update() should cap quantity to 999');

    cart_update(42, 0);
    assert_true(!isset($_SESSION['cart']['42']), 'cart_update() should remove item when qty is zero');
}

