# AGENTS.md

Back office di ordini e rimborsi — Laravel 13, PHP 8.3+, SQLite, Pest 4: API pubblica di checkout e area admin per lo staff. Questo file è una mappa: i dettagli stanno nei file a cui punta.

## Topology

- `app/Actions/` — un'operazione di business per classe, metodo `handle()`, con transazione e audit dentro. È la **convenzione dichiarata** per il codice nuovo. Esempio da imitare: `app/Actions/Orders/CancelOrder.php`.
- `app/Policies/OrderPolicy.php` — autorizzazione su ordini e rimborsi; il controller la chiama con `Gate::authorize`.
- `app/Payments/` — gateway di pagamento: `PaymentGateway` (interfaccia), `HttpPaymentGateway` (reale), `FakePaymentGateway` (test).
- `app/Services/RefundService.php` — rimborso totale legacy, in produzione: leggi Conflicts prima di aprirlo.
- `docs/contracts/INDEX.md` — indice dei Behavioral Contract, uno per operazione. Leggilo prima di toccare un'operazione.
- `.harness/memory/facts.md` — fatti verificati sul progetto, ciascuno con il comando che lo riverifica.

## Commands

- Test: `php artisan test`. Un'invariante sola: `vendor/bin/pest --group=CANCEL-INV-1` (il group è l'ID del contract).
- Prima di indovinare rotte, modelli o schema: `php artisan route:list -v`, `php artisan model:show Refund`, `php artisan db:show`.
- Serve una decisione umana (una migrazione, una dipendenza nuova, un dato che non trovi)? Fermati e chiudi il messaggio con `[ATTESA-UMANO]: <domanda>`. Il lavoro non risulta finito: resta in attesa della risposta.

## Environment

- SQLite in `database/database.sqlite`; i test usano SQLite in memoria (`phpunit.xml`).
- I segreti del gateway stanno in `.env`, che resta chiuso: per la configurazione leggi `config/services.php` (`payments`) e `.env.example`.
- I test sono offline solo con il fake del gateway: `$this->fakePayments()` (in `tests/TestCase.php`).

## Pitfalls

- Prima di toccare un'operazione, leggi il suo Behavioral Contract da `docs/contracts/INDEX.md`. Ogni invariante ha almeno un test con `->group('<ID>')`.
- Ogni test nuovo che tocca i pagamenti chiama `$this->fakePayments()`: è automatico solo in `tests/Feature/Payments/`. Fuori da lì parte il gateway HTTP.
- `.env` non si legge, non si stampa e non si cerca (`cat`, `grep`, Read): usa `.env.example`.
- Le migrazioni esistenti non si modificano e non si rilanciano da capo (`migrate:fresh`, `->change()`, `dropColumn`). Lo schema cambia solo con una migrazione nuova e additiva, approvata da un umano.

## Conflicts

Regola: il codice nuovo segue sempre la **convenzione dichiarata** (Actions + `OrderPolicy` + transazione + audit), anche se il legacy è in produzione. Un bugfix nel codice legacy è una modifica minima sul posto; la migrazione del legacy si fa solo se è nello scope del Task Contract. Un'eccezione nuova che incontri va segnalata nel riepilogo finale.

| ID | Dove | Divergenza | Per il codice nuovo |
|---|---|---|---|
| C1 | `app/Services/RefundService.php` | I rimborsi totali passano ancora dal Service legacy, chiamato da `Admin\OrderRefundController`. | Una classe in `app/Actions/Refunds/`. Non estendere il Service: si migra solo se è nello scope del Task Contract. |
| C2 | `RefundService::refundInFull()` | Autorizza con `isStaff()`, quindi anche `support` rimborsa per intero. È un'eccezione nota, coperta da un test. | La fonte dell'autorizzazione è `OrderPolicy::refund` (solo `finance` e `admin`). Non copiare `isStaff()` e non correggerlo fuori scope. |
| C3 | `refunds.amount` | Euro `decimal(8,2)` (legacy). Ogni altro importo, `orders.amount` compreso, è in centesimi interi. | Converti nel codice. La colonna non si cambia senza un ADR. |
| C4 | `Idempotency-Key` | L'idempotenza esiste solo sul checkout (middleware `idempotent` su `POST api/orders`). Il Service passa al gateway la chiave derivata `refund-{id}`, che regge un solo rimborso per ordine. | Ogni operazione che muove denaro riceve la chiave dal client con l'header `Idempotency-Key` e la passa al gateway. |
