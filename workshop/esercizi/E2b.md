# E2b — ADR 0001 scritto a mano

**Blocco:** B2 · **Durata:** 10' · **Livello MVH:** L2 (ambiente leggibile)
**Ingresso:** `cp-02` · **Uscita:** `cp-02b`

## Obiettivo

La riga C1 di Conflicts dice **cosa** fare: il codice nuovo va in `app/Actions/`, non in `RefundService`. Non dice **perché**. Il perché non sta nel codice: un agente che legge `app/` vede due stili e può solo indovinare quale vince. Un ADR conserva la decisione, le alternative scartate e il prezzo pagato.

Lo scrivi a mano, senza agente: è il punto dell'esercitazione. Dopo, il docente mostra un ADR **inferito** dal codice e li confrontate.

## Da dove parti

- `docs/adr/0000-template.md` — MADR breve, con la sezione Confirmation.
- `AGENTS.md`: la riga C1 e la regola generale di Conflicts.
- Il codice: `app/Actions/Orders/CancelOrder.php` (lo stile dichiarato) e `app/Services/RefundService.php` (il legacy).
- `docs/glossary.md` — le parole del dominio, se ti servono.

## Cosa fare

1. Copia il template in `docs/adr/0001-actions-over-services.md` e compilalo:
   - **frontmatter**: `status` (la decisione è già presa: `accepted`) e `date` vera;
   - **Context and Problem Statement**: 2–3 frasi, chiuse da una domanda;
   - **Decision Drivers**: le forze che pesano (pensa a contract e test, ad autorizzazione e transazione, al legacy in produzione);
   - **Considered Options**: almeno due, con quella scartata scritta seriamente;
   - **Decision Outcome**, **Consequences** (anche quelle cattive) e **Confirmation**: come si controlla che il codice nuovo rispetti la decisione.
2. In `AGENTS.md`:
   - nella riga **C1** di Conflicts aggiungi il link all'ADR, per esempio `Perché: [ADR 0001](docs/adr/0001-actions-over-services.md).`;
   - in **Topology** aggiungi `docs/adr/`, con quando aprirlo.

## Verifica

```bash
php .harness/bin/verify E2b
```

Controlla: `docs/adr/0001-*.md` esiste; frontmatter con `status` e una `date` vera; le sezioni Context and Problem Statement, Decision Drivers, Considered Options (almeno 2), Decision Outcome, Consequences e Confirmation; la riga di Conflicts che nomina `RefundService` ha un link a `docs/adr/0001-*.md` che risolve; Topology cita `docs/adr/`. Non giudica il perché che hai scritto: quello lo confronti con il docente quando esce `cp-02b`:

```bash
git diff cp-02b -- docs/adr/
```

## Indietro?

```bash
php workshop/bin/checkpoint prendi E2b
```

Attenzione: prende anche l'`AGENTS.md` del docente (la riga C1 e la Topology cambiano). Il tuo resta nel commit "wip".

## Dopo

Demo del docente: `necromancer:infer` scrive un ADR dal codice. Sarà plausibile. Confrontatelo con il vostro: cosa sa chi ha deciso, che il codice non dice?
