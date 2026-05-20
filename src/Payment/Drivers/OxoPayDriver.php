<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Payment\Drivers;

use Tandrezone\Chemheaven\Payment\PaymentGatewayInterface;
use RuntimeException;

class OxoPayDriver implements PaymentGatewayInterface
{
    private string $merchantKey;
    private string $apiUrl = 'https://api.oxapay.com';

    public function __construct()
    {
        // Load key from environment variables (Dotenv) or fallback to 'sandbox'
        $this->merchantKey = $_ENV['OXOPAY_MERCHANT_KEY'] 
            ?? getenv('OXOPAY_MERCHANT_KEY') 
            ?? 'sandbox';
    }

    public function getName(): string
    {
        return 'Oxo Pay';
    }

    /**
     * Create a payment invoice/session.
     * If merchant key is 'sandbox', returns a locally simulated checkout session.
     */
    public function createInvoice(array $orderData): array
    {
        $amount = (float)($orderData['total'] ?? 0.0);
        $email = $orderData['email'] ?? '';
        $name = $orderData['name'] ?? '';
        
        // --- Sandbox mode fallback ---
        if (strtolower(trim($this->merchantKey)) === 'sandbox' || empty($this->merchantKey)) {
            $invoiceId = 'oxo_' . bin2hex(random_bytes(8));
            $mockCryptoAddress = 'T' . bin2hex(random_bytes(16)); // Simulated TRON address
            
            return [
                'status' => 'success',
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'address' => $mockCryptoAddress,
                'currency' => 'USDT (TRC-20)',
                'qr_data' => "tron:{$mockCryptoAddress}?amount={$amount}",
                'redirect_url' => "/checkout/payment?invoice_id={$invoiceId}",
                'mode' => 'sandbox'
            ];
        }

        // --- Production/Sandbox API call to OxaPay ---
        $postData = [
            'merchant' => $this->merchantKey,
            'amount' => $amount,
            'currency' => 'USD', // Base currency (USD, will calculate equivalent in selected crypto on payment widget)
            'email' => $email,
            'description' => 'ChemHeaven Order Payment',
            'callbackUrl' => $_ENV['OXOPAY_CALLBACK_URL'] ?? getenv('OXOPAY_CALLBACK_URL') ?? '',
            'returnUrl' => $_ENV['OXOPAY_RETURN_URL'] ?? getenv('OXOPAY_RETURN_URL') ?? '',
        ];

        if (!empty($orderData['order_id'])) {
            $postData['orderId'] = $orderData['order_id'];
        }

        $response = $this->makePostRequest($this->apiUrl . '/merchants/request', $postData);

        if (!isset($response['result']) || $response['result'] !== 100) {
            throw new RuntimeException("OxaPay API Error: " . ($response['message'] ?? 'Unable to generate payment link.'));
        }

        return [
            'status' => 'success',
            'invoice_id' => (string)($response['trackId'] ?? ''),
            'amount' => $amount,
            'pay_url' => $response['payUrl'] ?? '',
            'redirect_url' => $response['payUrl'] ?? '',
            'mode' => 'live'
        ];
    }

    /**
     * Check payment status by invoice ID.
     */
    public function checkPaymentStatus(string $invoiceId): string
    {
        // --- Sandbox mode check ---
        if (str_starts_with($invoiceId, 'oxo_')) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $statusKey = "payment_status_{$invoiceId}";
            if (!isset($_SESSION[$statusKey])) {
                $_SESSION[$statusKey] = 'pending';
                $_SESSION[$statusKey . '_time'] = time();
            }
            
            // Auto-approve after 12 seconds to simulate network confirmation
            if ($_SESSION[$statusKey] === 'pending' && (time() - $_SESSION[$statusKey . '_time'] > 12)) {
                $_SESSION[$statusKey] = 'paid';
            }
            
            return $_SESSION[$statusKey];
        }

        // --- Production API status check ---
        $postData = [
            'merchant' => $this->merchantKey,
            'trackId' => $invoiceId
        ];

        try {
            $response = $this->makePostRequest($this->apiUrl . '/merchants/inquiry', $postData);
            
            if (isset($response['result']) && $response['result'] === 100) {
                $status = strtolower($response['status'] ?? '');
                
                if ($status === 'paid' || $status === 'success') {
                    return 'paid';
                }
                if ($status === 'expired' || $status === 'failed') {
                    return 'failed';
                }
            }
        } catch (\Exception $e) {
            // Log or handle inquiry failure, fallback to pending
        }

        return 'pending';
    }

    /**
     * Verify incoming webhook callback HMAC signature.
     */
    public function verifyWebhook(string $rawPostData, string $hmacHeader): bool
    {
        if (empty($this->merchantKey) || strtolower($this->merchantKey) === 'sandbox') {
            return true; // Auto-verify in sandbox mode
        }

        $calculatedHmac = hash_hmac('sha512', $rawPostData, $this->merchantKey);
        return hash_equals($calculatedHmac, $hmacHeader);
    }

    /**
     * Internal helper to make curl POST requests.
     */
    private function makePostRequest(string $url, array $data): array
    {
        $ch = curl_init($url);
        
        $payload = json_encode($data);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            throw new RuntimeException("HTTP Request failed: Connection error.");
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("HTTP Request failed: Invalid JSON response (HTTP $httpCode).");
        }

        return $decoded;
    }
}
