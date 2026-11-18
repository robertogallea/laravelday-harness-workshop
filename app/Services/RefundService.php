<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Payments\PaymentGateway;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

final class RefundService
{
    public function __construct(private PaymentGateway $gateway) {}

    public function refundInFull(Order $order, User $actor): Refund
    {
        return $this->refund($order, $actor, $this->remainingAmount($order), null);
    }

    public function refundPartially(Order $order, User $actor, int $amount, string $reason): Refund
    {
        return $this->refund($order, $actor, $amount, $reason);
    }

    private function refund(Order $order, User $actor, int $amount, ?string $reason): Refund
    {
        if (! $actor->isStaff()) {
            throw new AuthorizationException('Only staff can refund orders.');
        }

        if ($order->status !== OrderStatus::Paid) {
            throw new DomainException('Order is not refundable.');
        }

        $remainingAmount = $this->remainingAmount($order);

        if ($amount < 1 || $amount > $remainingAmount) {
            throw new DomainException('Refund amount exceeds the remaining refundable amount.');
        }

        $result = $this->gateway->refund(
            $order->gateway_charge_id,
            $amount,
            $this->idempotencyKey($order),
        );

        $refund = $order->refunds()->create([
            'amount' => $amount / 100,
            'reason' => $reason,
            'gateway_refund_id' => $result->id,
            'refunded_by' => $actor->id,
        ]);

        if ($amount === $remainingAmount) {
            $order->update(['status' => OrderStatus::Refunded]);
        }

        event(new OrderRefunded($order, $refund));

        Log::info('order refunded', ['order' => $order->id, 'by' => $actor->id, 'amount' => $amount]);

        return $refund;
    }

    private function remainingAmount(Order $order): int
    {
        $refunded = (int) round(((float) $order->refunds()->sum('amount')) * 100);

        return $order->amount - $refunded;
    }

    private function idempotencyKey(Order $order): string
    {
        $refundCount = $order->refunds()->count();

        return $refundCount === 0
            ? "refund-{$order->id}"
            : "refund-{$order->id}-".($refundCount + 1);
    }
}
