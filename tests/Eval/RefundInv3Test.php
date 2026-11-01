<?php

// REFUND-INV-3: Refund, stato dell'ordine e audit si scrivono tutti o nessuno.
// Il guasto si inietta con un trigger SQLite: la scrittura indicata fallisce.

use App\Models\User;

describe('REFUND-INV-3', function () {
    // Il rimborso dell'intero importo cambia lo stato dell'ordine in qualunque implementazione
    // ragionevole: così il guasto su orders colpisce davvero una scrittura, senza ripenalizzare
    // chi tiene "paid" dopo un parziale (quello è ORDER-INV-1).
    it('writes the refund and the new order status together when nothing fails', function () {
        $order = eval_order(5000);

        eval_refund(User::factory()->finance()->create(), $order, 5000);

        expect(eval_refund_rows($order))->toBe(1)
            ->and(eval_status($order))->not->toBe('paid');
    });

    it('leaves no refund, no audit and the order untouched when writing the refund fails', function () {
        $order = eval_order(5000);
        $audits = eval_audit_rows();
        eval_fail_on_refund_insert();

        eval_refund(User::factory()->finance()->create(), $order, 1000);

        expect(eval_refund_rows($order))->toBe(0)
            ->and(eval_status($order))->toBe('paid')
            ->and(eval_audit_rows())->toBe($audits);
    });

    it('leaves no refund and no audit when updating the order fails', function () {
        $order = eval_order(5000);
        $audits = eval_audit_rows();
        eval_fail_on('orders', 'UPDATE');

        eval_refund(User::factory()->finance()->create(), $order, 5000);

        expect(eval_refund_rows($order))->toBe(0)
            ->and(eval_status($order))->toBe('paid')
            ->and(eval_audit_rows())->toBe($audits);
    });
})->group('REFUND-INV-3');
