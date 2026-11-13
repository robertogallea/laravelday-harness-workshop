<?php

use App\Actions\Refunds\RefundOrderPartially;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\OrderRefunded;
use App\Models\AuditEntry;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundIssued;
use App\Payments\Exceptions\IdempotencyKeyReused;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->payments = $this->fakePayments();
});

function refundPartially(Order $order, User $actor, int $amount, string $key, string $reason = 'Articolo danneggiato')
{
    return test()->actingAs($actor)
        ->withHeader('Idempotency-Key', $key)
        ->postJson("/admin/orders/{$order->id}/refunds", ['amount' => $amount, 'reason' => $reason]);
}

// REFUND-INV-1

it('lets finance and admin refund part of an order', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $order = Order::factory()->paid()->create(['amount' => 5000]);

    refundPartially($order, $actor, 1250, 'key-1')
        ->assertRedirect("/admin/orders/{$order->id}")
        ->assertSessionHas('status', 'Rimborso registrato.');

    $refund = Refund::sole();
    expect($refund->amount)->toBe('12.50')
        ->and($refund->reason)->toBe('Articolo danneggiato')
        ->and($refund->refunded_by)->toBe($actor->id)
        ->and($order->fresh()->status)->toBe(OrderStatus::PartiallyRefunded);

    $this->payments->assertRefunded($order->gateway_charge_id, 1250);
})->with(['finance', 'admin'])->group('REFUND-INV-1', 'ORDER-INV-1');

it('forbids support and customers from refunding part of an order', function (Role $role) {
    $actor = User::factory()->create(['role' => $role]);
    $order = Order::factory()->paid()->for($role === Role::Customer ? $actor : User::factory())->create(['amount' => 5000]);

    refundPartially($order, $actor, 1000, 'key-1')->assertForbidden();

    // Even with an invalid body, authorization comes first.
    refundPartially($order, $actor, -5, 'key-2')->assertForbidden();

    expect(Refund::count())->toBe(0)
        ->and(AuditEntry::count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
    $this->payments->assertNothingRefunded();
})->with([Role::Support, Role::Customer])->group('REFUND-INV-1');

// REFUND-INV-2

it('refunds in several steps up to the order amount', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    refundPartially($order, $finance, 1000, 'key-1')->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::PartiallyRefunded);

    refundPartially($order, $finance, 1990, 'key-2')->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::PartiallyRefunded);

    refundPartially($order, $finance, 10, 'key-3')->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()->refundedCents())->toBe(3000)
        ->and($order->refunds()->pluck('amount')->all())->toBe(['10.00', '19.90', '0.10']);
})->group('REFUND-INV-2', 'ORDER-INV-1');

it('rejects an amount beyond the refundable balance without calling the gateway', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    refundPartially($order, $finance, 2000, 'key-1')->assertRedirect();
    $this->payments = $this->fakePayments(); // fresh fake: count calls from here

    refundPartially($order, $finance, 1001, 'key-2')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    expect(Refund::count())->toBe(1)
        ->and(AuditEntry::count())->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::PartiallyRefunded);
    $this->payments->assertNothingRefunded();
})->group('REFUND-INV-2');

it('rejects invalid amounts and a missing reason', function (array $body, string $field) {
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    $this->actingAs(User::factory()->finance()->create())
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/admin/orders/{$order->id}/refunds", $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Refund::count())->toBe(0);
    $this->payments->assertNothingRefunded();
})->with([
    'zero' => [['amount' => 0, 'reason' => 'x'], 'amount'],
    'negative' => [['amount' => -100, 'reason' => 'x'], 'amount'],
    'not an integer' => [['amount' => 12.5, 'reason' => 'x'], 'amount'],
    'missing amount' => [['reason' => 'x'], 'amount'],
    'missing reason' => [['amount' => 100], 'reason'],
])->group('REFUND-INV-2');

it('does not refund orders that are not paid or partially refunded', function (OrderStatus $status) {
    $order = Order::factory()->paid()->create(['amount' => 3000, 'status' => $status]);

    refundPartially($order, User::factory()->admin()->create(), 100, 'key-1')->assertForbidden();

    expect(Refund::count())->toBe(0);
    $this->payments->assertNothingRefunded();
})->with([OrderStatus::Refunded, OrderStatus::Cancelled, OrderStatus::Pending])->group('REFUND-INV-2');

// REFUND-INV-3 + REFUND-INV-4

