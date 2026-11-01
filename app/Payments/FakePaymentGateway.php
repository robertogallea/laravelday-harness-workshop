<?php

namespace App\Payments;

use App\Payments\Exceptions\IdempotencyKeyReused;
use App\Payments\Exceptions\PaymentGatewayException;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

final class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, array{amount: int, token: string, charge: GatewayCharge}> */
    private array $chargesByKey = [];

    /** @var array<string, array{charge: string, amount: int, refund: GatewayRefund}> */
    private array $refundsByKey = [];

    private bool $failNextRefund = false;

    public function charge(int $amountCents, string $token, string $idempotencyKey): GatewayCharge
    {
        if ($seen = $this->chargesByKey[$idempotencyKey] ?? null) {
            if ($seen['amount'] !== $amountCents || $seen['token'] !== $token) {
                throw new IdempotencyKeyReused($idempotencyKey);
            }

            return $seen['charge'];
        }

        $charge = new GatewayCharge('ch_'.Str::random(14), $amountCents);
        $this->chargesByKey[$idempotencyKey] = ['amount' => $amountCents, 'token' => $token, 'charge' => $charge];

        return $charge;
    }

    public function refund(string $chargeId, int $amountCents, string $idempotencyKey): GatewayRefund
    {
        if ($seen = $this->refundsByKey[$idempotencyKey] ?? null) {
            if ($seen['charge'] !== $chargeId || $seen['amount'] !== $amountCents) {
                throw new IdempotencyKeyReused($idempotencyKey);
            }

            return $seen['refund'];
        }

        if ($this->failNextRefund) {
            $this->failNextRefund = false;

            throw new PaymentGatewayException('The payment provider is unavailable.');
        }

        $refund = new GatewayRefund('re_'.Str::random(14), $chargeId, $amountCents);
        $this->refundsByKey[$idempotencyKey] = ['charge' => $chargeId, 'amount' => $amountCents, 'refund' => $refund];

        return $refund;
    }

    public function failNextRefund(): void
    {
        $this->failNextRefund = true;
    }

    /**
     * @return list<GatewayCharge>
     */
    public function charges(): array
    {
        return array_values(array_map(fn (array $entry) => $entry['charge'], $this->chargesByKey));
    }

    /**
     * @return list<GatewayRefund>
     */
    public function refundsFor(string $chargeId): array
    {
        return array_values(array_filter(
            array_map(fn (array $entry) => $entry['refund'], $this->refundsByKey),
            fn (GatewayRefund $refund) => $refund->chargeId === $chargeId,
        ));
    }

    /**
     * @return array<string, GatewayRefund>
     */
    public function refundsByKey(): array
    {
        return array_map(fn (array $entry) => $entry['refund'], $this->refundsByKey);
    }

    public function assertRefunded(string $chargeId, int $amountCents): void
    {
        $matching = array_filter(
            $this->refundsFor($chargeId),
            fn (GatewayRefund $refund) => $refund->amount === $amountCents,
        );

        Assert::assertNotEmpty($matching, "No refund of [{$amountCents}] was issued for charge [{$chargeId}].");
    }

    public function assertNothingRefunded(): void
    {
        Assert::assertEmpty($this->refundsByKey, 'Unexpected refunds were issued.');
    }
}
