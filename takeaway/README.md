# Takeaway: cosa vi portate a casa

Il pacchetto **non è un harness da copiare**. È un modo di decidere quale harness serve al vostro progetto. Ogni documento porta allo stesso gesto, riassunto in *costruisci lo strato più piccolo che elimina il fallimento che hai davvero*:

1. parti da un fallimento osservato;
2. scegli il pezzo minimo che lo elimina;
3. dimostra con un'evidenza che il pezzo funziona;
4. toglilo quando non serve più.

## Le promesse della pagina, e dove sono

| Promessa della pagina del workshop | File | Quando si usa nella giornata |
|---|---|---|
| Il confronto tra due esecuzioni dello stesso task, per individuare le assunzioni introdotte da un ambiente incompleto | [confronto-run.md](confronto-run.md) | confronto di run 1 e run 2 (15:45) |
| Una checklist per preparare un repository: leggibilità, contesto, discovery, capacità, vincoli, feedback, verifica | [checklist.md](checklist.md) (38 voci) | presentata alle 9:50 sul foglio A4, citata in ogni esercitazione ("questa è VIN-2"), usata nel porting |
| Criteri per verificare una modifica: autorizzazione, timing delle transazioni, tracciabilità, idempotenza, test | [criteri-verifica.md](criteri-verifica.md) | review delle run, review a coppie del porting |
| Un metodo per distinguere una modifica plausibile da una che rispetta gli invarianti | [metodo-plausibile-vs-corretto.md](metodo-plausibile-vs-corretto.md) | chiusura del confronto: verdetto a tre valori |
| Riuso nei propri progetti | [scheda-porting.md](scheda-porting.md), [kit/](kit/README.md), [`bin/verify-port`](bin/verify-port), il wizard [`bin/porting`](bin/porting) | blocco di porting (16:50, 35') |
| — chi non ha un progetto a disposizione | [scenario-riserva.md](scenario-riserva.md) | porting, sul repo del workshop |

Il foglio A4 stampato (fronte: la checklist in breve; retro: i cinque criteri, il metodo in tre righe e i comandi) si genera da questi file: la fonte è sempre il markdown.

## Il controllo del porting

```bash
php takeaway/bin/verify-port <cartella del tuo progetto>
```

Lo lanci dal repo del workshop sul tuo progetto, sul branch `harness/porting`. Guarda solo la struttura dei pezzi che hai portato (sezioni di AGENTS.md, `@contract` che risolvono, invarianti con un test nel loro group, adattatori leggibili, file del pre-hook), in sola lettura: non lancia i tuoi test e non ha bisogno di `vendor/`. Un pezzo che non hai scelto è SKIP. Exit 0 = struttura a posto.

## Il wizard del porting

```bash
cd <il tuo progetto>
bash <repo del workshop>/takeaway/bin/porting
```

Ti porta nelle fasi 0–4 della scheda una alla volta. Fa i collegamenti (branch, file nel posto giusto, `@contract`, group del test, `harness:check`, un commit per passo, `verify-port`); il fallimento, la scelta del pezzo e il contenuto li scrivi tu. Lo stato sta in `.git/porting-wizard.env`: il `.env` del progetto non lo legge e non lo scrive.

## Compatibilità

- **Claude Code e Codex.** I documenti non dipendono dall'agente. Gli snippet mostrano le due varianti; dove il workshop non ha potuto verificare qualcosa su Codex (permission profile, sandbox), la scheda lo dice.
- **Laravel 8 o successivo, senza necromancer.** La scheda parte dai comandi artisan nativi e dalla selezione dei test per convenzione di path. Necromancer e Laravel Boost compaiono solo come secondo gradino della discovery. Gli script (`verify-port` e il pre-hook, se lo porti) girano con il PHP dell'host, 8.1 o successivo, qualunque sia la versione del progetto.
- **Pest o PHPUnit.** I group degli invarianti sono indicati nelle tre sintassi: Pest `->group()`, PHPUnit 10+ `#[Group]`, PHPUnit 9 `@group`.
- **Il wizard su Linux, macOS e Windows.** `bin/porting` è uno script bash: su Windows gira in Git Bash (arriva con Git for Windows) o in WSL. Le modifiche ai file le fa il PHP del progetto, non `sed`. Provato su Linux con tre progetti (skeleton Laravel 13 con Boost e PHPUnit, un progetto senza git con Pest, un CLAUDE.md scritto a mano); con il bash 3.2 di macOS è controllata la sintassi; su Windows non è ancora stato provato.
- **Nessun comando AI.** Contract e ADR si scrivono a mano, o con il proprio agente.

## Licenze

- **Codice e template** (`bin/verify-port`, `bin/porting`, `kit/`, gli snippet dei documenti, e tutto il resto del repo): **MIT**, vedi [`LICENSE`](../LICENSE). Si copiano nei progetti, anche aziendali, senza attrito.
- **Testi** di questa cartella (`README.md`, `checklist.md`, `criteri-verifica.md`, `metodo-plausibile-vs-corretto.md`, `scheda-porting.md`, `scenario-riserva.md`, `confronto-run.md`): **CC BY 4.0**, vedi [LICENSE.md](LICENSE.md). Si riusano anche nella formazione interna, citando la fonte.

La versione consegnata il 19 novembre 2026 è il tag `laravelday-2026`.

---

<sub>© 2026 Roberto Gallea · [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/deed.it) · dal workshop "Costruire un harness per coding agent affidabili in Laravel", Laravel Day 2026.</sub>
