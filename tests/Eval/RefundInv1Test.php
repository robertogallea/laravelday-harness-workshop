<?php

// REFUND-INV-1: solo finance e admin possono fare un rimborso parziale (OrderPolicy::refund).
// L'eccezione legacy (support può fare il rimborso totale) la controlla la Baseline: RefundServiceTest.

use App\Models\User;

describe('REFUND-INV-1', function () {
    it('does not let support refund part of an order', function () {
        $order = eval_order(5000);

        eval_refund(User::factory()->support()->create(), $order, 1000);

        expect(eval_refund_rows($order))->toBe(0)
            ->and($this->gateway->refundCalls)->toBe(0)
            ->and(eval_status($order))->toBe('paid');
    });

    it('does not let a customer refund part of an order, not even their own', function () {
        $customer = User::factory()->create();
        $order = eval_order(5000);
        $order->update(['user_id' => $customer->id]);

        eval_refund($customer, $order, 1000);

        expect(eval_refund_rows($order))->toBe(0)
            ->and($this->gateway->refundCalls)->toBe(0)
            ->and(eval_status($order))->toBe('paid');
    });

    it('lets finance refund part of an order', function () {
        $order = eval_order(5000);

        eval_refund(User::factory()->finance()->create(), $order, 1500);

        expect($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(1500)
            ->and(eval_refund_rows($order))->toBe(1);
    });

    it('lets admin refund part of an order', function () {
        $order = eval_order(5000);

        eval_refund(User::factory()->admin()->create(), $order, 1500);

        expect($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(1500)
            ->and(eval_refund_rows($order))->toBe(1);
    });
})->group('REFUND-INV-1');
