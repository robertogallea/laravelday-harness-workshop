---
status: accepted
date: 2026-11-14
decision-makers: team back office
---

# La chiave di idempotenza di un rimborso è unica in tutta la tabella, come al gateway

## Context and Problem Statement

Con il rimborso parziale (T-001) un ordine può avere più rimborsi, quindi l'indice unique su `refunds.order_id` del rimborso totale legacy se ne va. Al suo posto serve un vincolo che faccia valere REFUND-INV-5 anche nel database, non solo al gateway: due richieste con la stessa `Idempotency-Key` devono produrre un solo rimborso, anche se arrivano insieme. Il contract dice "stessa chiave **sullo stesso ordine**", mentre il gateway (`PaymentGateway::refund()`) riconosce la chiave da sola: con un altro `chargeId` risponde `IdempotencyKeyReused`. In che ambito è unica la chiave di idempotenza nella tabella `refunds`?

## Decision Drivers

- Il database e il gateway devono avere lo stesso ambito: una chiave che uno accetta e l'altro rifiuta diventa un errore a metà operazione o un rimborso senza soldi.
- Il replay deve ritrovare il rimborso esistente dalla chiave, senza scrivere niente (REFUND-INV-5).
- Il vincolo deve reggere due richieste contemporanee: un indice unique, non una `SELECT` prima dell'`INSERT`.
- Con dati in produzione allargare l'ambito di un indice è facile, restringerlo no.
- Le righe del rimborso totale legacy non hanno una chiave del client (`RefundService` usa `refund-{id}` solo verso il gateway).

## Considered Options

- `idempotency_key` nullable e unique su tutta la tabella.
- `unique(order_id, idempotency_key)`: unica per ordine. È la scelta delle due run 2 di riferimento, di Claude e di Codex.
- Nessun indice: un controllo nel codice prima di scrivere, oppure solo il middleware `idempotent` del checkout.

## Decision Outcome

Scelta: **unique su tutta la tabella**, perché è l'ambito del gateway: una chiave già usata per un altro addebito non diventa mai una riga, e il replay si trova con la chiave da sola. In più è la scelta che si può correggere: passare a un indice per ordine è un `dropUnique` e un `unique`, mentre passare dall'indice per ordine a quello globale fallisce appena la stessa chiave compare su due ordini.

### Consequences

- Buono, perché database e gateway rifiutano la stessa cosa: nessun rimborso registrato con un `gateway_refund_id` che appartiene a un altro ordine.
- Buono, perché le righe legacy restano valide: la colonna è nullable e un indice unique ammette più `NULL`.
- Cattivo, perché il client deve generare chiavi uniche in assoluto (un UUID per richiesta), non per ordine.
- Cattivo, perché il contract dice "sullo stesso ordine" e chi legge solo quello si aspetta l'altro ambito. La frase di REFUND-INV-5 va precisata con un task che metta il contract nello scope.

### Confirmation

- La migrazione dei rimborsi parziali dichiara `->unique()` sulla sola `idempotency_key`; in revisione un `unique(['order_id', 'idempotency_key'])` su `refunds` si respinge con un rimando a questo ADR.
- Un test del group `REFUND-INV-5`: la stessa chiave su due ordini diversi non scrive un secondo rimborso.
