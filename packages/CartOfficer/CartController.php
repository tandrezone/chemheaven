<?php

declare(strict_types=1);

namespace CartOfficer;

/**
 * Handles the AJAX / form-POST endpoints for the shopping cart.
 *
 * Wire it up inside your own router/front-controller, e.g.:
 *
 *   $controller = new CartController(new Cart(), '/orders');
 *   $controller->handle();
 *
 * Or call individual action methods directly:
 *
 *   match ($action) {
 *       'add'    => $controller->actionAdd(),
 *       'update' => $controller->actionUpdate(),
 *       'delete' => $controller->actionDelete(),
 *       'clear'  => $controller->actionClear(),
 *       'order'  => $controller->actionOrder(),
 *       default  => $controller->actionGet(),
 *   };
 */
class CartController
{
    private Cart   $cart;
    private string $orderRoute;

    /** @var string|null Path to the product catalog JSON for price validation */
    private ?string $catalogPath;

    /**
     * @param Cart   $cart         An initialised Cart instance.
     * @param string $orderRoute   The URL that the "Create Order" action will
     *                             POST the cart payload to.
     * @param string|null $catalogPath  Absolute path to products.json for server-side
     *                                  price validation. If null, client price is trusted.
     */
    public function __construct(Cart $cart, string $orderRoute = '/orders', ?string $catalogPath = null)
    {
        $this->cart        = $cart;
        $this->orderRoute  = $orderRoute;
        $this->catalogPath = $catalogPath;
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    /**
     * Auto-dispatch based on the `action` request parameter.
     * Expects JSON or form-encoded bodies; always returns JSON.
     */
    public function handle(): void
    {
        $action = $this->input('action', 'get');

        switch ($action) {
            case 'add':
                $this->actionAdd();
                break;
            case 'update':
                $this->actionUpdate();
                break;
            case 'delete':
                $this->actionDelete();
                break;
            case 'clear':
                $this->actionClear();
                break;
            case 'order':
                $this->actionOrder();
                break;
            default:
                $this->actionGet();
        }
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /** GET  /cart?action=get  — return current cart state as JSON. */
    public function actionGet(): void
    {
        $this->jsonResponse($this->cartPayload());
    }

    /**
     * POST /cart  body: { action, product_id, product_variant, product_name, price, quantity }
     * Add a product (or increment its quantity) and return updated cart state.
     *
     * Security: price is validated against the server-side catalog if available.
     */
    public function actionAdd(): void
    {
        $productId      = $this->requireInput('product_id');
        $productVariant = $this->input('product_variant', '');
        $productName    = $this->requireInput('product_name');
        $clientPrice    = (float) $this->requireInput('price');
        $quantity       = max(1, (int) $this->input('quantity', '1'));

        // ── Input length validation ──────────────────────────────────────
        if (mb_strlen($productId) > 128 || mb_strlen($productVariant) > 128 || mb_strlen($productName) > 255) {
            $this->jsonResponse(['error' => 'Input exceeds maximum length.'], 422);
            return;
        }

        // ── Quantity sanity check ────────────────────────────────────────
        if ($quantity > 9999) {
            $this->jsonResponse(['error' => 'Quantity is too large.'], 422);
            return;
        }

        // ── Server-side price validation ─────────────────────────────────
        $price = $clientPrice;
        if ($this->catalogPath && file_exists($this->catalogPath)) {
            $verifiedPrice = $this->lookupPrice($productId, $productVariant);
            if ($verifiedPrice === null) {
                $this->jsonResponse(['error' => 'Product or variant not found in catalog.'], 422);
                return;
            }
            // Use the server-verified price, not the client-submitted one
            $price = $verifiedPrice;
        }

        $this->cart->add($productId, $productVariant, $productName, $price, $quantity);
        $this->jsonResponse($this->cartPayload());
    }

    /**
     * POST /cart  body: { action: 'update', key, quantity }
     * Update the quantity of a cart line.
     */
    public function actionUpdate(): void
    {
        $key      = $this->requireInput('key');
        $quantity = (int) $this->requireInput('quantity');

        if ($quantity > 9999) {
            $this->jsonResponse(['error' => 'Quantity is too large.'], 422);
            return;
        }

        $this->cart->update($key, $quantity);
        $this->jsonResponse($this->cartPayload());
    }

    /**
     * POST /cart  body: { action: 'delete', key }
     * Remove a single cart line.
     */
    public function actionDelete(): void
    {
        $key = $this->requireInput('key');
        $this->cart->remove($key);
        $this->jsonResponse($this->cartPayload());
    }

    /**
     * POST /cart  body: { action: 'clear' }
     * Empty the entire cart.
     */
    public function actionClear(): void
    {
        $this->cart->clear();
        $this->jsonResponse($this->cartPayload());
    }

    /**
     * POST /cart  body: { action: 'order' }
     * Collect the cart payload and redirect the browser to the order route
     * (or return the payload if called via XHR so the caller can POST it).
     */
    public function actionOrder(): void
    {
        if ($this->cart->isEmpty()) {
            $this->jsonResponse(['error' => 'Cart is empty.'], 422);
            return;
        }

        $payload = [
            'items'      => $this->cart->toArray(),
            'total'      => $this->cart->total(),
            'item_count' => $this->cart->count(),
            'order_route' => $this->orderRoute,
        ];

        $this->jsonResponse(['redirect' => $this->orderRoute, 'payload' => $payload]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function cartPayload(): array
    {
        $items = [];
        foreach ($this->cart->items() as $key => $item) {
            $items[] = array_merge($item->toArray(), [
                'key'        => $key,
                'line_total' => $item->lineTotal(),
            ]);
        }

        return [
            'items'      => $items,
            'total'      => $this->cart->total(),
            'item_count' => $this->cart->count(),
        ];
    }

    /**
     * Look up the authoritative price for a product+variant from the catalog JSON.
     */
    private function lookupPrice(string $productId, string $variantLabel): ?float
    {
        static $catalog = null;

        if ($catalog === null) {
            $raw = file_get_contents($this->catalogPath);
            $catalog = json_decode($raw ?: '', true)['products'] ?? [];
        }

        foreach ($catalog as $product) {
            if (($product['id'] ?? '') !== $productId) {
                continue;
            }
            // If variant label is empty, return first variant price
            foreach ($product['variants'] ?? [] as $variant) {
                if ($variantLabel === '' || ($variant['label'] ?? '') === $variantLabel) {
                    return (float) ($variant['price'] ?? 0.0);
                }
            }
        }

        return null;
    }

    /** Read a value from POST body (JSON or form-encoded) or GET params. */
    private function input(string $field, string $default = ''): string
    {
        // Try JSON body first
        static $jsonBody = null;
        if ($jsonBody === null) {
            $raw      = file_get_contents('php://input');
            $jsonBody = json_decode($raw ?: '', true) ?? [];
        }

        if (array_key_exists($field, $jsonBody)) {
            return (string) $jsonBody[$field];
        }

        if (isset($_POST[$field])) {
            return (string) $_POST[$field];
        }

        if (isset($_GET[$field])) {
            return (string) $_GET[$field];
        }

        return $default;
    }

    private function requireInput(string $field): string
    {
        $value = $this->input($field);
        if ($value === '') {
            $this->jsonResponse(['error' => "Missing required field: {$field}"], 422);
            exit;
        }
        return $value;
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
