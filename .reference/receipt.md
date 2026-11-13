<!-- php .harness/bin/receipt · [D] lo dimostra lo script (deterministico, ripetibile) · [A] lo dichiara l'agente in progress.md, senza verifica -->

# Change Receipt — T-001 · ✅ VERDE

Run `2026-11-13T0941-T-001` · agente `codex` · 2026-11-13 09:46 · trace: `.harness/runs/2026-11-13T0941-T-001/trace.jsonl`

## Esito [D]

Tutti i controlli sono verdi.

## Task [D]

T-001 "Rimborso parziale di un ordine" · status `approved` · `.harness/tasks/T-001.md` · commit base `8ad6028`

## Diff [D]

| File | +/− | Nello scope | must_not_change |
|---|---|---|---|
| .harness/memory/progress.md | +7 −3 | sì | — |
| app/Actions/Refunds/RefundPartially.php | +84 −0 | sì | — |
| app/Enums/OrderStatus.php | +1 −0 | sì | — |
| app/Http/Controllers/Admin/OrderRefundController.php | +18 −0 | sì | — |
| app/Http/Requests/StorePartialRefundRequest.php | +24 −0 | sì | — |
| app/Models/Refund.php | +8 −1 | sì | — |
| app/Policies/OrderPolicy.php | +1 −1 | sì | — |
| database/migrations/2026_11_13_000000_add_partial_refunds_support.php | +27 −0 | sì | — |
| routes/web.php | +3 −0 | sì | — |
| tests/Feature/Refunds/PartialRefundTest.php | +134 −0 | sì | — |

## Stile e tipi [D]

- Pint: ok
- PHPStan: ok

## Test mirati [D]

Selezione: convenzione di path (app/**/X.php → tests/**/XTest.php) · necromancer: assente

`tests/Feature/Admin/OrderRefundControllerTest.php`, `tests/Feature/Refunds/PartialRefundTest.php`

✅ 9 test, 0 rossi

## Evidenze [D]

| Comando | Esito | Test | Durata |
|---|---|---|---|
| `vendor/bin/pest --group=REFUND-INV-1` | ✅ | 1 | 0,3 s |
| `vendor/bin/pest --group=REFUND-INV-2` | ✅ | 1 | 0,3 s |
| `vendor/bin/pest --group=REFUND-INV-3` | ✅ | 1 | 0,3 s |
| `vendor/bin/pest --group=REFUND-INV-4` | ✅ | 1 | 0,2 s |
| `vendor/bin/pest --group=REFUND-INV-5` | ✅ | 3 | 0,4 s |
| `vendor/bin/pest --group=REFUND-INV-6` | ✅ | 1 | 0,3 s |
| `vendor/bin/pest` | ✅ | 30 | 0,7 s |

## Invarianti → test [D]

| Invariante | Test | Esito |
|---|---|---|
| REFUND-INV-1 | 1 | ✅ |
| REFUND-INV-2 | 1 | ✅ |
| REFUND-INV-3 | 1 | ✅ |
| REFUND-INV-4 | 1 | ✅ |
| REFUND-INV-5 | 3 | ✅ |
| REFUND-INV-6 | 1 | ✅ |

Un invariante con 0 test è ❌ "non coperta": l'agente non lo può dimostrare.

## Progress [D]

`.harness/memory/progress.md`: task `T-001`, aggiornato in questa sessione.

## Conflicts [D] + [A]

- [D] Il diff non tocca file elencati in AGENTS.md → Conflicts.
- [A] "Il rimborso totale resta nel `RefundService` legacy (C1/C2/C4), intenzionalmente non modificato."

## Rischi aperti [A]

- "Nessuno noto: eseguite tutte le evidence richieste su SQLite in memoria."

## Metriche [D]

Richieste al modello, token e costo: _da estrarre_ dal transcript (`metrics.json`, `workshop/bin/run`). Verifica: 4,4 s.
