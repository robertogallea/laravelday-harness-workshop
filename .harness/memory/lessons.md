# Lessons

Scritte dall'umano dopo un fallimento dell'agente. Ogni Lesson produce un pezzo di harness (hook, regola, test).

Regola anti-decay: ogni pezzo di harness risponde a "quale fallimento previene?". Una Lesson senza pezzo è solo una nota. Un pezzo il cui fallimento non si presenta più si rimuove quando scatta la sua condizione di rimozione. "Ultima volta che è scattato" si legge dal trace: `grep '"rule":"<id>"' .harness/runs/*/trace.jsonl | tail -1`.

## L-001 — "Fatto" senza test

- **Fallimento osservato:** run 1, l'agente dichiara il rimborso parziale completato senza un test che provi gli invarianti; `support` riesce a rimborsare (REFUND-INV-1).
- **Fallimento prevenuto:** chiudere un task con test rossi, o senza test per un invariante del contract.
- **Pezzo di harness:** stop `.harness/bin/stop` (blocca il "fatto" se il receipt è rosso) + `evidence` del Task Contract.
- **Quando rimuoverlo:** mai, finché l'agente può dichiarare "fatto"; da rivedere se la CI blocca già il merge con lo stesso criterio.
- **Ultima volta che è scattato:** —

## L-002 — Migrazione distruttiva su `refunds.amount`

- **Fallimento osservato:** run 1, l'agente "normalizza" `refunds.amount` in centesimi riscrivendo la colonna (`->change()`) o la migrazione esistente.
- **Fallimento prevenuto:** perdita o troncamento di dati monetari già in produzione.
- **Pezzo di harness:** pre-tool, regole `migration.edit-existing` e `migration.destructive` in `.harness/rules/guard.php` + Pitfall in AGENTS.md.
- **Quando rimuoverlo:** quando il progetto ha un controllo equivalente in CI (per esempio un lint delle migrazioni).
- **Ultima volta che è scattato:** —
