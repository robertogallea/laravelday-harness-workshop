<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(5, 400) * 50,
            'currency' => 'EUR',
            'status' => OrderStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'gateway_charge_id' => 'ch_'.Str::random(14),
            'checkout_key' => (string) Str::uuid(),
            'paid_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ]);
    }

    public function refunded(): static
    {
        return $this->paid()->state(fn (array $attributes) => [
            'status' => OrderStatus::Refunded,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled,
        ]);
    }
}
