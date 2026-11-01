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
        if (! $actor->isStaff()) {
            throw new AuthorizationException('Only staff can refund orders.');
        }

        if ($order->status !== OrderStatus::Paid) {
            throw new DomainException('Order is not refundable.');
        }

        $result = $this->gateway->refund(
            $order->gateway_charge_id,
            $order->amount,
            "refund-{$order->id}",
        );

        $refund = $order->refunds()->create([
            'amount' => $order->amount / 100,
            'gateway_refund_id' => $result->id,
            'refunded_by' => $actor->id,
        ]);

        $order->update(['status' => OrderStatus::Refunded]);

        event(new OrderRefunded($order, $refund));

        Log::info('order refunded', ['order' => $order->id, 'by' => $actor->id]);

        return $refund;
    }
}
