<?php

// REFUND-INV-6: ogni rimborso registra attore, motivo, chiave e gateway_refund_id in audit_entries.

use App\Models\User;
use Illuminate\Support\Facades\DB;

describe('REFUND-INV-6', function () {
    it('records actor, reason, key and gateway refund id in the audit trail', function () {
        $finance = User::factory()->finance()->create();
        $order = eval_order(5000);

        eval_refund($finance, $order, 1200, 'audit-key-42', 'Spedizione arrivata danneggiata');

        $gatewayRefund = $this->gateway->refundsFor($order->gateway_charge_id)[0] ?? null;
        expect($gatewayRefund)->not->toBeNull('Nessun rimborso al gateway.');

        $entries = DB::table('audit_entries')->where('actor_id', $finance->id)->get()
            ->map(fn ($row) => json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $match = $entries->first(fn (string $row) => str_contains($row, 'Spedizione arrivata danneggiata')
            && str_contains($row, 'audit-key-42')
            && str_contains($row, $gatewayRefund->id));

        expect($match)->not->toBeNull('Nessuna riga di audit dell\'attore con motivo, chiave e gateway_refund_id: '.$entries->implode(' | '));
    });
})->group('REFUND-INV-6');
