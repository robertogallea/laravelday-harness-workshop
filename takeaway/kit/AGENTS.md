# AGENTS.md

<!-- Una riga: che cos'è il progetto e lo stack. È una mappa, non un manuale:
     scrivi solo ciò che l'agente non scopre da solo leggendo composer.json, config e cartelle. -->

## Topology

<!-- Dove stanno le cose che contano e quando leggerle. Una riga per voce: `percorso` — cosa c'è / quando aprirlo.
     Punta sempre a: indice dei contract, ADR, Task Contract, memoria. -->
- `app/…` — <!-- convenzione dichiarata per il codice nuovo -->
- `docs/contracts/INDEX.md` — indice dei Behavioral Contract.
- `docs/glossary.md` — le parole del dominio e dove stanno nel codice.
- `docs/adr/` — decisioni architetturali; leggile quando devi scegliere tra due pattern.
- `.harness/tasks/` — Task Contract; il task attivo è quello con `status: approved`.
- `.harness/memory/` — Facts, Decisions, Progress, Lessons; a inizio sessione leggi `progress.md`.

## Commands

<!-- Solo i comandi non ovvi o quelli che definiscono "fatto". Il resto sta in composer.json. -->
- Test mirati su un'invariante: `vendor/bin/pest --group=<ID>` <!-- PHPUnit: vendor/bin/phpunit --group <ID> -->
- Prima di dire "fatto": <!-- il comando che lo dimostra: `composer harness:check`, oppure `php .harness/bin/receipt T-NNN` --> deve essere verde.
- Serve una decisione umana? Fermati e chiudi il messaggio con `[ATTESA-UMANO]: <domanda>`. Il lavoro non risulta finito: resta in attesa della risposta.

## Environment

<!-- Ciò che serve sapere per eseguire senza danni: DB, servizi esterni, segreti (dove sono e come evitarli), rete nei test. -->

## Pitfalls

<!-- Gli errori che l'agente ha già fatto o farebbe qui. Frase positiva: cosa fare, non cosa evitare.
     Ogni voce nasce da una Lesson; se il fallimento non si presenta più, rimuovila. -->
- Prima di modificare <!-- un'Action / un'operazione -->, leggi il suo Behavioral Contract da `INDEX.md`.

## Conflicts

Regola: il codice nuovo segue sempre la convenzione dichiarata. Un bugfix nel codice legacy è una modifica minima sul posto; la migrazione si fa solo se è nello scope del Task Contract. Le eccezioni nuove vanno annotate in `.harness/memory/progress.md` → "Conflicts incontrati".

| ID | Dove | Divergenza | Per il codice nuovo |
|---|---|---|---|
| C1 | <!-- percorso, colonna o header --> | <!-- cosa diverge dalla convenzione dichiarata --> | <!-- cosa usare al suo posto; link all'ADR se c'è --> |
