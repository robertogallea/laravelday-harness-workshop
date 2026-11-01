<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->refunded(),
            'amount' => fn (array $attributes) => Order::find($attributes['order_id'])->amount / 100,
            'gateway_refund_id' => 're_'.Str::random(14),
            'refunded_by' => User::factory()->support(),
        ];
    }
}
