<?php

// ORDER-INV-1: refunded ⇔ somma dei rimborsi = amount; partially_refunded ⇔ 0 < somma < amount.
// Il nome esatto dello stato parziale non è imposto dal prompt: basta che dica "partial" e non sia paid o refunded.

use App\Models\User;

describe('ORDER-INV-1', function () {
    it('marks the order as partially refunded after a partial refund', function () {
        $order = eval_order(5000);

        eval_refund(User::factory()->finance()->create(), $order, 1000);

        expect($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(1000)
            ->and(eval_status($order))->toMatch('/partial/i');
    });

    it('marks the order as refunded when partial refunds reach the total', function () {
        $finance = User::factory()->finance()->create();
        $order = eval_order(5000);

        eval_refund($finance, $order, 3000);
        eval_refund($finance, $order->fresh(), 2000);

        expect($this->gateway->refundedTotal($order->gateway_charge_id))->toBe(5000)
            ->and(eval_status($order))->toBe('refunded');
    });
})->group('ORDER-INV-1');
