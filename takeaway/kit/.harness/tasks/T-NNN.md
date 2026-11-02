---
id: T-NNN
status: draft               # draft | approved — lo cambi tu, mai l'agente; uno solo approved alla volta
scope:
  paths: []                 # file o glob che l'agente può modificare; il pre-hook blocca il resto
must_not_change: []         # file che non devono cambiare (codice legacy, migrazioni esistenti, segreti)
evidence: []                # comandi che devono passare: un --group per invariante coinvolta, poi la suite
approval_required: []       # modifiche da mostrare all'umano prima di farle (migrazioni, dipendenze, …)
---

# T-NNN — <!-- titolo -->

## Risultato atteso

<!-- Cosa è vero, a task finito, per chi usa il sistema. Osservabile, non "implementare X". -->

## Perché questo perimetro

<!-- Una riga per le scelte non ovvie di scope e must_not_change. -->

## Note di negoziazione

<!-- Round agente/umano: cosa è stato proposto, cosa è stato cambiato e perché. -->
