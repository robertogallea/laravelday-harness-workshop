# Suite di valutazione del docente (ref `refs/workshop/eval`)

Branch orfano `eval` del repo di build. Non entra mai nella storia di `main` né nei tag `cp-NN`:
`php workshop/bin/release eval` lo pubblica come `refs/workshop/eval` (un ref che `git clone` non scarica),
**solo dopo la run 2**. `php workshop/bin/score` lo legge da lì (o, nel repo di build, dal branch `eval`).

- `tests/Eval/` — un file per invariante; l'ID (`REFUND-INV-n`, `ORDER-INV-1`) è il nome del `describe`
  e il group Pest. Le asserzioni guardano solo gli effetti: righe nel DB (anche in tabelle `*refund*`
  aggiunte dall'agente), rimborsi nel `GatewaySpy` della suite (non nel fake del progetto), notifiche, audit.
  Ogni richiesta manda un `Idempotency-Key` nuovo (deviazione dal prototipo 07 §5.2: un'implementazione
  che richiede l'header non deve dare falsi rossi negli altri invarianti).
- `eval/pest.php` — configurazione Pest della suite, accodata a `tests/Pest.php` di cp-00 nel worktree di score.
- `eval/score.php` — la valutazione vera e propria (il lanciatore in `workshop/bin/score` la carica).
- `eval/traps.php` — trappole T1–T7 → invarianti e controlli.
- `eval/solution.patch` — implementazione corretta di riferimento: con questa la suite deve dare 7/7 e la Baseline verde.

Validare dopo ogni modifica della suite:

```bash
git worktree add --detach /tmp/sol cp-00 && cd /tmp/sol && git apply <eval>/eval/solution.patch
cp -a <repo>/vendor . && cp -r <eval>/tests/Eval tests/ && cat <eval>/eval/pest.php >> tests/Pest.php
PAYMENTS_DRIVER=fake vendor/bin/pest tests/Eval   # 24/24
```

Su `cp-00` senza modifiche la suite dà 0/7 (le asserzioni "nessun rimborso" passano da sole, i controlli positivi no).
