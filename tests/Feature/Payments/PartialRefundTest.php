<?php

use App\Enums\OrderStatus;
use App\Models\AuditEntry;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RefundIssued;
use App\Services\RefundService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

it('refunds only part of an order', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 5000]);

    $refund = app(RefundService::class)->refundPartially($order, $finance, 1250, 'Articolo danneggiato');

    expect($refund->amount)->toBe('12.50')
        ->and($refund->reason)->toBe('Articolo danneggiato')
        ->and($refund->refunded_by)->toBe($finance->id)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->refundableAmount())->toBe(3750);

    $this->payments->assertRefunded($order->gateway_charge_id, 1250);
});

it('allows several partial refunds with distinct gateway keys', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 5000]);
    $service = app(RefundService::class);

    $service->refundPartially($order, $finance, 1000, 'Primo');
    $service->refundPartially($order->fresh(), $finance, 1000, 'Secondo');

    expect($this->payments->refundsFor($order->gateway_charge_id))->toHaveCount(2)
        ->and(array_keys($this->payments->refundsByKey()))->toBe(["refund-{$order->id}-1", "refund-{$order->id}-2"])
        ->and($order->refunds()->count())->toBe(2)
        ->and($order->fresh()->refundableAmount())->toBe(3000);
});

it('marks the order refunded when partial refunds reach the total', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 1999]);
    $service = app(RefundService::class);

    $service->refundPartially($order, $finance, 999, 'Primo');
    $service->refundPartially($order->fresh(), $finance, 1000, 'Resto');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()->refundableAmount())->toBe(0);
});

it('does not refund more than the remaining amount', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 3000]);
    $service = app(RefundService::class);

    $service->refundPartially($order, $finance, 2000, 'Primo');

    expect(fn () => $service->refundPartially($order->fresh(), $finance, 1001, 'Troppo'))
        ->toThrow(DomainException::class);

    expect($this->payments->refundsFor($order->gateway_charge_id))->toHaveCount(1);
});

it('rejects non positive amounts', function (int $amount) {
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    expect(fn () => app(RefundService::class)->refundPartially($order, User::factory()->finance()->create(), $amount, 'x'))
        ->toThrow(DomainException::class);

    $this->payments->assertNothingRefunded();
})->with([0, -100]);

it('does not let a customer refund part of an order', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->paid()->for($customer)->create();

    expect(fn () => app(RefundService::class)->refundPartially($order, $customer, 100, 'x'))
        ->toThrow(AuthorizationException::class);

    $this->payments->assertNothingRefunded();
});

it('does not partially refund an order that is not paid', function () {
    $order = Order::factory()->create(['amount' => 3000]);

    expect(fn () => app(RefundService::class)->refundPartially($order, User::factory()->finance()->create(), 100, 'x'))
        ->toThrow(DomainException::class);

    $this->payments->assertNothingRefunded();
});

it('refunds only the remainder when a full refund follows partial ones', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 5000]);
    $service = app(RefundService::class);

    $service->refundPartially($order, $finance, 1500, 'Primo');
    $refund = $service->refundInFull($order->fresh(), $finance);

    expect($refund->amount)->toBe('35.00')
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($this->payments->refundsByKey()["refund-{$order->id}"]->amount)->toBe(3500);
});

it('records the reason in the audit log and notifies the customer', function () {
    Notification::fake();
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 5000]);

    $refund = app(RefundService::class)->refundPartially($order, $finance, 700, 'Spedizione in ritardo');

    $entry = AuditEntry::where('action', 'order.partially_refunded')->sole();
    expect($entry->actor_id)->toBe($finance->id)
        ->and($entry->subject_id)->toBe($order->id)
        ->and($entry->payload)->toBe(['refund_id' => $refund->id, 'amount' => 700, 'reason' => 'Spedizione in ritardo']);

    Notification::assertSentTo($order->user, RefundIssued::class, fn (RefundIssued $n) => $n->refund->is($refund));
});
