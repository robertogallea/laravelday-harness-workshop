<?php

namespace App\Actions\Refunds;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrderStatus;
use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues a partial refund against a paid order.
 *
 * @contract docs/contracts/refunds.partial.md
 */
final class RefundPartially
{
    public function __construct(
        private PaymentGateway $gateway,
        private RecordAuditEntry $audit,
    ) {}

    public function handle(Order $order, User $actor, int $amount, string $reason, string $idempotencyKey): Refund
    {
        $existing = $order->refunds()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            if ($existing->amountInCents() !== $amount) {
                // Let the gateway enforce its idempotency contract for a reused
                // key with different payment parameters.
                $this->gateway->refund($order->gateway_charge_id, $amount, $idempotencyKey);
            }

            return $existing;
        }

        $refundedCents = $order->refunds->sum(fn (Refund $refund): int => $refund->amountInCents());

        if ($amount > $order->amount - $refundedCents) {
            throw ValidationException::withMessages([
                'amount' => 'The amount exceeds the refundable balance.',
            ]);
        }

        $gatewayRefund = $this->gateway->refund($order->gateway_charge_id, $amount, $idempotencyKey);

        $refund = DB::transaction(function () use ($order, $actor, $amount, $reason, $idempotencyKey, $gatewayRefund): Refund {
            $refund = $order->refunds()->create([
                'amount' => $amount / 100,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'gateway_refund_id' => $gatewayRefund->id,
                'refunded_by' => $actor->id,
            ]);

            $totalRefundedCents = $order->refunds()->get()
                ->sum(fn (Refund $item): int => $item->amountInCents());

            $order->update([
                'status' => $totalRefundedCents === $order->amount
                    ? OrderStatus::Refunded
                    : OrderStatus::PartiallyRefunded,
            ]);

            $this->audit->handle($actor, 'order.partially_refunded', $refund, [
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'gateway_refund_id' => $gatewayRefund->id,
            ]);

            return $refund;
        });

        OrderRefunded::dispatch($order->fresh(), $refund);

        return $refund;
    }
}
