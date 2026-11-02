# refunds.partial — Rimborso parziale di un ordine

Lo staff autorizzato rimborsa una parte dell'importo di un ordine pagato, anche in più volte, fino al totale.
Classe: da creare in `app/Actions/Refunds/` (ADR [0001](../adr/0001-actions-over-services.md)), con `@contract docs/contracts/refunds.partial.md` nel docblock · Rotta: `POST /admin/orders/{order}/refunds`

## Input

- `Order $order` — dalla rotta.
- `User $actor` — l'utente autenticato.
- `int $amount` — **centesimi**, dal campo `amount`.
- `string $reason` — obbligatoria, dal campo `reason`.
- `string $idempotencyKey` — dall'header `Idempotency-Key` (middleware `idempotent`).

## Output

Il rimborso registrato. L'ordine passa a `partially_refunded` se resta un saldo, a `refunded` quando la somma dei rimborsi arriva a `orders.amount` (ORDER-INV-1, `orders.status.md`). Il controller torna alla pagina dell'ordine con un messaggio.

## Invarianti

| ID | Regola | Group Pest |
|---|---|---|
| REFUND-INV-1 | Solo chi passa `OrderPolicy::refund` rimborsa in parte: `finance` e `admin`. `support` e i clienti ricevono 403, e non parte niente. | `REFUND-INV-1` |
| REFUND-INV-3 | Riga del rimborso, stato dell'ordine e riga di audit si scrivono tutti o nessuno (`DB::transaction`). | `REFUND-INV-3` |
| REFUND-INV-4 | La notifica al cliente (`RefundIssued`) parte solo dopo il commit: se una scrittura fallisce, non parte. | `REFUND-INV-4` |
| REFUND-INV-6 | Ogni rimborso registra in `audit_entries` (`RecordAuditEntry`) attore, motivo, chiave di idempotenza e `gateway_refund_id`. | `REFUND-INV-6` |
<!-- E3: aggiungi REFUND-INV-2 (tetto e stati) e REFUND-INV-5 (idempotenza), nell'ordine degli ID. -->

## Side effects

In quest'ordine, che è quello che rende vere REFUND-INV-3, 4 e 5:

1. `PaymentGateway::refund($order->gateway_charge_id, $amount, $idempotencyKey)` **prima** della transazione, con la chiave del client;
2. dentro `DB::transaction()`: la riga del rimborso (in `refunds`, `amount` in euro: C3), `orders.status` aggiornato, la riga di audit;
3. dopo il commit: `OrderRefunded` → `RefundIssued` al cliente.

Se una scrittura fallisce dopo il gateway, il client ripete la richiesta con la **stessa** chiave: il gateway restituisce lo stesso rimborso e le scritture si completano. Oggi `refunds.order_id` è unique (un rimborso per ordine): toglierlo richiede una migrazione nuova, approvata da un umano.

## Errori

- 403 (`AuthorizationException`) — l'attore non passa `OrderPolicy::refund` (REFUND-INV-1).
- 400 — header `Idempotency-Key` mancante (middleware `idempotent`).
- `PaymentGatewayException` — il gateway rifiuta: nessuna scrittura, l'errore torna al client.
- `QueryException` — una scrittura fallisce: rollback di tutto, nessuna notifica (REFUND-INV-3, REFUND-INV-4).
<!-- E3: aggiungi gli errori di REFUND-INV-2 e di REFUND-INV-5. -->

## Non-goals

- Sostituire o modificare `RefundService`: il rimborso totale resta legacy (C1, `refunds.full.md`).
- Annullare un rimborso già eseguito.
- Cambiare l'unità di `refunds.amount` (C3: serve un ADR).
- Rimborsi chiesti dal cliente: solo dal back office.
