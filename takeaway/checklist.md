# Checklist: preparare un repository Laravel per un coding agent

Sette aree, quelle promesse dalla pagina del workshop: leggibilità, contesto, discovery, capacità, vincoli, feedback, verifica. Vale per Claude Code e per Codex.

## Come usarla

Questa checklist **non è un elenco di cose da fare tutte**. Per ogni voce segna una di tre risposte:

- **Sì**: c'è già, e sai indicare il file;
- **No, e mi è costato**: manca, e hai visto un fallimento che la voce avrebbe evitato. È da qui che si parte;
- **Non serve (per ora)**: manca, ma non hai visto il fallimento che previene.

Si lavora solo sulle voci "No, e mi è costato". Niente punteggio: un punteggio spinge a riempire tutte le voci, cioè a copiare l'harness di qualcun altro. Vale la regola: *costruisci lo strato più piccolo che elimina il fallimento che hai davvero*.

Ogni voce riporta:
- il **pezzo dell'harness** che la realizza;
- il **livello del Minimum Viable Harness** (L1 task delimitato · L2 ambiente leggibile · L3 azioni controllate · L4 esecuzione durevole · L5 evidenze · L6 recupero e apprendimento);
- la **trappola del workshop** in cui l'hai vista all'opera, dove c'è: T1 autorizzazione · T2 transazione · T3 doppio rimborso · T4 migrazione distruttiva · T5 accesso a `.env` · T6 "fatto" senza test · T7 tracciabilità. C1–C4 sono le righe di Conflicts dell'AGENTS.md del workshop.

L'ID (`LEG-1`, `VIN-2`, …) serve a citare la voce nella scheda di porting, nelle Lessons e nelle review. La parte in grassetto è la voce in breve: è quella stampata sul foglio A4.

---

## 1. Leggibilità: il codice dice qual è la strada giusta?

| ID | Controllo | Pezzo dell'harness | Liv. | Nel workshop |
|---|---|---|---|---|
| LEG-1 | **Convenzione dichiarata in una riga.** Per il codice nuovo (es. "Actions in `app/Actions/<Dominio>/`"), in AGENTS.md → Topology, con un esempio da imitare | AGENTS.md · Topology | L2 | T1 |
| LEG-2 | **Ogni divergenza dalla convenzione ha una riga in Conflicts**, con "cosa usare al suo posto" e, se c'è, il link all'ADR | AGENTS.md · Conflicts | L2 | T1, T7 (C1) |
| LEG-3 | **Unità e formati non ovvi sono dichiarati** (centesimi o euro, timezone, stati) dove l'agente li incontra: nel tipo, nel nome o in Conflicts | Conflicts · cast ed enum | L2 | T4 (C3) |
| LEG-4 | **Almeno una riparazione di leggibilità**: un'ambiguità corretta nel codice (nome, tipo, enum, `@contract`) invece che spiegata in un documento | codice + docblock `@contract` | L2 | — |
| LEG-5 | **Un controllo che vive solo nella UI** (`@can` in Blade) è anche sul server, oppure è dichiarato come eccezione nota | Policy · Conflicts | L2 | T1 (C2) |

## 2. Contesto: l'agente ha ciò che non può scoprire da solo?

| ID | Controllo | Pezzo dell'harness | Liv. | Nel workshop |
|---|---|---|---|---|
| CTX-1 | **AGENTS.md con 5 sezioni** (Topology, Commands, Environment, Pitfalls, Conflicts): una mappa, non un manuale. Niente che si legga già da `composer.json` o dalle cartelle | AGENTS.md | L2 | E2 |
| CTX-2 | **Un solo file di istruzioni.** `CLAUDE.md` contiene `@AGENTS.md`; Codex legge `AGENTS.md` da solo. Con Laravel Boost i due file li genera Boost dalla stessa fonte, `.ai/guidelines/`: si scrive lì | CLAUDE.md → AGENTS.md | L2 | E2 |
| CTX-3 | **Contract per le operazioni che muovono soldi, permessi o dati irreversibili**, con invarianti identificati da un ID | `docs/contracts/` + INDEX | L2 | E3, REFUND-INV-1..6 |
| CTX-4 | **ADR per le decisioni difficili da invertire** (MADR breve, con Confirmation), raggiungibili da Topology | `docs/adr/` | L2 | E2b, ADR 0001 |
| CTX-5 | **Ogni task non banale parte da un Task Contract approvato**: risultato osservabile, `scope.paths`, `must_not_change`, `evidence` | `.harness/tasks/T-NNN.md` | L1 | E4, T-001 |
| CTX-6 | **Una sessione nuova riprende da `progress.md`**: puntatore in AGENTS.md, più l'hook di session-start | `.harness/memory/progress.md` · session-start | L4 | — |

