<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RefundIssued;
use App\Services\RefundService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

it('lets support refund an order in full', function () {
    $support = User::factory()->support()->create();
    $order = Order::factory()->paid()->create(['amount' => 1990]);

    $refund = app(RefundService::class)->refundInFull($order, $support);

    expect($refund->amount)->toBe('19.90')
        ->and($refund->refunded_by)->toBe($support->id)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);

    $this->payments->assertRefunded($order->gateway_charge_id, 1990);
});

it('does not let a customer refund an order', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->paid()->for($customer)->create();

    expect(fn () => app(RefundService::class)->refundInFull($order, $customer))
        ->toThrow(AuthorizationException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    $this->payments->assertNothingRefunded();
});

it('does not refund an order twice', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create();

    app(RefundService::class)->refundInFull($order, $finance);

    expect(fn () => app(RefundService::class)->refundInFull($order->fresh(), $finance))
        ->toThrow(DomainException::class);

    expect($order->refunds()->count())->toBe(1)
        ->and($this->payments->refundsFor($order->gateway_charge_id))->toHaveCount(1);
});

it('notifies the customer', function () {
    Notification::fake();
    $order = Order::factory()->paid()->create();

    app(RefundService::class)->refundInFull($order, User::factory()->finance()->create());

    Notification::assertSentTo($order->user, RefundIssued::class);
});

it('sends the full amount to the gateway with a key derived from the order', function () {
    $order = Order::factory()->paid()->create(['amount' => 4500]);

    app(RefundService::class)->refundInFull($order, User::factory()->admin()->create());

    $refunds = $this->payments->refundsByKey();

    expect($refunds)->toHaveKey("refund-{$order->id}")
        ->and($refunds["refund-{$order->id}"]->amount)->toBe(4500)
        ->and($refunds["refund-{$order->id}"]->chargeId)->toBe($order->gateway_charge_id);
});