it('writes nothing and notifies nobody when a write fails', function () {
    Notification::fake();
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    DB::unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON audit_entries BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END;");

    expect(fn () => app(RefundOrderPartially::class)->handle($order, $finance, 1000, 'Test', 'key-1'))
        ->toThrow(QueryException::class, 'audit unavailable');

    expect(Refund::count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
    Notification::assertNothingSent();

    // The client retries with the same key: one refund at the gateway, writes completed.
    DB::unprepared('DROP TRIGGER fail_audit');

    app(RefundOrderPartially::class)->handle($order->fresh(), $finance, 1000, 'Test', 'key-1');

    expect(Refund::count())->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::PartiallyRefunded)
        ->and($this->payments->refundsFor($order->gateway_charge_id))->toHaveCount(1);
    Notification::assertSentTo($order->user, RefundIssued::class);
})->group('REFUND-INV-3', 'REFUND-INV-4', 'REFUND-INV-5');

it('notifies the customer only after the transaction commits', function () {
    Notification::fake();
    $order = Order::factory()->paid()->create(['amount' => 3000]);
    $levelBefore = DB::transactionLevel();
    $levelsAtDispatch = [];

    Event::listen(OrderRefunded::class, function () use (&$levelsAtDispatch) {
        $levelsAtDispatch[] = DB::transactionLevel();
    });

    app(RefundOrderPartially::class)->handle($order, User::factory()->finance()->create(), 500, 'Test', 'key-1');

    expect($levelsAtDispatch)->toBe([$levelBefore]);
    Notification::assertSentTo($order->user, RefundIssued::class);
})->group('REFUND-INV-4');

// REFUND-INV-5

it('refunds once when the same Idempotency-Key is repeated', function () {
    Notification::fake();
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    refundPartially($order, $finance, 1000, 'same-key')->assertRedirect();
    refundPartially($order, $finance, 1000, 'same-key')->assertRedirect();

    expect(Refund::count())->toBe(1)
        ->and(AuditEntry::count())->toBe(1)
        ->and($order->fresh()->refundedCents())->toBe(1000)
        ->and($this->payments->refundsFor($order->gateway_charge_id))->toHaveCount(1);
    Notification::assertSentToTimes($order->user, RefundIssued::class, 1);
})->group('REFUND-INV-5');

it('treats different keys as distinct refunds, even with the same amount', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    refundPartially($order, $finance, 1000, 'key-a')->assertRedirect();
    refundPartially($order, $finance, 1000, 'key-b')->assertRedirect();

    expect(Refund::count())->toBe(2)
        ->and($this->payments->refundsFor($order->gateway_charge_id))->toHaveCount(2);
})->group('REFUND-INV-5');

it('passes the client key to the gateway as is', function () {
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    refundPartially($order, User::factory()->finance()->create(), 700, 'client-key-42')->assertRedirect();

    $refunds = $this->payments->refundsByKey();
    expect(array_keys($refunds))->toBe(['client-key-42'])
        ->and($refunds['client-key-42']->amount)->toBe(700);
})->group('REFUND-INV-5');

it('rejects the same key with a different amount', function () {
    $finance = User::factory()->finance()->create();
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    refundPartially($order, $finance, 1000, 'same-key')->assertRedirect();
    refundPartially($order, $finance, 900, 'same-key')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('refund');

    expect(fn () => app(RefundOrderPartially::class)->handle($order->fresh(), $finance, 900, 'Test', 'same-key'))
        ->toThrow(IdempotencyKeyReused::class);

    expect(Refund::count())->toBe(1)
        ->and($this->payments->refundsFor($order->gateway_charge_id))->toHaveCount(1);
})->group('REFUND-INV-5');

it('requires the Idempotency-Key header', function () {
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    $this->actingAs(User::factory()->finance()->create())
        ->postJson("/admin/orders/{$order->id}/refunds", ['amount' => 100, 'reason' => 'x'])
        ->assertStatus(400);

    expect(Refund::count())->toBe(0);
    $this->payments->assertNothingRefunded();
})->group('REFUND-INV-5');

// REFUND-INV-6

it('records actor, reason, idempotency key and gateway refund id in the audit', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->paid()->create(['amount' => 3000]);

    refundPartially($order, $admin, 1500, 'key-audit', 'Spedizione in ritardo')->assertRedirect();

    $refund = Refund::sole();
    $entry = AuditEntry::sole();
    expect($entry->action)->toBe('order.partially_refunded')
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->subject->is($order))->toBeTrue()
        ->and($entry->payload)->toMatchArray([
            'amount' => 1500,
            'reason' => 'Spedizione in ritardo',
            'idempotency_key' => 'key-audit',
            'gateway_refund_id' => $refund->gateway_refund_id,
        ]);
})->group('REFUND-INV-6');

// The legacy full refund keeps working next to the partial one.

it('keeps the legacy full refund working', function () {
    $order = Order::factory()->paid()->create(['amount' => 3200]);

    $this->actingAs(User::factory()->support()->create())
        ->post("/admin/orders/{$order->id}/refund")
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
    $this->payments->assertRefunded($order->gateway_charge_id, 3200);
});
