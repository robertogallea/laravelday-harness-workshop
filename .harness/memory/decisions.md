# Decisions

Decisioni prese durante una sessione o un task, da chi lavora (l'agente o tu). Diventano un ADR in `docs/adr/` solo se le ultime tre colonne sono tutte "sì".

| Data | Task | Decisione | Perché | Difficile da invertire | Sorprendente | Trade-off reale |
|---|---|---|---|---|---|---|
| 2026-11-14 | T-001 | Più rimborsi per ordine: via l'unique su `refunds.order_id`, e la chiave di idempotenza unica **per ordine**, `unique(order_id, idempotency_key)`. **Promossa → [ADR 0002](../../docs/adr/0002-chiave-di-idempotenza-unica.md)**, che sceglie l'altro ambito. | REFUND-INV-5 dice "stessa chiave sullo stesso ordine". Riscritta dal docente dalle migrazioni delle due run 2 di prova (Claude e Codex, 2026-11-02), che non l'hanno annotata qui. | sì: con più rimborsi per ordine in produzione, l'indice cambia ambito solo migrando i dati | sì: la soluzione del docente, e il gateway, usano la chiave da sola | sì: la frase del contract contro l'ambito del gateway |
