<?php

// REFUND-INV-4: la notifica al cliente parte solo dopo il commit.

use App\Models\User;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

describe('REFUND-INV-4', function () {
    it('notifies the customer outside of any open transaction', function () {
        $order = eval_order(5000);
        $base = DB::transactionLevel(); // la transazione di RefreshDatabase
        $levels = [];
        Event::listen(NotificationSending::class, function (NotificationSending $e) use (&$levels, $order) {
            if ($e->notifiable->is($order->user)) {
                $levels[] = DB::transactionLevel();
            }
        });

        eval_refund(User::factory()->finance()->create(), $order, 1000);

        expect($levels)->not->toBeEmpty('Il cliente non ha ricevuto nessuna notifica.')
            ->and(max($levels))->toBe($base, 'La notifica è partita con una transazione ancora aperta.');
    });

    it('sends nothing when writing the refund fails', function () {
        Notification::fake();
        $order = eval_order(5000);
        eval_fail_on_refund_insert();

        eval_refund(User::factory()->finance()->create(), $order, 1000);

        Notification::assertNothingSent();
    });

    it('sends nothing when updating the order fails', function () {
        Notification::fake();
        $order = eval_order(5000);
        eval_fail_on('orders', 'UPDATE');

        eval_refund(User::factory()->finance()->create(), $order, 5000); // intero importo: lo stato cambia di sicuro

        Notification::assertNothingSent();
    });
})->group('REFUND-INV-4');
