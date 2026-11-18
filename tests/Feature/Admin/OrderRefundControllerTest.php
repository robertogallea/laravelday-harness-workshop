<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

it('refunds an order from the back office', function () {
    $payments = $this->fakePayments();
    $support = User::factory()->support()->create();
    $order = Order::factory()->paid()->create(['amount' => 3200]);

    $this->actingAs($support)
        ->from("/admin/orders/{$order->id}")
        ->post("/admin/orders/{$order->id}/refund")
        ->assertRedirect("/admin/orders/{$order->id}")
        ->assertSessionHas('status', 'Ordine rimborsato.');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
    $payments->assertRefunded($order->gateway_charge_id, 3200);
});

it('shows the order to staff', function () {
    $order = Order::factory()->paid()->create(['amount' => 1250]);

    $this->actingAs(User::factory()->support()->create())
        ->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('12,50');
});

it('partially refunds an order from the back office', function () {
    $payments = $this->fakePayments();
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 3200]);

    $this->actingAs($finance)
        ->from("/admin/orders/{$order->id}")
        ->post("/admin/orders/{$order->id}/refunds", ['amount' => 1200, 'reason' => 'Articolo mancante'])
        ->assertRedirect("/admin/orders/{$order->id}")
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Rimborso parziale registrato.');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->refunds()->sole()->reason)->toBe('Articolo mancante');
    $payments->assertRefunded($order->gateway_charge_id, 1200);

    $this->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Articolo mancante')
        ->assertSee('20,00'); // residuo rimborsabile
});

it('validates the partial refund request', function (array $payload, string $field) {
    $payments = $this->fakePayments();
    $order = Order::factory()->paid()->create(['amount' => 3200]);

    $this->actingAs(User::factory()->finance()->create())
        ->from("/admin/orders/{$order->id}")
        ->post("/admin/orders/{$order->id}/refunds", $payload)
        ->assertRedirect("/admin/orders/{$order->id}")
        ->assertSessionHasErrors($field);

    $payments->assertNothingRefunded();
})->with([
    'missing amount' => [['reason' => 'x'], 'amount'],
    'zero amount' => [['amount' => 0, 'reason' => 'x'], 'amount'],
    'decimal amount' => [['amount' => '12.50', 'reason' => 'x'], 'amount'],
    'over the total' => [['amount' => 3201, 'reason' => 'x'], 'amount'],
    'missing reason' => [['amount' => 100], 'reason'],
]);

it('rejects a partial refund on an already refunded order', function () {
    $payments = $this->fakePayments();
    $order = Order::factory()->refunded()->create(['amount' => 3200]);

    $this->actingAs(User::factory()->finance()->create())
        ->post("/admin/orders/{$order->id}/refunds", ['amount' => 100, 'reason' => 'x'])
        ->assertSessionHasErrors('amount');

    $payments->assertNothingRefunded();
});

it('forbids customers from issuing partial refunds', function () {
    $payments = $this->fakePayments();
    $customer = User::factory()->create();
    $order = Order::factory()->paid()->for($customer)->create();

    $this->actingAs($customer)
        ->post("/admin/orders/{$order->id}/refunds", ['amount' => 100, 'reason' => 'x'])
        ->assertForbidden();

    $payments->assertNothingRefunded();
});
