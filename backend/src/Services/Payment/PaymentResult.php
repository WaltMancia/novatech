<?php
declare(strict_types=1);

namespace NovaTech\Services\Payment;

final readonly class PaymentResult
{
    public function __construct(public bool $approved, public ?string $reference = null)
    {
    }
}
