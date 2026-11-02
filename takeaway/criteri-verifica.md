# Criteri per verificare una modifica proposta da un agente

Cinque criteri, quelli promessi dalla pagina del workshop: autorizzazione, tempistica delle transazioni, tracciabilità, idempotenza, test.

## Come usarli

Si applicano al **diff**, non al riassunto dell'agente. Il riassunto dice cosa l'agente crede di aver fatto, il diff dice cosa ha fatto davvero.

Per ogni criterio ci sono tre cose:
- **domande** da fare al diff;
- **evidenze** da pretendere: un test, un comando, una riga del receipt;
- **segnali d'allarme**: pattern che si trovano con una ricerca nel diff.

Una domanda senza evidenza ha una sola risposta onesta: "non dimostrato". Non vuol dire "sbagliato": vuol dire che manca la prova.

Ordine consigliato: prima i **test** (se la suite è rossa, il resto è prematuro), poi **autorizzazione**, **idempotenza**, **transazioni** e **tracciabilità**. Su un diff medio bastano 10–15 minuti. Se non bastano, il task era troppo grande per un solo Task Contract.

---

## 1. Autorizzazione

Una modifica plausibile copia il controllo che trova più vicino. Quello giusto è scritto altrove: nella Policy, nel Gate, nel middleware.

**Domande al diff**
- Nel progetto, dov'è la fonte dell'autorizzazione per questa operazione (Policy, Gate, middleware `can:`)? Il diff usa proprio quella?
- Il controllo sta **sul server**, cioè in `authorize()`, `Gate::authorize()`, nel middleware o in un Form Request? Un `@can` nella vista non basta.
- Il diff ha copiato un controllo locale (`isStaff()`, `$user->role === …`) invece di usare la Policy?
- Ha cambiato l'autorizzazione di un percorso **esistente**, anche "per coerenza"? È nello scope del Task Contract?

**Evidenze da pretendere**
- Un test negativo per ogni ruolo che **non** deve passare (403) e un test positivo per ogni ruolo che deve passare.
- I test dei percorsi esistenti, invariati e verdi: il comportamento legacy dichiarato in Conflicts resta com'è.
- `php artisan route:list --path=<percorso> -v`, che mostra il middleware attivo sulla route nuova.

**Segnali d'allarme**: `->role ===`, `in_array($user->role` nel codice nuovo; `authorize` tolto o spostato; un test di autorizzazione esistente modificato.

*Nel workshop: T1, REFUND-INV-1, Conflicts C2.*

---

## 2. Tempistica delle transazioni

Una modifica plausibile esegue le scritture nell'ordine in cui le pensa. Quella giusta decide che cosa è atomico, che cosa sta fuori dalla transazione e che cosa parte solo dopo il commit.

**Domande al diff**
- Quali scritture devono riuscire o fallire **insieme**? Stanno dentro un'unica `DB::transaction`?
- Ci sono chiamate esterne (gateway, HTTP, storage) **dentro** la transazione? Se la transazione fa rollback, quella chiamata si può annullare? Si può ripetere senza danni?
- Eventi, notifiche, job e mail partono **dopo il commit** (`ShouldDispatchAfterCommit`, `->afterCommit()`, `DB::afterCommit`)? Oppure il cliente riceve una mail per un rimborso che poi fa rollback?
- Se l'operazione legge un aggregato e poi scrive (es. "somma dei rimborsi ≤ importo dell'ordine"), la lettura è protetta dalla concorrenza, con `lockForUpdate` o con un vincolo nel database?

