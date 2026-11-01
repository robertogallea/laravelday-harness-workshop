<?php

namespace App\Payments;

use App\Payments\Exceptions\IdempotencyKeyReused;
use App\Payments\Exceptions\PaymentGatewayException;

interface PaymentGateway
{
    /**
     * @throws IdempotencyKeyReused when the key was already used with different parameters
     * @throws PaymentGatewayException
     */
    public function charge(int $amountCents, string $token, string $idempotencyKey): GatewayCharge;

    /**
     * @throws IdempotencyKeyReused when the key was already used with different parameters
     * @throws PaymentGatewayException
     */
    public function refund(string $chargeId, int $amountCents, string $idempotencyKey): GatewayRefund;
}
