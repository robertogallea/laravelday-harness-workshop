<?php

namespace Database\Seeders;

use App\Actions\Orders\CancelOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class E6Seeder extends Seeder
{
    public function run(CancelOrder $cancel): void
    {
        $admin = User::query()->firstOrFail();

        Order::query()->each(fn (Order $order) => $cancel->handle($order, $admin, 42));
    }
}
