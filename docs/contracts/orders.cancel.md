# orders.cancel — Annullamento di un ordine

Il cliente annulla un proprio ordine non ancora pagato.
Classe: `App\Actions\Orders\CancelOrder` (docblock: `@contract docs/contracts/orders.cancel.md`) · Rotta: `POST /orders/{order}/cancel` (`OrderCancellationController`)

## Input

- `Order $order` — dalla rotta.
- `User $actor` — l'utente autenticato.
- `string $reason` — obbligatoria, al massimo 500 caratteri (la valida il controller).

## Output

L'ordine con `status = cancelled`. Il controller torna alla pagina precedente con il messaggio "Ordine annullato.".

## Invarianti

| ID | Regola | Group Pest |
|---|---|---|
| CANCEL-INV-1 | Solo il proprietario dell'ordine, e solo finché l'ordine è `pending`, può annullarlo: `OrderPolicy::cancel`, controllata dal controller con `Gate::authorize`. Lo staff non annulla gli ordini degli altri. | `CANCEL-INV-1` |
| CANCEL-INV-2 | Cambio di stato e riga di audit (`order.cancelled`, con il motivo) si scrivono insieme o per niente (`DB::transaction`). | `CANCEL-INV-2` |
| CANCEL-INV-3 | `OrderCancelled` parte solo dopo il commit (`ShouldDispatchAfterCommit`): se la transazione fallisce, nessun listener lo riceve. | `CANCEL-INV-3` |

## Side effects

In quest'ordine:

1. dentro `DB::transaction()`: `orders.status` → `cancelled`; una riga in `audit_entries` con `RecordAuditEntry` (`action = order.cancelled`, attore, ordine, `payload.reason`);
2. dopo il commit: evento `OrderCancelled`.

Nessuna chiamata al gateway: un ordine `pending` non è stato addebitato.

## Errori

- `AuthorizationException` (403) — l'attore non è il proprietario, oppure l'ordine non è `pending` (CANCEL-INV-1).
- `ValidationException` — `reason` mancante o più lunga di 500 caratteri.
- `QueryException` — una scrittura fallisce: rollback di tutto, nessun evento (CANCEL-INV-2, CANCEL-INV-3).

## Non-goals

- Annullare un ordine pagato: quello è un rimborso.
- Avvisare il cliente o liberare il magazzino: lo fanno i listener di `OrderCancelled`.
- Riaprire un ordine annullato.