## 3. Discovery: l'agente trova ciò che serve senza leggere tutto?

| ID | Controllo | Pezzo dell'harness | Liv. | Nel workshop |
|---|---|---|---|---|
| DIS-1 | **Comandi nativi di scoperta in Commands**, quelli che funzionano sul tuo progetto (`route:list`, `about`, `model:show`, `db:show`), con i flag utili (`--path=`, `-v`, `--json`) | AGENTS.md · Commands | L2 | primo gradino della scala |
| DIS-2 | **Dall'AGENTS.md all'operazione in un salto**: Topology → `docs/contracts/INDEX.md` → contract → classe (`@contract`) | INDEX.md · `@contract` | L2 | E3 |
| DIS-3 | **Un MCP o una project map solo per un buco preciso** della discovery che i comandi nativi non coprono (necromancer su Laravel 13, Laravel Boost): uno strumento per scopo | MCP · project map | L2 | secondo gradino |
| DIS-4 | **Dato un file modificato, si trovano i suoi test**: per convenzione di path (`app/**/X.php` → `tests/**/XTest.php`) o con `affected-tests` | convenzione · stop hook | L5 | T6 |
| DIS-5 | **Prova pratica in sola lettura**: "dove si decide l'autorizzazione per <operazione>?", "come si testa senza rete?". L'agente risponde citando i file, senza indovinare | — (prova della discovery) | L2 | run 1 contro run 2 |

## 4. Capacità: l'agente fa il lavoro senza danni e senza aiuto?

| ID | Controllo | Pezzo dell'harness | Liv. | Nel workshop |
|---|---|---|---|---|
| CAP-1 | **I test girano offline**: i servizi esterni hanno un fake, e Pitfalls dice come si attiva | fake + Pitfalls | L3 | `fakePayments()` |
| CAP-2 | **Il database dell'agente è sacrificabile** (SQLite o container) e si ricostruisce con un comando | Environment | L3 | SQLite |
| CAP-3 | **Formatter e analisi statica su un file solo, in pochi secondi** (Pint, PHPStan/Larastan) | Commands · post-edit | L5 | E6 |
| CAP-4 | **Le procedure ripetute sono skill** (abbozzare un Task Contract, aggiornare `progress.md`) in `.agents/skills/`, con un symlink in `.claude/skills/` | skill | L2 | `task-contract`, `progress` |
| CAP-5 | **I comandi rischiosi o lunghi girano in una sandbox** o in un container, non sull'host dello sviluppatore | sandbox dell'agente · Docker | L3 | demo sandbox |

## 5. Vincoli: ciò che non deve succedere è impossibile, non solo sconsigliato?

| ID | Controllo | Pezzo dell'harness | Liv. | Nel workshop |
|---|---|---|---|---|
| VIN-1 | **I segreti non si leggono.** Su Claude Code il deny nativo (`permissions.deny`) **più** il pre-hook, che ferma anche `php -r 'file_get_contents(".env")'`. Su Codex il pre-hook è la guardia che il workshop ha verificato | settings nativi + pre-hook | L3 | T5 |
| VIN-2 | **Le migrazioni esistenti non si modificano** e i comandi distruttivi (`migrate:fresh/refresh/reset/rollback`, `db:wipe`, `->change()`, drop) sono bloccati o richiedono un umano | pre-hook · `approval_required` | L3 | T4 |
| VIN-3 | **Il perimetro del task è applicato**: un file fuori da `scope.paths` o dentro `must_not_change` viene bloccato, non solo segnalato | pre-hook + Task Contract | L1 · L3 | T1 (C1) |
| VIN-4 | **Senza un Task Contract `approved` niente modifiche** ad `app/`, `database/`, `routes/`, `config/` | pre-hook (gate del contract) | L1 | E5 |
| VIN-5 | **Il pre-hook fallisce chiuso**: un errore dello script blocca l'azione (exit 2) su entrambi gli agenti; su Claude anche il timeout (`"onFailure": "block"`). Su Codex un timeout lascia passare: il guard deve restare veloce, senza bootstrap di Laravel | adattatori · `bin/pre-tool` | L3 | E5 |
| VIN-6 | **Le azioni che richiedono un umano** (migrazioni, dipendenze nuove) sono nel Task Contract, e l'agente si ferma a chiedere con `[ATTESA-UMANO]` | `approval_required` · marcatore | L1 | T4, L-003 |

