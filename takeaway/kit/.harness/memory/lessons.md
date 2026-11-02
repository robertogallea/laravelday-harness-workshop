# Lessons

<!-- Scritte dall'umano dopo un fallimento dell'agente. Ogni lesson produce un pezzo di harness (hook, regola, test). -->

Regola anti-decay: ogni pezzo di harness risponde a "quale fallimento previene?". Una lesson senza pezzo è solo una nota; un pezzo il cui fallimento non si presenta più si rimuove quando scatta la sua condizione di rimozione. "Ultima volta che è scattato" si legge dal trace: `grep '"rule":"<id>"' .harness/runs/*/trace.jsonl | tail -1`.

## L-NNN — <!-- titolo breve -->

- **Fallimento osservato:** <!-- run, cosa ha fatto l'agente, quale danno -->
- **Fallimento prevenuto:** <!-- la classe di errori, non il singolo episodio -->
- **Pezzo di harness:** <!-- hook / regola in AGENTS.md / test, con percorso; per una regola del guard, l'id fra backtick e `// lesson: L-NNN` sopra la chiave -->
- **Quando rimuoverlo:** <!-- condizione osservabile -->
- **Ultima volta che è scattato:** <!-- data, dal trace -->
