<?php

use App\Enums\OrderStatus;
use App\Models\AuditEntry;
use App\Models\Order;
use App\Models\User;

it('charges the customer and creates a paid order', function () {
    $customer = User::factory()->create();

    $response = $this->actingAs($customer)->postJson('/api/orders', [
        'amount' => 2490,
        'payment_token' => 'tok_visa',
    ], ['Idempotency-Key' => 'checkout-1']);

    $response->assertCreated()->assertJsonPath('data.status', 'paid');

    $order = Order::sole();
    expect($order->user_id)->toBe($customer->id)
        ->and($order->amount)->toBe(2490)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->gateway_charge_id)->toBe($this->payments->charges()[0]->id)
        ->and($order->paid_at)->not->toBeNull();

    expect(AuditEntry::where('action', 'order.placed')->count())->toBe(1);
});

it('returns the same order when the checkout is retried with the same key', function () {
    $customer = User::factory()->create();
    $payload = ['amount' => 2490, 'payment_token' => 'tok_visa'];

    $first = $this->actingAs($customer)->postJson('/api/orders', $payload, ['Idempotency-Key' => 'checkout-1']);
    $second = $this->actingAs($customer)->postJson('/api/orders', $payload, ['Idempotency-Key' => 'checkout-1']);

    $first->assertCreated();
    $second->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

    expect(Order::count())->toBe(1)
        ->and($this->payments->charges())->toHaveCount(1);
});

it('requires an idempotency key', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->postJson('/api/orders', ['amount' => 2490, 'payment_token' => 'tok_visa'])
        ->assertStatus(400);

    expect(Order::count())->toBe(0)
        ->and($this->payments->charges())->toBeEmpty();
});

it('validates the amount', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->postJson('/api/orders', ['amount' => 0, 'payment_token' => 'tok_visa'], ['Idempotency-Key' => 'checkout-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');
});
