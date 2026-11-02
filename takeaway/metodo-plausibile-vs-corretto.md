# Metodo: modifica plausibile o modifica corretta?

Una pagina, da tenere accanto a [criteri-verifica.md](criteri-verifica.md).

## La distinzione

- **Plausibile**: compila, i test scritti dall'agente sono verdi, somiglia al codice che ha intorno, e il riassunto convince.
- **Corretta**: rispetta gli **invarianti** e la **convenzione dichiarata** del progetto, e lo dimostrano evidenze prodotte dal sistema, non dichiarazioni dell'agente.

Una modifica è corretta solo **rispetto a qualcosa di scritto**. Se l'invariante non è scritto da nessuna parte, la domanda "è corretta?" non ha risposta, e quel vuoto è il pezzo di harness che manca.

Quasi sempre la modifica plausibile è quella che **imita il codice più vicino**. Nel workshop, la run 1 estende il `RefundService` legacy e ne copia `isStaff()`, la chiave `refund-{id}` e l'assenza di transazione. Tutto regge per un rimborso totale, niente regge il parziale.

## Il metodo in sei passi

1. **Nomina l'operazione e trova il contract.** Quale operazione di business tocca il diff? Esiste un Behavioral Contract? Se manca, scrivi gli invarianti in 5 minuti **prima** di leggere il codice: sono il criterio di giudizio, non si deducono dal diff.
2. **Butta il riassunto, tieni il receipt.** Separa ciò che è **dimostrato** (`[D]`: test eseguiti dallo script, diff, esiti dei comandi) da ciò che è **dichiarato** (`[A]`: "ho gestito l'idempotenza"). Tutto ciò che è solo `[A]` vale come "non dimostrato".
3. **Chiedi da dove l'ha copiato.** Per ogni blocco nuovo, trova il codice che l'agente ha imitato. Se la fonte è in Conflicts (legacy, eccezione nota), il blocco è sospetto finché non provi il contrario.
4. **Invariante per invariante: c'è un test, e morde?** Per ogni ID serve un test nel suo group, verde. Poi la prova di sensibilità: togli la riga che dovrebbe garantirlo (la transazione, l'`authorize`, il controllo della somma) e verifica che il test diventi rosso. Un test che resta verde non protegge niente.
5. **Passa i 5 criteri sui confini.** Applica [criteri-verifica.md](criteri-verifica.md) ai punti del diff che attraversano un confine: database, rete, permessi, eventi e code, dati esistenti.
6. **Dai un verdetto a tre valori.**
   - **Corretta**: ogni invariante coperto da un test che morde, nessun segnale d'allarme nei criteri.
   - **Plausibile, non dimostrata**: niente di sbagliato in vista, ma mancano evidenze. Si aggiunge l'evidenza (un test, una riga di contract) prima del merge.
   - **Sbagliata**: un invariante violato o un segnale d'allarme confermato. Si scrive una **Lesson**: quale pezzo dell'harness l'avrebbe fermata?

## Promemoria in tre righe

- Non chiedere "funziona?", chiedi "**quale invariante dimostra?**".
- Non chiedere "i test passano?", chiedi "**fallirebbero senza questa riga?**".
- Non chiedere "è fatto bene?", chiedi "**da quale codice l'ha copiato, e quel codice vale per questo caso?**".

---

<sub>© 2026 Roberto Gallea · [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/deed.it) · dal workshop "Costruire un harness per coding agent affidabili in Laravel", Laravel Day 2026.</sub>
