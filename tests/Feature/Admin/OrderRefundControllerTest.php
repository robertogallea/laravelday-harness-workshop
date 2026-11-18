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

it('partially refunds an order from the back office', function () {
    $payments = $this->fakePayments();
    $support = User::factory()->support()->create();
    $order = Order::factory()->paid()->create(['amount' => 3200]);

    $this->actingAs($support)
        ->from("/admin/orders/{$order->id}")
        ->post("/admin/orders/{$order->id}/refunds", ['amount' => 1200, 'reason' => 'Articolo danneggiato'])
        ->assertRedirect("/admin/orders/{$order->id}")
        ->assertSessionHas('status', 'Ordine rimborsato parzialmente.');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->refunds)->toHaveCount(1)
        ->and($order->refunds->first()->amount)->toBe('12.00')
        ->and($order->refunds->first()->reason)->toBe('Articolo danneggiato');
    $payments->assertRefunded($order->gateway_charge_id, 1200);
});

it('validates the partial refund input', function () {
    $order = Order::factory()->paid()->create(['amount' => 3200]);

    $this->actingAs(User::factory()->support()->create())
        ->from("/admin/orders/{$order->id}")
        ->post("/admin/orders/{$order->id}/refunds", ['amount' => 0])
        ->assertRedirect("/admin/orders/{$order->id}")
        ->assertSessionHasErrors(['amount', 'reason']);
});

it('shows the order to staff', function () {
    $order = Order::factory()->paid()->create(['amount' => 1250]);

    $this->actingAs(User::factory()->support()->create())
        ->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('12,50');
});
