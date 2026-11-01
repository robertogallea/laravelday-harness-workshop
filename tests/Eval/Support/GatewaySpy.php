<?php

namespace Tests\Eval\Support;

use App\Payments\Exceptions\IdempotencyKeyReused;
use App\Payments\GatewayCharge;
use App\Payments\GatewayRefund;
use App\Payments\PaymentGateway;
use Illuminate\Support\Str;

/**
 * Gateway della suite di valutazione: stessa semantica Stripe-like del fake del progetto
 * (stessa chiave e stessi parametri → stesso risultato; stessa chiave e parametri diversi →
 * IdempotencyKeyReused; nessun controllo del tetto), ma indipendente da quello che l'agente
 * può aver cambiato in app/Payments/FakePaymentGateway.php.
 */
final class GatewaySpy implements PaymentGateway
{
    /** @var array<string, array{charge: string, amount: int, refund: GatewayRefund}> */
    public array $refunds = [];

    /** @var array<string, GatewayCharge> */
    public array $charges = [];

    public int $refundCalls = 0;

    public function charge(int $amountCents, string $token, string $idempotencyKey): GatewayCharge
    {
        return $this->charges[$idempotencyKey] ??= new GatewayCharge('ch_'.Str::random(14), $amountCents);
    }

    public function refund(string $chargeId, int $amountCents, string $idempotencyKey): GatewayRefund
    {
        $this->refundCalls++;
        if ($seen = $this->refunds[$idempotencyKey] ?? null) {
            if ($seen['charge'] !== $chargeId || $seen['amount'] !== $amountCents) {
                throw new IdempotencyKeyReused($idempotencyKey);
            }

            return $seen['refund'];
        }
        $refund = new GatewayRefund('re_'.Str::random(14), $chargeId, $amountCents);
        $this->refunds[$idempotencyKey] = ['charge' => $chargeId, 'amount' => $amountCents, 'refund' => $refund];

        return $refund;
    }

    /** @return list<GatewayRefund> rimborsi distinti emessi per l'addebito */
    public function refundsFor(string $chargeId): array
    {
        return array_values(array_map(
            fn (array $r) => $r['refund'],
            array_filter($this->refunds, fn (array $r) => $r['charge'] === $chargeId),
        ));
    }

    public function refundedTotal(string $chargeId): int
    {
        return array_sum(array_map(fn (GatewayRefund $r) => $r->amount, $this->refundsFor($chargeId)));
    }
}
