<?php

use App\Enums\OrderStatus;
use App\Models\AuditEntry;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundIssued;
use Illuminate\Support\Facades\Notification;

function partialRefundRequest(User $actor, Order $order, int $amount, string $key, string $reason = 'Customer request')
{
    return test()->actingAs($actor)
        ->from("/admin/orders/{$order->id}")
        ->withHeader('Idempotency-Key', $key)
        ->post("/admin/orders/{$order->id}/refunds", [
            'amount' => $amount,
            'reason' => $reason,
        ]);
}

it('allows finance and admin, but not support or customers, to issue partial refunds', function () {
    $payments = $this->fakePayments();
    $order = Order::factory()->paid()->create(['amount' => 3200]);

    partialRefundRequest(User::factory()->support()->create(), $order, 500, 'support-key')
        ->assertForbidden();
    partialRefundRequest(User::factory()->create(), $order, 500, 'customer-key')
        ->assertForbidden();

    partialRefundRequest(User::factory()->finance()->create(), $order, 500, 'finance-key')
        ->assertRedirect("/admin/orders/{$order->id}");
    partialRefundRequest(User::factory()->admin()->create(), $order->fresh(), 500, 'admin-key')
        ->assertRedirect("/admin/orders/{$order->id}");

    expect($order->refunds()->count())->toBe(2);
    $payments->assertRefunded($order->gateway_charge_id, 500);
})->group('REFUND-INV-1');

it('rejects invalid amounts, amounts over the refundable balance, and non-refundable orders', function () {
    $payments = $this->fakePayments();
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 1000]);

    $this->actingAs($finance)
        ->withHeader('Idempotency-Key', 'invalid-amount')
        ->postJson("/admin/orders/{$order->id}/refunds", ['amount' => 0, 'reason' => 'No'])
        ->assertUnprocessable();
    partialRefundRequest($finance, $order, 700, 'first-refund')->assertRedirect();
    $this->actingAs($finance)
        ->withHeader('Idempotency-Key', 'over-balance')
        ->postJson("/admin/orders/{$order->id}/refunds", ['amount' => 301, 'reason' => 'Too much'])
        ->assertUnprocessable();

    partialRefundRequest($finance, Order::factory()->cancelled()->create(), 100, 'cancelled-order')
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::PartiallyRefunded)
        ->and($order->refunds()->count())->toBe(1);
    $payments->assertRefunded($order->gateway_charge_id, 700);
})->group('REFUND-INV-2');

it('records the refund, order state, and audit entry together', function () {
    $this->fakePayments();
    $actor = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 1000]);

    partialRefundRequest($actor, $order, 1000, 'audit-key', 'Damaged parcel')->assertRedirect();

    $refund = Refund::sole();
    $audit = AuditEntry::sole();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($refund->amount)->toBe('10.00')
        ->and($refund->reason)->toBe('Damaged parcel')
        ->and($audit->actor_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($refund->id)
        ->and($audit->payload)->toMatchArray([
            'reason' => 'Damaged parcel',
            'idempotency_key' => 'audit-key',
            'gateway_refund_id' => $refund->gateway_refund_id,
        ]);
})->group('REFUND-INV-3', 'REFUND-INV-6');

it('notifies the customer only after a successful partial refund', function () {
    Notification::fake();
    $this->fakePayments();
    $order = Order::factory()->paid()->create();

    partialRefundRequest(User::factory()->finance()->create(), $order, 100, 'notification-key')->assertRedirect();

    Notification::assertSentTo($order->user, RefundIssued::class);
})->group('REFUND-INV-4');

it('is idempotent per order and passes the client key to the gateway', function () {
    $payments = $this->fakePayments();
    $actor = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 1000]);

    partialRefundRequest($actor, $order, 200, 'same-key')->assertRedirect();
    partialRefundRequest($actor, $order->fresh(), 200, 'same-key')->assertRedirect();
    partialRefundRequest($actor, $order->fresh(), 300, 'different-key')->assertRedirect();

    expect($order->refunds()->count())->toBe(2)
        ->and(AuditEntry::count())->toBe(2)
        ->and($payments->refundsFor($order->gateway_charge_id))->toHaveCount(2)
        ->and($payments->refundsByKey())->toHaveKeys(['same-key', 'different-key']);
})->group('REFUND-INV-5');

it('requires an idempotency key before making a refund', function () {
    $payments = $this->fakePayments();
    $order = Order::factory()->paid()->create();

    $this->actingAs(User::factory()->finance()->create())
        ->postJson("/admin/orders/{$order->id}/refunds", ['amount' => 100, 'reason' => 'Missing key'])
        ->assertBadRequest();

    expect(Refund::count())->toBe(0);
    $payments->assertNothingRefunded();
})->group('REFUND-INV-5');

it('does not create another refund when a key is reused with a different amount', function () {
    $this->fakePayments();
    $actor = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 1000]);

    partialRefundRequest($actor, $order, 200, 'reused-key')->assertRedirect();

    partialRefundRequest($actor, $order->fresh(), 300, 'reused-key')
        ->assertServerError();

    expect(Refund::count())->toBe(1)
        ->and(AuditEntry::count())->toBe(1);
})->group('REFUND-INV-5');
