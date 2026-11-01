<?php

namespace App\Payments\Exceptions;

class IdempotencyKeyReused extends PaymentGatewayException
{
    public function __construct(public readonly string $idempotencyKey)
    {
        parent::__construct("Idempotency key [{$idempotencyKey}] was already used with different parameters.");
    }
}
