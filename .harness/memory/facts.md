# Facts

Fatti verificabili sul progetto. Ogni fatto dice come riverificarlo con un comando di sola lettura. Un fatto che serve in ogni sessione si promuove in AGENTS.md e si cancella da qui.

| Fatto | Come verificarlo | Verificato |
|---|---|---|
| _Esempio:_ i test usano un database SQLite in memoria. | `grep -n DB_DATABASE phpunit.xml` | 2026-11-01 |
| `Admin\OrderRefundController` passa a `RefundService::refundInFull()`, che autorizza con `isStaff()`. `OrderPolicy::refund` la usa solo la vista (`@can`). | `git grep -n isStaff -- app/Services` | 2026-11-01 |
| `refunds.amount` è in **euro** (`decimal:2`), `orders.amount` in **centesimi** (intero). | `php artisan model:show Refund` | 2026-11-01 |
| Il fake del gateway (`fakePayments()`) è attivo solo in `tests/Feature/Payments`: fuori da lì i test chiamano il gateway HTTP. | `git grep -n -B1 fakePayments -- tests/Pest.php` | 2026-11-01 |
| L'idempotenza esiste solo su `POST api/orders`: middleware `idempotent`, header `Idempotency-Key`. Il rimborso admin non ce l'ha. | `php artisan route:list -v --path=orders` | 2026-11-01 |
| `refunds.order_id` ha un indice unique (`refunds_order_id_unique`): un ordine ha al massimo un rimborso. | `php artisan db:table refunds` | 2026-11-01 |

## Da Facts ad AGENTS.md (E2)

In E2 si promuovono in AGENTS.md le righe che servono in ogni sessione che tocca i rimborsi:

- authz → **Conflicts C1 e C2**: i rimborsi totali passano ancora dal Service legacy, e la fonte dell'autorizzazione dichiarata è la Policy, non `isStaff()`;
- unità di `refunds.amount` → **Conflicts C3**;
- idempotenza → **Conflicts C4**;
- perimetro del fake → **Pitfalls**: `fakePayments()` in ogni test nuovo che tocca i pagamenti;
- indice unique su `refunds.order_id` → lo decidi tu in E2.

La riga d'esempio resta qui: non serve in ogni sessione.
