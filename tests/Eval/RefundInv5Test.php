<?php

// REFUND-INV-5: stessa Idempotency-Key → un solo rimborso (DB e gateway); chiavi diverse → rimborsi distinti.

use App\Models\User;

describe('REFUND-INV-5', function () {
    it('refunds once when the same request is sent twice with the same key', function () {
        $finance = User::factory()->finance()->create();
        $order = eval_order(5000);

        eval_refund($finance, $order, 1000, 'retry-key-1');
        eval_refund($finance, $order->fresh(), 1000, 'retry-key-1');

        expect($this->gateway->refundsFor($order->gateway_charge_id))->toHaveCount(1)
            ->and($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(1000)
            ->and(eval_refund_rows($order))->toBe(1);
    });

    it('refunds twice for two requests with different keys and the same amount', function () {
        $finance = User::factory()->finance()->create();
        $order = eval_order(5000);

        eval_refund($finance, $order, 1000, 'key-a');
        eval_refund($finance, $order->fresh(), 1000, 'key-b');

        expect($this->gateway->refundsFor($order->gateway_charge_id))->toHaveCount(2)
            ->and($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(2000)
            ->and(eval_refund_rows($order))->toBe(2);
    });

    it('refunds twice for two requests with different keys and different amounts', function () {
        $finance = User::factory()->finance()->create();
        $order = eval_order(5000);

        eval_refund($finance, $order, 1000, 'key-c');
        eval_refund($finance, $order->fresh(), 700, 'key-d');

        expect($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(1700)
            ->and(eval_refund_rows($order))->toBe(2);
    });
})->group('REFUND-INV-5');
