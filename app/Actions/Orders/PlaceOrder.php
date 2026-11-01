<?php

namespace App\Actions\Orders;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Charges the customer and creates a paid order.
 *
 * Retrying with the same checkout key returns the order created by the first attempt.
 */
final class PlaceOrder
{
    public function __construct(
        private PaymentGateway $gateway,
        private RecordAuditEntry $audit,
    ) {}

    public function handle(User $customer, int $amount, string $paymentToken, string $checkoutKey): Order
    {
        if ($existing = $customer->orders()->firstWhere('checkout_key', $checkoutKey)) {
            return $existing;
        }

        $charge = $this->gateway->charge($amount, $paymentToken, "checkout-{$checkoutKey}");

        return DB::transaction(function () use ($customer, $amount, $checkoutKey, $charge) {
            $order = $customer->orders()->create([
                'amount' => $amount,
                'status' => OrderStatus::Paid,
                'gateway_charge_id' => $charge->id,
                'checkout_key' => $checkoutKey,
                'paid_at' => now(),
            ]);

            $this->audit->handle($customer, 'order.placed', $order, [
                'amount' => $amount,
                'gateway_charge_id' => $charge->id,
            ]);

            return $order;
        });
    }
}
