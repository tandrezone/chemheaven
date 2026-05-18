<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Payment;

use Tandrezone\Chemheaven\Payment\Drivers\OxoPayDriver;
use RuntimeException;

class PaymentManager
{
    private array $drivers = [];
    private string $defaultDriver = 'oxo';

    public function __construct()
    {
        // Register default Oxo Pay driver
        $this->registerDriver('oxo', new OxoPayDriver());
    }

    public function registerDriver(string $name, PaymentGatewayInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function getDriver(?string $name = null): PaymentGatewayInterface
    {
        $name ??= $this->defaultDriver;
        if (!isset($this->drivers[$name])) {
            throw new RuntimeException("Payment driver '{$name}' not registered.");
        }
        return $this->drivers[$name];
    }
}
