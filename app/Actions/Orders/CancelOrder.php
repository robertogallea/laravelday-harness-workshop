<?php

namespace App\Actions\Orders;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an order that has not been paid yet.
 */
final class CancelOrder
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(Order $order, User $actor, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            $order->update(['status' => OrderStatus::Cancelled]);

            $this->audit->handle($actor, 'order.cancelled', $order, ['reason' => $reason]);

            OrderCancelled::dispatch($order);

            return $order;
        });
    }
}
