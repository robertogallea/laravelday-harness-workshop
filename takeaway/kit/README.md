# Kit di template dell'harness

Template vuoti, con brevi commenti guida `<!-- … -->`. **Sono da adattare, non da copiare in blocco**: copia un file solo quando un passo della [scheda di porting](../scheda-porting.md) lo chiede, o quando un fallimento che hai visto lo giustifica.

I percorsi dentro `kit/` sono quelli che i file hanno nel progetto: `kit/docs/contracts/_template.md` va in `docs/contracts/`, `kit/.harness/tasks/T-NNN.md` in `.harness/tasks/`.

| Pezzo | Template | Esempio compilato nel repo del workshop |
|---|---|---|
| AGENTS.md + CLAUDE.md | `AGENTS.md`, `CLAUDE.md` | `AGENTS.md`, `CLAUDE.md` (E2) |
| Behavioral Contract | `docs/contracts/_template.md`, `docs/contracts/INDEX.md` | `docs/contracts/orders.cancel.md`, `refunds.partial.md`, `INDEX.md` (E3) |
| Glossario | `docs/glossary.md` | `docs/glossary.md` |
| ADR (MADR breve) | `docs/adr/0000-template.md` | `docs/adr/0001-actions-over-services.md` (E2b), `0002-chiave-di-idempotenza-unica.md` (E8) |
| Task Contract | `.harness/tasks/T-NNN.md` | `.harness/tasks/T-001.md` (E4) |
| Memoria | `.harness/memory/{facts,decisions,progress,lessons}.md` | `.harness/memory/` (E1, E8) |
| Change Receipt | `.harness/runs/RUN-ID/receipt.md` (schema dell'output dello script) | `php .harness/bin/receipt T-001 --of=run-1` (E7) |
| Piano del porting | `docs/harness-porting.md` | — (lo compili tu, fase 0 della scheda) |

Gli esempi compilati stanno nel checkpoint finale: `git switch -c esempi laravelday-2026` (durante il porting, quando il tag non è ancora pubblicato: `cp-08`). Gli script (`.harness/bin/`, `.harness/lib/`), le regole (`.harness/rules/`, `.harness/config.php`) e gli adattatori (`.claude/settings.json`, `.codex/hooks.json`) restano dove sono nel repo: la scheda dice quali aprire e cosa togliere.

## Come si collegano i pezzi

```text
CLAUDE.md ──@AGENTS.md──▶ AGENTS.md  (Topology · Commands · Environment · Pitfalls · Conflicts)
                             │ Topology
             ┌───────────────┼──────────────────┬───────────────────┐
             ▼               ▼                  ▼                   ▼
   docs/contracts/INDEX.md  docs/adr/      .harness/tasks/    .harness/memory/
             │                                  │ evidence          │
             ▼                                  ▼                   │
   <operazione>.md ◀── @contract ── classe   test (--group=ID) ◀──┘ progress.md: [A]
     X-INV-1..n ──── group = ID ─────────────▶  │
                                                ▼
                                     php .harness/bin/receipt ─▶ receipt.md ([D] + [A])
```

1. **AGENTS.md** è la mappa: non contiene regole di dominio, punta a contract, ADR, task e memoria. CLAUDE.md lo importa con `@AGENTS.md`; Codex lo legge da solo.
2. Il **Behavioral Contract** descrive un'operazione. La classe lo cita nel docblock (`@contract docs/contracts/<nome>.md`), e ogni invariante è un group di test con lo stesso ID.
3. Il **Task Contract** descrive un lavoro. Il frontmatter lo leggono gli script (scope per il pre-hook, `evidence` per lo stop e il receipt); il corpo spiega il risultato atteso e il perché del perimetro, senza ripetere il frontmatter.
4. Il **Change Receipt** è l'output di uno script: unisce Task Contract, diff, esiti dei comandi, invarianti → test e l'elenco Conflicts di AGENTS.md. Le parti `[A]` le copia da `progress.md` e le marca come dichiarate, non dimostrate.
5. La **memoria** alimenta il resto: Facts → AGENTS.md, Decisions → ADR (se difficili da invertire, sorprendenti e frutto di un trade-off), Lessons → regola o Pitfall (con la condizione di rimozione), Progress → receipt e stop hook.

Se copi `.harness/` nel tuo progetto, aggiungi al `.gitignore` le cartelle che gli script scrivono: `/.harness/state/` e `/.harness/runs/`.

---

<sub>Template: © 2026 Roberto Gallea · licenza MIT (vedi `LICENSE` nella root del repo): copiali e modificali nei tuoi progetti, anche aziendali, senza attribuzione nei file copiati.</sub>
