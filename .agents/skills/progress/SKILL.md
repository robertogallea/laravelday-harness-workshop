---
name: progress
description: Aggiorna .harness/memory/progress.md con lo stato del task attivo. Usala prima di chiudere una sessione di lavoro su un Task Contract, o quando ti fermi con [ATTESA-UMANO].
---

# Aggiornare Progress

`.harness/memory/progress.md` è la memoria fra le sessioni: si **sovrascrive**, non si accoda. La prossima sessione parte da qui.

1. Nel frontmatter: `task:` è l'id del Task Contract approvato su cui lavori; `updated:` è l'ora attuale (`YYYY-MM-DDTHH:MM`).
2. Riscrivi le quattro sezioni, brevi e verificabili:
   - `## Stato` — cosa è fatto, con gli invarianti già coperti da test verdi;
   - `## Prossimo passo` — la prima cosa da fare alla ripresa;
   - `## Conflicts incontrati` — divergenze dalla convenzione dichiarata viste nel codice (anche quelle nuove, non in `AGENTS.md`);
   - `## Rischi aperti` — cosa non hai potuto verificare.
3. Scrivi solo quello che hai visto o eseguito. Il Change Receipt riporta Conflicts incontrati e Rischi aperti come **dichiarati** [A], separati da ciò che il sistema dimostra [D].
