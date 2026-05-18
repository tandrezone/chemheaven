<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Payment;

interface PaymentGatewayInterface
{
    public function getName(): string;
    
    /**
     * Create a payment invoice/session.
     *
     * @param array $orderData Must contain 'total', 'email', 'name', etc.
     * @return array Response from payment provider.
     */
    public function createInvoice(array $orderData): array;
    
    /**
     * Check payment status by invoice ID.
     *
     * @return string 'pending', 'paid', or 'failed'
     */
    public function checkPaymentStatus(string $invoiceId): string;
}
