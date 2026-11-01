<?php

namespace App\Payments;

final readonly class GatewayRefund
{
    public function __construct(
        public string $id,
        public string $chargeId,
        public int $amount,
    ) {}

    /**
     * @param  array{id: string, charge: string, amount: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['charge'], (int) $data['amount']);
    }
}