**Evidenze da pretendere**
- Un test con un **guasto iniettato** a metà sequenza (un trigger SQLite `RAISE(ABORT)`, o un mock che lancia un'eccezione) che dimostra: nessuna scrittura parziale e nessuna notifica partita (`Notification::assertNothingSent()`).
- L'ordine dei side effects scritto nel Behavioral Contract, e un test che lo copre.

**Segnali d'allarme**: `event(`, `dispatch(`, `->notify(` subito dopo un `create`/`update` senza transazione; una chiamata HTTP o al gateway dentro `DB::transaction(function () {`; nessun test che faccia fallire qualcosa.

*Nel workshop: T2, REFUND-INV-3, REFUND-INV-4.*

---

## 3. Tracciabilità

Una modifica plausibile scrive un `Log::info`. Quella giusta lascia una traccia che si può interrogare: chi, cosa, quando, perché, con quale riferimento esterno.

**Domande al diff**
- L'operazione registra **attore, azione, soggetto, motivo** nella fonte che il progetto usa per l'audit (tabella di audit, activity log, eventi di dominio)? Il log applicativo non basta.
- Gli identificativi esterni (`gateway_refund_id`, chiave di idempotenza, request id) sono salvati, così un caso si ricostruisce da tutti e due i lati?
- La motivazione che l'utente fornisce (`reason`) viene salvata?
- La modifica stessa è tracciabile: il commit cita il Task Contract (`T-NNN`) ed esiste un receipt?

**Evidenze da pretendere**
- Un test che asserisce la riga di audit, con attore e payload attesi.
- Nel receipt: Task Contract, diff ed esiti dei comandi, cioè la traccia del cambiamento oltre a quella del dato.

**Segnali d'allarme**: `Log::info(` come unica traccia in un'Action nuova; `reason` validato ma mai salvato; un'Action nuova che non usa il servizio di audit usato dalle Actions vicine.

*Nel workshop: T7, REFUND-INV-6, Conflicts C1.*

---

## 4. Idempotenza

Una modifica plausibile funziona quando la richiesta arriva una volta. Quella giusta dice cosa succede quando arriva due volte: un retry, un doppio click, un job rieseguito.

**Domande al diff**
- Se la stessa richiesta arriva due volte, quanti effetti produce? Quanti nel database e quanti nel sistema esterno?
- Da dove viene la chiave di idempotenza? Una chiave del client (`Idempotency-Key`) identifica l'**intenzione**. Una chiave derivata (`refund-{id}`, `refund-{id}-{amount}`) è una supposizione su quante intenzioni possono esistere.
- Una chiave generata a ogni chiamata (`Str::uuid()`) non protegge da niente: c'è?
- Due richieste **legittime** e uguali (due rimborsi parziali dello stesso importo) producono due effetti?
- Stessa chiave con dati diversi: errore esplicito, oppure il sistema restituisce in silenzio il risultato della prima?
- Il vincolo è anche nel database (indice unique sulla chiave), oppure solo nel codice? Con quale ambito: per entità o per tutta la tabella?

**Evidenze da pretendere**
- Un test: stessa chiave due volte → un solo effetto (una riga, una chiamata al fake).
- Un test: chiavi diverse e dati uguali → due effetti.
- Un test: stessa chiave con dati diversi → errore esplicito, nessun effetto.

**Segnali d'allarme**: `Str::uuid()` o `now()` nella costruzione di una chiave; una chiave copiata da codice pensato per "una volta per entità"; nessuna migrazione con `unique` sulla chiave nuova.

*Nel workshop: T3, REFUND-INV-2, REFUND-INV-5, Conflicts C4, ADR 0002.*

---

## 5. Test

Una modifica plausibile ha test verdi scritti dallo stesso agente che ha scritto il codice. Quella giusta ha test che **falliscono senza la modifica** e che coprono gli invarianti, non solo il percorso felice.

**Domande al diff**
- Ogni invariante toccato ha almeno un test nel suo group, e il test è verde?
- I test nuovi falliscono se togli la riga che garantisce l'invariante? Prova di sensibilità: commenta la `DB::transaction` o il controllo della Policy e rilancia.
- È stata eseguita la suite rilevante (test mirati o suite completa), o solo un `--filter` sui test nuovi?
- Il diff **modifica o cancella test esistenti**? Ogni modifica a un test esistente va giustificata nel Task Contract.
- I test girano offline, con i fake, senza leggere `.env`?

**Evidenze da pretendere**
- Il Change Receipt: invariante → test → esito, dove 0 test vuol dire "non coperta".
- L'output dei test mirati o della suite completa, eseguiti da uno script e non riportati dall'agente.
- `git diff --stat <base> -- tests/`: i file esistenti sono invariati, oppure ogni modifica è giustificata.

**Segnali d'allarme**: solo test del percorso felice; asserzioni sui mock invece che sullo stato; `->skip()`, `markTestSkipped`, `assertTrue(true)`; un test esistente "aggiornato" per riflettere il nuovo comportamento.

*Nel workshop: T6, stop hook, receipt.*

---

## Modello di commento di review

```markdown
Review di <PR o branch> — Task Contract T-NNN

| Criterio | Esito | Evidenza |
|---|---|---|
| Test | ✅ / ❌ / non dimostrato | comando o riga del receipt |
| Autorizzazione | | |
| Idempotenza | | |
| Transazioni | | |
| Tracciabilità | | |

Verdetto: corretta / plausibile, non dimostrata / sbagliata (metodo-plausibile-vs-corretto.md)
Da aggiungere prima del merge: …
Lesson candidata: …
```

---

<sub>© 2026 Roberto Gallea · [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/deed.it) · dal workshop "Costruire un harness per coding agent affidabili in Laravel", Laravel Day 2026.</sub>
