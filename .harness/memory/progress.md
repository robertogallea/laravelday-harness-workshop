---
task: T-001
updated: 2026-11-13T10:40
---

# Progress

Si sovrascrive (non si accoda) a fine sessione. Lo stop controlla che `task` sia il task attivo e che il file sia stato aggiornato in questa sessione. Il receipt riporta le ultime due sezioni come dichiarate [A].

## Stato

T-001 implementato. `POST /admin/orders/{order}/refunds` (middleware `idempotent`) → `StorePartialRefundRequest` (Policy + validazione) → `App\Actions\Refunds\RefundOrderPartially` (`@contract docs/contracts/refunds.partial.md`). Migrazione additiva `2026_11_13_100000_add_partial_refunds_to_refunds_table.php`: via unique su `order_id`, nuove colonne `reason`, `idempotency_key`, unique `(order_id, idempotency_key)`. `OrderStatus::PartiallyRefunded`; `OrderPolicy::refund` ammette `paid` e `partially_refunded`.
Verdi: REFUND-INV-1..6 (`tests/Feature/Refunds/PartialRefundTest.php`), ORDER-INV-1 ramo `partially_refunded`, suite completa 46/46. `RefundService` non toccato; test legacy verdi.

## Prossimo passo

Revisione umana; poi, fuori da T-001: aggiornare `docs/contracts/INDEX.md` (classe `RefundOrderPartially` al posto di "da creare") e `docs/glossary.md` (stato `partially_refunded`, rimborso parziale esistente). Migrazione non ancora lanciata su `database/database.sqlite` (solo nei test, in memoria).

## Conflicts incontrati

- C2 ha un effetto nuovo: la vista `admin/orders/show` mostra il bottone del rimborso totale con `@can('refund')`; ora la Policy passa anche per `partially_refunded`, ma `RefundService` rifiuta (DomainException → 500). Vista e Service fuori scope.
- Il middleware `idempotent` richiede un header: un form HTML del back office non può inviarlo. Nessun form per il parziale nella vista (fuori scope).

## Rischi aperti

- Concorrenza: il saldo rimborsabile si controlla prima del gateway, senza lock; due richieste parallele con chiavi diverse possono superare `orders.amount`. Non testato.
- Replay con la stessa chiave dopo che quel rimborso ha portato l'ordine a `refunded`: la Policy risponde 403 invece di restituire il rimborso esistente (nessun doppio rimborso, ma non è il "restituisce quello esistente" del contract).
- `PaymentGatewayException` (anche `IdempotencyKeyReused`) diventa 422 con errore `refund`: scelta mia, il contract dice solo "torna al client".
- `down()` della migrazione fallisce se esistono più rimborsi per ordine.
