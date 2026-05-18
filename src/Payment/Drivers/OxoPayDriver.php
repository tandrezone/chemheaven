<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Payment\Drivers;

use Tandrezone\Chemheaven\Payment\PaymentGatewayInterface;

class OxoPayDriver implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'Oxo Pay';
    }

    public function createInvoice(array $orderData): array
    {
        // Generate a unique payment identifier
        $invoiceId = 'oxo_' . bin2hex(random_bytes(8));
        
        $amount = (float)($orderData['total'] ?? 0.0);
        
        // Oxo Pay / OxaPay Sandbox crypto wallet address simulation
        $mockCryptoAddress = '0x' . bin2hex(random_bytes(20));
        
        return [
            'status' => 'success',
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'address' => $mockCryptoAddress,
            'currency' => 'USDT (TRC-20)',
            'qr_data' => "ethereum:{$mockCryptoAddress}?value={$amount}",
            'redirect_url' => "/checkout/payment?invoice_id={$invoiceId}"
        ];
    }

    public function checkPaymentStatus(string $invoiceId): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $statusKey = "payment_status_{$invoiceId}";
        if (!isset($_SESSION[$statusKey])) {
            $_SESSION[$statusKey] = 'pending';
            $_SESSION[$statusKey . '_time'] = time();
        }
        
        // Auto-approve after 12 seconds to simulate sandbox verification
        if ($_SESSION[$statusKey] === 'pending' && (time() - $_SESSION[$statusKey . '_time'] > 12)) {
            $_SESSION[$statusKey] = 'paid';
        }
        
        return $_SESSION[$statusKey];
    }
}
