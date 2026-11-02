# Scenario di riserva: il checkout `PlaceOrder`

Per chi non ha un progetto a disposizione durante il porting: laptop aziendale senza codice, NDA, progetto non Laravel. Si segue la [scheda di porting](scheda-porting.md) sul repo del workshop, come se fosse il tuo progetto, su un'operazione che oggi nessuno ha toccato: il checkout.

`PlaceOrder` (`app/Actions/Orders/PlaceOrder.php`, rotta `POST api/orders`) segue già la convenzione dichiarata: è un'Action, ha la transazione e l'audit, e riceve dal client l'header `Idempotency-Key` (middleware `idempotent`). Non ha un Behavioral Contract, e i suoi test (`tests/Feature/Payments/PlaceOrderTest.php`) non hanno group.

## 0. Preparazione

```bash
git switch -c harness/porting cp-08
mkdir -p docs && cp takeaway/kit/docs/harness-porting.md docs/
```

Qui `W` è il repo stesso: dove la scheda dice `$W/takeaway/…`, usa `takeaway/…`.

## 1. Il fallimento (già osservato)

Immagina il task che ha prodotto questo codice: "il checkout deve essere idempotente: se il client ripete la richiesta, non addebitare due volte". La soluzione è plausibile: si cerca un ordine **del cliente** con la stessa `checkout_key` e, se c'è, lo si restituisce. Il test "returns the same order when the checkout is retried with the same key" è verde.

Due casi che il test non prova, verificati sul codice di `cp-08`:

| Richiesta | Cosa succede | Cosa dovrebbe succedere |
|---|---|---|
| Stesso cliente, stessa chiave, `amount` 2490 e poi 9990 | la seconda risponde **200** con l'ordine da 2490: il client crede di aver pagato 99,90 € | un errore esplicito, nessun addebito |
| Due clienti diversi, stessa chiave | il secondo riceve **500** (`UniqueConstraintViolationException` su `orders.checkout_key`): la ricerca è per cliente, l'indice unique è su tutta la tabella | da decidere: chiave unica per cliente o per tutta la tabella, ma mai un 500 |

Compila la tabella del fallimento con questi dati. Il criterio violato è l'idempotenza; il codice imitato è il controllo "esiste già un ordine con questa chiave?". Confronta con la quarta sezione di [criteri-verifica.md](criteri-verifica.md): "stessa chiave con dati diversi" e "con quale ambito".

## 2. Il pezzo

Un Behavioral Contract con un test che morde (CTX-3, VER-1, L2 · L5). La regola non è scritta da nessuna parte: finché non lo è, la domanda "il checkout è corretto?" non ha risposta.

## 3. I passi

**3.1 Una riga in Conflicts** (5 minuti al massimo: AGENTS.md c'è già). Aggiungi C5: `orders.checkout_key` è unica su tutta la tabella, mentre `PlaceOrder` cerca la chiave solo fra gli ordini del cliente. Per il codice nuovo: l'ambito della chiave lo decide il contract, non la query.

**3.2 Il contract** `docs/contracts/orders.place.md`, dal template del kit, più la riga in `docs/contracts/INDEX.md`. Tre invarianti al massimo, per esempio:

| ID | Regola |
|---|---|
| CHECKOUT-INV-1 | Stessa chiave e stessi dati: un solo ordine e un solo addebito; la seconda risposta restituisce l'ordine esistente. |
| CHECKOUT-INV-2 | Stessa chiave e dati diversi: errore esplicito (4xx), nessun addebito nuovo, nessuna scrittura. |
| CHECKOUT-INV-3 | Ambito della chiave: <decidilo tu, e scrivi nel Non-goals che cosa non fa>. |

Poi:
- `@contract docs/contracts/orders.place.md` nel docblock di `PlaceOrder`;
- `->group('CHECKOUT-INV-1')` sul test esistente del retry, che resta verde;
- un test nuovo con `->group('CHECKOUT-INV-2')`: **è rosso**, ed è il risultato giusto. Il test morde il fallimento prima ancora della correzione: la correzione è il primo task di lunedì, con il suo Task Contract.

**3.3 Il Task Contract della correzione**, in bozza: `.harness/tasks/T-002.md` dal kit, `status: draft`, `scope.paths` su `app/Actions/Orders/PlaceOrder.php` e sui test del checkout, `must_not_change` sulle migrazioni esistenti, `evidence` con un `--group` per ogni ID. Se CHECKOUT-INV-3 cambia l'indice, la migrazione va in `approval_required`.

## Controllo

```bash
vendor/bin/pest --group=CHECKOUT-INV-1     # verde
vendor/bin/pest --group=CHECKOUT-INV-2     # rosso: il fallimento è documentato
php takeaway/bin/verify-port .
```

Sul repo del workshop il controllo della struttura esce comunque rosso, per `refunds.partial.md`: i suoi sei invarianti non hanno test in nessun checkpoint, perché il codice del rimborso parziale lo scrive l'agente nella run, non il docente. Su `orders.place.md` invece nessun FAIL: OK per CHECKOUT-INV-1 e 2 (il test rosso c'è, ed è quello che conta qui) e per il `@contract`, un WARN se CHECKOUT-INV-3 non ha ancora un test.

## A coppie

Il tuo compagno applica i 5 criteri al tuo diff (`git diff cp-08...harness/porting`), tu ai suoi. Se lavorate entrambi su questo scenario, confrontate la scelta di CHECKOUT-INV-3: è la stessa domanda dell'ADR 0002, su un'altra tabella.

---

<sub>© 2026 Roberto Gallea · [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/deed.it) · dal workshop "Costruire un harness per coding agent affidabili in Laravel", Laravel Day 2026.</sub>
