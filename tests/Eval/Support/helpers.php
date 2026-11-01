<?php

/*
 * Aiuti della suite di valutazione. Le asserzioni guardano gli effetti (righe nel DB,
 * rimborsi nel gateway, notifiche, audit), mai la forma del codice o il corpo della risposta,
 * così valgono per qualunque implementazione ragionevole del prompt della run.
 */

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * POST /admin/orders/{order}/refunds come lo farebbe il back office. Ogni richiesta porta
 * un header Idempotency-Key nuovo (oppure quello passato): un'implementazione che lo richiede
 * non dà falsi rossi, una che lo ignora non ne trae vantaggio.
 */
function eval_refund(User $actor, Order $order, int $amount, ?string $key = null, string $reason = 'Articolo reso dal cliente'): TestResponse
{
    return test()->actingAs($actor)
        ->from("/admin/orders/{$order->id}")
        ->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())
        ->post("/admin/orders/{$order->id}/refunds", ['amount' => $amount, 'reason' => $reason]);
}

/** Le tabelle dei rimborsi: `refunds` e quelle che l'agente può aver aggiunto (es. `partial_refunds`). */
function eval_refund_tables(): array
{
    return array_values(array_filter(
        array_map(fn ($t) => $t['name'], Schema::getTables()),
        fn (string $name) => str_contains($name, 'refund'),
    ));
}

function eval_refund_rows(Order $order): int
{
    return array_sum(array_map(
        fn (string $t) => Schema::hasColumn($t, 'order_id') ? DB::table($t)->where('order_id', $order->id)->count() : 0,
        eval_refund_tables(),
    ));
}

/** Lo stato dell'ordine così com'è nel DB (stringa), senza passare dal cast dell'enum. */
function eval_status(Order $order): string
{
    return (string) DB::table('orders')->where('id', $order->id)->value('status');
}

function eval_audit_rows(): int
{
    return DB::table('audit_entries')->count();
}

/** Guasto iniettato: ogni scrittura del tipo indicato sulla tabella fallisce (RAISE(ABORT)). */
function eval_fail_on(string $table, string $event): void
{
    $name = 'eval_fail_'.$table.'_'.strtolower($event);
    DB::statement("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT, 'eval: guasto iniettato su {$table}'); END");
}

function eval_fail_on_refund_insert(): void
{
    foreach (eval_refund_tables() as $t) {
        eval_fail_on($t, 'INSERT');
    }
}

function eval_order(int $amount = 5000, string $state = 'paid'): Order
{
    $factory = $state === 'pending' ? Order::factory() : Order::factory()->{$state}();

    return $factory->create(['amount' => $amount]);
}
