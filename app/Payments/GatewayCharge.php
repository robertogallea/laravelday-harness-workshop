<?php

namespace App\Payments;

final readonly class GatewayCharge
{
    public function __construct(
        public string $id,
        public int $amount,
    ) {}

    /**
     * @param  array{id: string, amount: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], (int) $data['amount']);
    }
}
