---
name: task-contract
description: Abbozza un Task Contract (.harness/tasks/T-NNN.md, status draft) a partire dalla richiesta dell'utente. Usala prima di modificare app/, database/, routes/ o config/ quando non c'è un contract approvato, o quando l'harness nega una scrittura con [harness:task.no-contract].
---

# Abbozzare un Task Contract

Senza un Task Contract approvato l'harness nega le scritture in `app/`, `database/`, `routes/` e `config/`. Il contract lo abbozzi tu; lo approva l'umano.

1. Leggi la richiesta, `AGENTS.md` (Conflicts e Pitfalls) e il Behavioral Contract dell'operazione da `docs/contracts/INDEX.md`.
2. Scegli il primo numero libero in `.harness/tasks/` e scrivi `.harness/tasks/T-NNN.md` con questo frontmatter:

   ```yaml
   ---
   id: T-NNN
   status: draft               # lo porta ad approved solo l'umano
   scope:
     paths:                    # i soli path che il task scrive (glob con ** e *)
       - app/Actions/<Area>/**
   must_not_change:            # cosa resta intatto anche se è "comodo" cambiarlo
       - .env*
   evidence:                   # i comandi che dimostrano il risultato: un --group per invariante
     - vendor/bin/pest --group=<ID-INV-1>
     - vendor/bin/pest
   approval_required:          # cosa chiedi all'umano prima di farlo
     - dipendenze composer nuove
   ---
   ```

3. Nel corpo: `## Risultato atteso` (cosa cambia per l'utente, con il contract di riferimento) e `## Perché questo perimetro` (una riga per ogni voce di `must_not_change`).
4. Non scrivere mai `status: approved`: l'harness lo nega. Chiudi il messaggio con `[ATTESA-UMANO]: approvi T-NNN?` e aspetta.
5. Un contract approvato non si modifica: se lo scope non basta, proponi la modifica in chat e aspetta l'umano.
