---
task: T-001
updated: 2026-11-13T09:45
---

# Progress

Si sovrascrive (non si accoda) a fine sessione. Lo stop controlla che `task` sia il task attivo e che il file sia stato aggiornato in questa sessione. Il receipt riporta le ultime due sezioni come dichiarate [A].

## Stato

T-001 completato: il rimborso parziale passa da `RefundPartially`, aggiorna stato, audit e notifica dopo commit. Coperti e verdi REFUND-INV-1..6 e suite completa.

## Prossimo passo

Nessuno: task completato.

## Conflicts incontrati

Il rimborso totale resta nel `RefundService` legacy (C1/C2/C4), intenzionalmente non modificato.

## Rischi aperti

Nessuno noto: eseguite tutte le evidence richieste su SQLite in memoria.
