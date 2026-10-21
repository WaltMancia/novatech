<?php
declare(strict_types=1);

namespace NovaTech\Services\Payment;

/**
 * Pasarela de pruebas: el método elegido decide el resultado. Nunca recibe ni almacena datos de tarjeta,
 * por lo que PCI DSS queda fuera del alcance del proyecto.
 */
final class SimulatedPaymentGateway implements PaymentGateway
{
    public const METHODS = ['simulated_success', 'simulated_decline'];

    public function charge(int $amountCents, string $method): PaymentResult
    {
        return $method === 'simulated_success'
            ? new PaymentResult(true, 'SIM-' . strtoupper(bin2hex(random_bytes(6))))
            : new PaymentResult(false);
    }
}
