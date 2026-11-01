<?php

// REFUND-INV-2: 0 < amount e somma dei rimborsi ≤ orders.amount; solo ordini paid o partially_refunded.

use App\Models\User;

describe('REFUND-INV-2', function () {
    it('rejects a zero or negative amount without calling the gateway', function (int $amount) {
        $order = eval_order(5000);

        eval_refund(User::factory()->finance()->create(), $order, $amount);

        expect(eval_refund_rows($order))->toBe(0)
            ->and($this->gateway->refundCalls)->toBe(0)
            ->and(eval_status($order))->toBe('paid');
    })->with([0, -500]);

    it('rejects an amount above the order total without calling the gateway', function () {
        $order = eval_order(5000);

        eval_refund(User::factory()->finance()->create(), $order, 5001);

        expect(eval_refund_rows($order))->toBe(0)
            ->and($this->gateway->refundCalls)->toBe(0);
    });

    it('rejects a partial refund that would push the total refunded above the order total', function () {
        $finance = User::factory()->finance()->create();
        $order = eval_order(5000);

        eval_refund($finance, $order, 3000);
        eval_refund($finance, $order->fresh(), 2500);

        expect($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(3000)
            ->and(eval_refund_rows($order))->toBe(1);
    });

    it('accepts a second partial refund within the total', function () {
        $finance = User::factory()->finance()->create();
        $order = eval_order(5000);

        eval_refund($finance, $order, 2000);
        eval_refund($finance, $order->fresh(), 1000);

        expect($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(3000)
            ->and(eval_refund_rows($order))->toBe(2);
    });

    it('does not refund orders that are not paid', function (string $state) {
        $order = eval_order(5000, $state);
        $status = eval_status($order);

        eval_refund(User::factory()->finance()->create(), $order, 1000);

        expect(eval_refund_rows($order))->toBe(0)
            ->and($this->gateway->refundCalls)->toBe(0)
            ->and(eval_status($order))->toBe($status);
    })->with(['pending' => 'pending', 'cancelled' => 'cancelled', 'refunded' => 'refunded']);
})->group('REFUND-INV-2');
