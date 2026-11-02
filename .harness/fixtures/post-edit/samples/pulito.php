<?php

namespace App\Support;

use App\Models\Order;

class E6Pulito
{
    public function inCentesimi(Order $order): int
    {
        return $order->amount;
    }
}