## 6. Feedback: l'agente scopre l'errore mentre lavora, non dopo?

| ID | Controllo | Pezzo dell'harness | Liv. | Nel workshop |
|---|---|---|---|---|
| FB-1 | **Dopo ogni modifica, Pint e PHPStan sul file toccato**, con l'esito che torna all'agente (`additionalContext`), troncato ai primi errori | post-edit hook | L5 | E6 |
| FB-2 | **Ogni blocco dice cosa fare invece** ("scrivi una migrazione nuova e additiva", "leggi `docs/contracts/…`") | messaggi del pre-hook | L3 | E5 |
| FB-3 | **Ogni blocco e ogni fallimento lascia un evento** in `trace.jsonl`, con una classe | trace events · `classify()` | L4 · L6 | E9 |
| FB-4 | **Lo stop hook ha un budget di tentativi**: esaurito il budget passa all'umano invece di andare in loop (Codex, con exit 2, non ha un tetto suo) | stop hook | L6 | demo dello stop |
| FB-5 | **Un fallimento visto due volte diventa una Lesson**, e ogni Lesson diventa un pezzo (regola, Pitfall, test) con una condizione di rimozione | `lessons.md` → harness | L6 | E8, L-003 |

## 7. Verifica: "fatto" è un fatto, non una dichiarazione?

| ID | Controllo | Pezzo dell'harness | Liv. | Nel workshop |
|---|---|---|---|---|
| VER-1 | **Ogni invariante ha almeno un test nel group con il suo ID**: Pest `->group('X-INV-n')`, PHPUnit 10+ `#[Group('X-INV-n')]`, PHPUnit 9 `@group`. Un `--group` per ID quando li lanci | contract ↔ test | L5 | T6 |
| VER-2 | **Lo stop hook lancia i test mirati e blocca il "fatto" se sono rossi** (catena: `affected-tests`, poi convenzione di path, poi suite completa) | stop hook | L5 | T6 |
| VER-3 | **Il Change Receipt separa il dimostrato `[D]` dal dichiarato `[A]`**, e "fatto" vuol dire receipt verde | `.harness/bin/receipt` | L5 | E7 |
| VER-4 | **Il receipt confronta il diff con il Task Contract** (`scope.paths`, `must_not_change`) e segnala i Conflicts toccati | receipt | L5 | T1 (C1) |
| VER-5 | **Sai quanto costa un risultato verificato**: token per invariante verde, confrontati fra una run e l'altra | metriche della run | L5 | run 1 contro run 2 |
| VER-6 | **Chi fa review applica i 5 criteri al diff** (`criteri-verifica.md`), non rilegge il riassunto dell'agente | review umana | L5 | confronto |

---

## Manutenzione: le domande contro il decay

Ogni pezzo dell'harness invecchia. Una volta al mese, e ogni volta che cambi modello o agente, per ogni voce segnata "Sì" chiediti:

1. **Quale fallimento previene?** Se non lo sai dire, è candidata alla rimozione.
2. **Quando è scattata l'ultima volta?** Guarda il trace: `grep '"rule":"<id>"' .harness/runs/*/trace.jsonl | tail -1`.
3. **Il modello di oggi farebbe ancora quell'errore senza questo pezzo?** Provalo: disattiva il pezzo su un task di prova.
4. **Il pezzo dice ancora il vero?** Un Conflict risolto, un comando rinominato o un contract superato fanno più danni di un'assenza.
5. **Costa più di quanto salva?** Un hook lento o rumoroso consuma token e attenzione a ogni turno.

---

<sub>© 2026 Roberto Gallea · [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/deed.it) · dal workshop "Costruire un harness per coding agent affidabili in Laravel", Laravel Day 2026.</sub>
