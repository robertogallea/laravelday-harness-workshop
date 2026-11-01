<?php

namespace App\Listeners;

use App\Events\OrderRefunded;
use App\Notifications\RefundIssued;

class NotifyCustomerOfRefund
{
    public function handle(OrderRefunded $event): void
    {
        $event->order->user->notify(new RefundIssued($event->order, $event->refund));
    }
}
