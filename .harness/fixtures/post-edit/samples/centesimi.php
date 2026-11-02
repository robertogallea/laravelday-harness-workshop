<?php

namespace App\Support;

use App\Models\Order;

class E6Centesimi
{
    public function inEuro(Order $order): int
    {
        return number_format($order->amount / 100, 2);
    }
}
