<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Anonymised copy of the staging database.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        fake()->seed(20250210);

        User::factory()->admin()->create(['name' => 'Giulia Ferri', 'email' => 'admin@example.com']);
        $finance = User::factory()->finance()->create(['name' => 'Marco Lentini', 'email' => 'finance@example.com']);
        $support = User::factory()->support()->create(['name' => 'Sara Bonomo', 'email' => 'support@example.com']);

        $customers = User::factory(12)->create();
        $customers->first()->update(['email' => 'customer@example.com']);

        $start = Carbon::parse('2025-02-17 09:00:00');

        $statuses = [
            ...array_fill(0, 28, OrderStatus::Paid),
            ...array_fill(0, 6, OrderStatus::Pending),
            ...array_fill(0, 4, OrderStatus::Cancelled),
        ];

        foreach ($statuses as $i => $status) {
            $this->order($customers, $status, fake()->numberBetween(4, 300) * 50, $start->copy()->addDays($i * 6));
        }

        $refunded = [
            [1990, $support, '2025-03-11 10:24:00'],
            [450, $support, '2025-04-02 16:05:00'],
            [12900, $finance, '2025-05-20 11:47:00'],
            [5490, $support, '2025-07-08 09:31:00'],
            [1200, $support, '2025-09-15 14:12:00'],
            [8999, $finance, '2025-10-27 17:58:00'],
        ];

        foreach ($refunded as [$amount, $staff, $refundedAt]) {
            $refundedAt = Carbon::parse($refundedAt);
            $order = $this->order($customers, OrderStatus::Refunded, $amount, $refundedAt->copy()->subDays(9));

            Refund::forceCreate([
                'order_id' => $order->id,
                'amount' => $amount / 100,
                'gateway_refund_id' => 're_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'refunded_by' => $staff->id,
                'created_at' => $refundedAt,
                'updated_at' => $refundedAt,
            ]);

            $order->forceFill(['updated_at' => $refundedAt])->save();
        }
    }

    /**
     * @param  Collection<int, User>  $customers
     */
    private function order(Collection $customers, OrderStatus $status, int $amount, Carbon $createdAt): Order
    {
        $paid = in_array($status, [OrderStatus::Paid, OrderStatus::Refunded], true);

        return Order::forceCreate([
            'user_id' => fake()->randomElement($customers->all())->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => $status,
            'gateway_charge_id' => $paid ? 'ch_'.fake()->regexify('[A-Za-z0-9]{14}') : null,
            'checkout_key' => $paid ? fake()->uuid() : null,
            'paid_at' => $paid ? $createdAt->copy()->addMinutes(2) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
