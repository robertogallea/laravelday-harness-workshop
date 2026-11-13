<?php

namespace App\Actions\Refunds;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrderStatus;
use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Payments\Exceptions\IdempotencyKeyReused;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refunds part of a paid order, possibly in several steps, up to the order amount.
 *
 * Authorization (OrderPolicy::refund) is the caller's job, as for the other Actions.
 *
 * @contract docs/contracts/refunds.partial.md
 */
final class RefundOrderPartially
{
    public function __construct(
        private PaymentGateway $gateway,
        private RecordAuditEntry $audit,
    ) {}

    /**
     * @param  int  $amount  cents
     *
     * @throws ValidationException when the amount is not positive or exceeds the refundable balance
     * @throws IdempotencyKeyReused when the key was already used on this order with another amount
     */
    public function handle(Order $order, User $actor, int $amount, string $reason, string $idempotencyKey): Refund
    {
        // REFUND-INV-5: a replay returns the refund already recorded, without writing anything.
        if ($existing = $order->refunds()->firstWhere('idempotency_key', $idempotencyKey)) {
            if ($existing->amountInCents() !== $amount) {
                throw new IdempotencyKeyReused($idempotencyKey);
            }

            return $existing;
        }

        // REFUND-INV-2: never beyond the refundable balance, checked before the gateway.
        $refundable = $order->refundableCents();

        if ($amount <= 0 || $amount > $refundable) {
            throw ValidationException::withMessages([
                'amount' => "L'importo deve essere compreso fra 1 e {$refundable} centesimi.",
            ]);
        }

        // Before the transaction, with the client's key as is (REFUND-INV-5).
        $result = $this->gateway->refund($order->gateway_charge_id, $amount, $idempotencyKey);

        $refund = DB::transaction(function () use ($order, $actor, $amount, $reason, $idempotencyKey, $result) {
            $refund = $order->refunds()->create([
                'amount' => Refund::centsToEuros($amount),
                'gateway_refund_id' => $result->id,
                'refunded_by' => $actor->id,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
            ]);

            // ORDER-INV-1, compared in cents.
            $order->update([
                'status' => $order->refundedCents() >= $order->amount
                    ? OrderStatus::Refunded
                    : OrderStatus::PartiallyRefunded,
            ]);

            $this->audit->handle($actor, 'order.partially_refunded', $order, [
                'amount' => $amount,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'gateway_refund_id' => $result->id,
            ]);

            return $refund;
        });

        // REFUND-INV-4: the customer is notified only after the commit.
        OrderRefunded::dispatch($order, $refund);

        return $refund;
    }
}
