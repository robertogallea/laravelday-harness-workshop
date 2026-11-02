# refunds.full — Rimborso totale (legacy)

Lo staff rimborsa per intero un ordine pagato. Il contract descrive il codice **com'è**, difetti compresi: è legacy in produzione (AGENTS.md → Conflicts C1–C4), non un modello da copiare.
Classe: `App\Services\RefundService::refundInFull()` · Rotta: `POST /admin/orders/{order}/refund` (`Admin\OrderRefundController`)

## Input

- `Order $order` — dalla rotta.
- `User $actor` — l'utente autenticato.

Nessun importo (si rimborsa tutto `orders.amount`), nessun motivo, nessuna chiave di idempotenza dal client.

## Output

Il `Refund` creato, con `amount` in euro (C3), e l'ordine con `status = refunded`. Il controller torna alla pagina precedente con il messaggio "Ordine rimborsato.".

## Invarianti

| ID | Regola | Group Pest |
|---|---|---|
| REFUND-FULL-INV-1 | Lo staff rimborsa per intero, `support` compreso (`User::isStaff()`): è l'eccezione nota C2, e c'è un test che la tiene ferma. Un cliente riceve `AuthorizationException` e non parte niente. | `REFUND-FULL-INV-1` |
| REFUND-FULL-INV-2 | Si rimborsa solo un ordine `paid`: un ordine già `refunded` non si rimborsa una seconda volta, né nel DB né al gateway. | `REFUND-FULL-INV-2` |

## Side effects

In quest'ordine, **senza transazione**:

1. `PaymentGateway::refund()` per l'intero `orders.amount`, con la chiave derivata `refund-{order.id}` (C4);
2. una riga in `refunds`, con `amount = orders.amount / 100` (euro, C3);
3. `orders.status` → `refunded`;
4. evento `OrderRefunded` → notifica `RefundIssued` al cliente (listener sincrono);
5. `Log::info('order refunded')`: nessuna riga in `audit_entries`.

## Errori

- `AuthorizationException` (403) — l'attore non è staff (REFUND-FULL-INV-1).
- `DomainException` — l'ordine non è `paid` (REFUND-FULL-INV-2). Il controller non la gestisce: risposta 500.
- `PaymentGatewayException` — il gateway rifiuta il rimborso: nessuna scrittura.

## Non-goals

- Rimborsi parziali o ripetuti: la chiave `refund-{id}` e l'indice unique su `refunds.order_id` ne ammettono uno per ordine. Vedi `refunds.partial.md`.
- Atomicità, notifica dopo il commit e audit: mancano, ed è noto. Si aggiungono solo con un Task Contract che li mette in scope; un bugfix qui è una modifica minima sul posto.
