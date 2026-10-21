<?php
declare(strict_types=1);

namespace NovaTech\Services\Payment;

interface PaymentGateway
{
    public function charge(int $amountCents, string $method): PaymentResult;
}
