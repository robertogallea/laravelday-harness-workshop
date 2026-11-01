<?php

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Models\AuditEntry;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('lets the owner cancel a pending order', function () {
    Event::fake([OrderCancelled::class]);
    $order = Order::factory()->create();

    $this->actingAs($order->user)
        ->post("/orders/{$order->id}/cancel", ['reason' => 'Ordinato per errore'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    $entry = AuditEntry::sole();
    expect($entry->action)->toBe('order.cancelled')
        ->and($entry->actor_id)->toBe($order->user_id)
        ->and($entry->subject->is($order))->toBeTrue()
        ->and($entry->payload)->toBe(['reason' => 'Ordinato per errore']);

    Event::assertDispatched(OrderCancelled::class, fn ($event) => $event->order->is($order));
});

it('does not let other users cancel the order', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post("/orders/{$order->id}/cancel", ['reason' => 'Test'])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('does not cancel a paid order', function () {
    $order = Order::factory()->paid()->create();

    $this->actingAs($order->user)
        ->post("/orders/{$order->id}/cancel", ['reason' => 'Test'])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('rolls back and does not announce the cancellation when the audit fails', function () {
    Event::fake([OrderCancelled::class]);
    $order = Order::factory()->create();

    DB::unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON audit_entries BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END;");

    expect(fn () => app(CancelOrder::class)->handle($order, $order->user, 'Test'))
        ->toThrow(QueryException::class, 'audit unavailable');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    Event::assertNotDispatched(OrderCancelled::class);
});

it('announces the cancellation only after the transaction commits', function () {
    $order = Order::factory()->create();
    $levelBefore = DB::transactionLevel();
    $levelsAtDispatch = [];

    Event::listen(OrderCancelled::class, function () use (&$levelsAtDispatch) {
        $levelsAtDispatch[] = DB::transactionLevel();
    });

    app(CancelOrder::class)->handle($order, $order->user, 'Test');

    expect($levelsAtDispatch)->toBe([$levelBefore]);
});
