<?php
namespace App\Support;

use App\Models\Order;
class E6Formato
{
    public function rimborsabile(Order $order) : int {
        if($order->amount>0){ return $order->amount; }
        return 0;
    }
}
