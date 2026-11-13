<!-- php .harness/bin/receipt · [D] lo dimostra lo script (deterministico, ripetibile) · [A] lo dichiara l'agente in progress.md, senza verifica -->

# Change Receipt — T-001 · ✅ VERDE

Run `2026-11-13T0936-T-001` · agente `claude` · 2026-11-13 09:40 · trace: `.harness/runs/2026-11-13T0936-T-001/trace.jsonl`

## Esito [D]

Tutti i controlli sono verdi.

## Task [D]

T-001 "Rimborso parziale di un ordine" · status `approved` · `.harness/tasks/T-001.md` · commit base `8ad6028`

## Diff [D]

| File | +/− | Nello scope | must_not_change |
|---|---|---|---|
| .harness/memory/progress.md | +12 −3 | sì | — |
| app/Actions/Refunds/RefundOrderPartially.php | +90 −0 | sì | — |
| app/Enums/OrderStatus.php | +1 −0 | sì | — |
| app/Http/Controllers/Admin/OrderPartialRefundController.php | +32 −0 | sì | — |
| app/Http/Requests/StorePartialRefundRequest.php | +35 −0 | sì | — |
| app/Models/Order.php | +16 −0 | sì | — |
| app/Models/Refund.php | +20 −1 | sì | — |
| app/Policies/OrderPolicy.php | +1 −1 | sì | — |
| database/migrations/2026_11_13_100000_add_partial_refunds_to_refunds_table.php | +37 −0 | sì | — |
| routes/web.php | +3 −0 | sì | — |
| tests/Feature/Refunds/PartialRefundTest.php | +266 −0 | sì | — |

## Stile e tipi [D]

- Pint: ok
- PHPStan: ok

## Test mirati [D]

Selezione: convenzione di path (app/**/X.php → tests/**/XTest.php) · necromancer: assente

`tests/Feature/Refunds/PartialRefundTest.php`

✅ 23 test, 0 rossi

## Evidenze [D]

| Comando | Esito | Test | Durata |
|---|---|---|---|
| `vendor/bin/pest --group=REFUND-INV-1` | ✅ | 4 | 0,3 s |
| `vendor/bin/pest --group=REFUND-INV-2` | ✅ | 10 | 0,4 s |
| `vendor/bin/pest --group=REFUND-INV-3` | ✅ | 1 | 0,2 s |
| `vendor/bin/pest --group=REFUND-INV-4` | ✅ | 2 | 0,2 s |
| `vendor/bin/pest --group=REFUND-INV-5` | ✅ | 6 | 0,4 s |
| `vendor/bin/pest --group=REFUND-INV-6` | ✅ | 1 | 0,3 s |
| `vendor/bin/pest` | ✅ | 46 | 0,8 s |

## Invarianti → test [D]

| Invariante | Test | Esito |
|---|---|---|
| REFUND-INV-1 | 4 | ✅ |
| REFUND-INV-2 | 10 | ✅ |
| REFUND-INV-3 | 1 | ✅ |
| REFUND-INV-4 | 2 | ✅ |
| REFUND-INV-5 | 6 | ✅ |
| REFUND-INV-6 | 1 | ✅ |

Un invariante con 0 test è ❌ "non coperta": l'agente non lo può dimostrare.

## Progress [D]

`.harness/memory/progress.md`: task `T-001`, aggiornato in questa sessione.

## Conflicts [D] + [A]

- [D] Il diff non tocca file elencati in AGENTS.md → Conflicts.
- [A] "C2 ha un effetto nuovo: la vista `admin/orders/show` mostra il bottone del rimborso totale con `@can('refund')`; ora la Policy passa anche per `partially_refunded`, ma `RefundService` rifiuta (DomainException → 500). Vista e Service fuori scope."
- [A] "Il middleware `idempotent` richiede un header: un form HTML del back office non può inviarlo. Nessun form per il parziale nella vista (fuori scope)."

## Rischi aperti [A]

- "Concorrenza: il saldo rimborsabile si controlla prima del gateway, senza lock; due richieste parallele con chiavi diverse possono superare `orders.amount`. Non testato."
- "Replay con la stessa chiave dopo che quel rimborso ha portato l'ordine a `refunded`: la Policy risponde 403 invece di restituire il rimborso esistente (nessun doppio rimborso, ma non è il "restituisce quello esistente" del contract)."
- "`PaymentGatewayException` (anche `IdempotencyKeyReused`) diventa 422 con errore `refund`: scelta mia, il contract dice solo "torna al client"."
- "`down()` della migrazione fallisce se esistono più rimborsi per ordine."

## Metriche [D]

Richieste al modello, token e costo: _da estrarre_ dal transcript (`metrics.json`, `workshop/bin/run`). Verifica: 4,6 s.
