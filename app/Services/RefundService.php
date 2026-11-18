<?php

namespace App\Services;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrderStatus;
use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Payments\PaymentGateway;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RefundService
{
    public function __construct(
        private PaymentGateway $gateway,
        private RecordAuditEntry $audit,
    ) {}

    /**
     * Refunds whatever is still refundable on the order (the whole amount
     * if no partial refund was issued before) and marks it as refunded.
     */
    public function refundInFull(Order $order, User $actor): Refund
    {
        return $this->refund($order, $actor, null, null);
    }

    /**
     * Refunds only part of the order. The order stays paid until the
     * refunded total reaches the order amount.
     */
    public function refundPartially(Order $order, User $actor, int $amountCents, string $reason): Refund
    {
        return $this->refund($order, $actor, $amountCents, $reason);
    }

    private function refund(Order $order, User $actor, ?int $amountCents, ?string $reason): Refund
    {
        if (! $actor->isStaff()) {
            throw new AuthorizationException('Only staff can refund orders.');
        }

        [$order, $refund] = DB::transaction(function () use ($order, $actor, $amountCents, $reason) {
            // Re-read under lock so concurrent refunds can't exceed the order amount.
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($order->status !== OrderStatus::Paid) {
                throw new DomainException('Order is not refundable.');
            }

            $refundable = $order->refundableAmount();
            $isPartial = $amountCents !== null;
            $amountCents ??= $refundable;

            if ($amountCents <= 0) {
                throw new DomainException('Refund amount must be positive.');
            }

            if ($amountCents > $refundable) {
                throw new DomainException('Refund amount exceeds the refundable amount.');
            }

            // The full refund keeps its historical key; each partial refund gets
            // its own key, stable across retries of the same refund.
            $idempotencyKey = $isPartial
                ? "refund-{$order->id}-".($order->refunds()->count() + 1)
                : "refund-{$order->id}";

            $result = $this->gateway->refund($order->gateway_charge_id, $amountCents, $idempotencyKey);

            $refund = $order->refunds()->create([
                'amount' => $amountCents / 100,
                'reason' => $reason,
                'gateway_refund_id' => $result->id,
                'refunded_by' => $actor->id,
            ]);

            if ($amountCents === $refundable) {
                $order->update(['status' => OrderStatus::Refunded]);
            }

            if ($isPartial) {
                $this->audit->handle($actor, 'order.partially_refunded', $order, [
                    'refund_id' => $refund->id,
                    'amount' => $amountCents,
                    'reason' => $reason,
                ]);
            }

            return [$order, $refund];
        });

        event(new OrderRefunded($order, $refund));

        Log::info('order refunded', [
            'order' => $order->id,
            'refund' => $refund->id,
            'amount' => $amountCents ?? $order->amount,
            'by' => $actor->id,
        ]);

        return $refund;
    }
}
