# Scheda di porting: il primo pezzo di harness sul tuo progetto

Blocco finale del workshop, 35 minuti (si adatta da 30 a 40). È fuori dal percorso dei checkpoint: si lavora sul **tuo** repository.

**L'obiettivo non è portare l'harness del workshop sul tuo progetto.** L'obiettivo è uscire con **un fallimento reale**, **il pezzo più piccolo** che lo elimina, già scritto su un branch, e un piano per lunedì.

Funziona con Laravel 8 o successivo, con Pest o PHPUnit, senza necromancer, con Claude Code o con Codex.

| Fase | Tempo | Risultato |
|---|---|---|
| 0. Preparazione | 3' | branch `harness/porting`, `docs/harness-porting.md` copiato |
| 1. Un fallimento reale | 7' | la tabella del fallimento compilata |
| 2. Il pezzo più piccolo | 3' | un pezzo scelto, con ID della checklist e livello |
| 3. I primi tre passi | 17' | AGENTS.md minimo, un contract con un test, un guardrail |
| 4. A coppie, poi lunedì | 5' | review incrociata con i 5 criteri; il piano scritto |

Dopo il workshop, la [sezione 5](#5-dopo-lunedì-i-pezzi-che-verificano-da-soli) porta i pezzi che verificano da soli: post-edit, receipt, session-start e stop.

**Se il tempo stringe** (porting a 30'): il passo 3 si ferma a 3.2, e il passo 3.3 diventa la prima voce di lunedì.

**Non hai un progetto a disposizione** (laptop aziendale senza codice, NDA, progetto non Laravel)? Due strade: in coppia con chi ce l'ha, da navigatore (compili tu la tabella del fallimento e fai tu la review con i criteri); oppure lo [scenario di riserva](scenario-riserva.md), sul repo del workshop.

---

## 0. Preparazione (3')

Nel tuo progetto, con `W` = la cartella del repo del workshop:

```bash
W=~/laravel-harness                        # dove hai clonato il repo del workshop
git switch -c harness/porting
mkdir -p docs && cp "$W/takeaway/kit/docs/harness-porting.md" docs/
php artisan --version                      # Laravel ___
php -v | head -1                           # PHP ___
vendor/bin/pest --version 2>/dev/null || vendor/bin/phpunit --version
```

- Agente che userai: ☐ Claude Code ☐ Codex
- File di istruzioni che esistono già: ☐ CLAUDE.md ☐ AGENTS.md ☐ `.cursor/rules` o altri ☐ nessuno
- Il kit è in `$W/takeaway/kit/`. **Copia un file solo quando un passo lo chiede.**

Le risposte di questa scheda vanno in `docs/harness-porting.md`, **nel tuo progetto**: finiscono nella PR insieme al pezzo, e chi la rivede sa quale fallimento previene.

**Con il wizard.** Le fasi 0–4 si fanno anche con `bash "$W/takeaway/bin/porting"`, lanciato dalla root del tuo progetto (Linux e macOS; su Windows da Git Bash o WSL). Il wizard fa i collegamenti: il branch, i file nel posto giusto, `@contract` nel docblock, il group del test, `harness:check` con gli strumenti che hai, un commit per passo e `verify-port` alla fine. Il contenuto resta tuo: il fallimento, la scelta del pezzo, Conflicts e Pitfalls, le invarianti, il corpo del test, la review. Con Ctrl-C ti fermi, e al giro dopo riparti da dove eri. Le tappe hanno i numeri di questa scheda, che resta il riferimento.

---

## 1. Parti da un fallimento reale (7')

Pensa all'ultima volta in cui un agente (o un collega nuovo) ha fatto, nel tuo progetto, qualcosa di **plausibile ma sbagliato**. Scrivi un caso solo, nella prima tabella di `docs/harness-porting.md`:

| Domanda | Risposta |
|---|---|
| Che task era? | |
| Cosa ha fatto l'agente? | |
| Cosa avrebbe dovuto fare, e dove era scritto (se lo era)? | |
| Quale criterio ha violato? (autorizzazione · transazioni · tracciabilità · idempotenza · test · altro) | |
| Da quale codice ha copiato? | |
| Danno reale o evitato per un pelo? | |

**Non ti viene in mente un caso?** Provocane uno in 5 minuti, in sola lettura (Claude Code: modalità plan; Codex: chiedigli di non modificare niente, oppure `codex --sandbox read-only` se la sandbox parte sulla tua macchina: su Ubuntu 24 e successivi, con AppArmor di default, non parte):

> Devo aggiungere <una piccola feature realistica del tuo dominio>. Senza modificare nulla, dimmi: quali file toccheresti, dove si decide l'autorizzazione, come lo testeresti senza rete, e quali vincoli del progetto devo rispettare.

Ogni risposta in cui l'agente **indovina** (cita il codice vicino invece della regola, o dà per buona una cosa che è un'eccezione) è il tuo fallimento.

---

## 2. Scegli il pezzo più piccolo (3')

*Costruisci lo strato più piccolo che elimina il fallimento che hai davvero.* Parti dalla riga che descrive il tuo fallimento e scegli **un** pezzo:

| Se l'agente… | Pezzo minimo | Checklist | Liv. |
|---|---|---|---|
| ha seguito il codice vecchio invece della convenzione | una riga in AGENTS.md → Conflicts | LEG-2 | L2 |
| non ha trovato dove si decide qualcosa (authz, config, test) | una riga in Topology o in Commands | LEG-1, DIS-1 | L2 |
| ha violato una regola di business | un Behavioral Contract con 1–3 invarianti e un test per ID | CTX-3, VER-1 | L2 · L5 |
| ha toccato file che non doveva | un Task Contract con `must_not_change` (il pre-hook lo applicherà più avanti) | CTX-5, VIN-3 | L1 |
| ha letto segreti o lanciato comandi distruttivi | un deny nativo su Claude Code, il pre-hook su Codex | VIN-1, VIN-2 | L3 |
| ha detto "fatto" senza prove | un comando "fatto" in Commands, poi lo stop hook | VER-2 | L5 |
| ha perso il filo fra una sessione e l'altra | `progress.md` più un puntatore in AGENTS.md | CTX-6 | L4 |
| ha ripetuto un errore già visto | una Lesson che produce un Pitfall o una regola | FB-5 | L6 |

Scrivilo nel piano: pezzo, ID della checklist, livello.

---

## 3. I primi tre passi (17')

Fai i passi in ordine. Ognuno ha un criterio di **fatto**. Se il pezzo scelto alla fase 2 coincide con uno dei passi, dedica a quello più tempo e salta gli altri.

### 3.1 AGENTS.md minimo (6')

1. Copia `$W/takeaway/kit/AGENTS.md` nella root. Se esiste già un CLAUDE.md, sposta in AGENTS.md ciò che serve e lascia in CLAUDE.md solo la riga `@AGENTS.md` (più eventuali note specifiche di Claude). Codex legge AGENTS.md da solo.
2. Compila **solo** tre sezioni:
   - **Commands**: come si lanciano i test, Pint e PHPStan, e i comandi di discovery che funzionano sul tuo progetto (tabella qui sotto);
   - **Conflicts**: da una a tre righe, a partire dal fallimento della fase 1;
   - **Pitfalls**: una riga, scritta in positivo ("fai X"), legata al fallimento.
3. Lascia vuote Topology ed Environment (le intestazioni restano), oppure scrivi una riga ciascuna se ti viene subito.

**Con Laravel Boost** (AGENTS.md comincia con `<laravel-boost-guidelines>`), AGENTS.md e CLAUDE.md sono generati, e una modifica a mano sparisce alla prossima rigenerazione. Non toccarli: copia `$W/takeaway/kit/AGENTS.md` da `## Topology` in giù in `.ai/guidelines/harness.md`, compila lì le tre sezioni, poi rigenera solo le istruzioni:

```bash
php artisan boost:update --no-discover --ignore-skills    # riscrive AGENTS.md e CLAUDE.md, non le skill
```

Boost mette le tue sezioni in cima a tutti e due i file (provato con Boost 2.10). Se esisteva un AGENTS.md scritto a mano prima di Boost, le parti utili vanno in `.ai/guidelines/` anche loro.

**Lo skeleton di Laravel 13** ha già AGENTS.md e CLAUDE.md nel formato di Boost, ma senza `boost.json`: lì `boost:update` rifiuta di partire ("Please set up Boost…"). Configura Boost una volta, solo per le linee guida:

```bash
php artisan boost:install --guidelines --no-interaction
```

Senza domande, Boost 2.10 non registra gli agenti in `boost.json`, e il `boost:update` successivo rifiuta di nuovo: aggiungi `"agents": ["claude_code", "codex"]` a `boost.json` (il wizard lo fa da solo). Se avevi installato le skill, con `--ignore-skills` Boost toglie da AGENTS.md il paragrafo "Skills Activation" (provato con Boost 2.10.4): le skill restano, ma l'agente non è più invitato ad attivarle. Guarda il diff prima del commit.

**Discovery: il primo gradino è sempre nativo.**

| Il tuo progetto | Comandi di discovery |
|---|---|
| Laravel 9 e successivi | `php artisan about`, `route:list --path=…`, `model:show <Model>`, `db:show`, `db:table <tabella>` (con `php artisan list` controlli quali ci sono nella tua versione) |
| Laravel 8 | `route:list`; per modelli e schema, le migrazioni, `php artisan schema:dump` e la lettura dei model |
| Laravel 13 con necromancer | in più project map, MCP e `affected-tests` per i test mirati: è il secondo gradino |
| Laravel Boost installato | i suoi tool MCP sono un'alternativa al secondo gradino: scegline uno, non due per lo stesso scopo; le istruzioni vanno in `.ai/guidelines/` (sopra) |

**Fatto quando:** in una sessione nuova, alla domanda "dove si decide <la cosa del fallimento>?", l'agente risponde citando la riga di Conflicts.

### 3.2 Un Behavioral Contract con un test che morde (7')

1. Copia `$W/takeaway/kit/docs/contracts/_template.md` in `docs/contracts/<operazione>.md`, e `INDEX.md` accanto, con una riga.
2. Compila solo **Invarianti** (da 1 a 3, con ID `PREFISSO-INV-n`) e **Non-goals**. Il resto può aspettare.
3. Aggiungi `@contract docs/contracts/<operazione>.md` al docblock della classe che implementa l'operazione (Action, Service, Controller: va bene quella che c'è).
4. Scrivi **un** test per l'invariante più vicino al fallimento, e taggalo con il suo ID:
   - Pest: `->group('X-INV-1')`
   - PHPUnit 10 o successivo: `#[Group('X-INV-1')]`
   - PHPUnit 9 o precedente: `@group X-INV-1` nel docblock
5. Prova di sensibilità: commenta la riga che garantisce l'invariante e verifica che il test diventi rosso.

**Fatto quando:** `vendor/bin/pest --group=X-INV-1` (o `vendor/bin/phpunit --group X-INV-1`) è verde, ed è rosso senza la riga che garantisce l'invariante. Con più ID, un `--group` per ID: con PHPUnit 12 `--group=A,B` non trova niente.

### 3.3 Un guardrail deterministico (4')

Scegli il guardrail **nativo** prima dello script. Se il tuo fallimento non riguarda segreti o comandi distruttivi, salta al comando "fatto".

**Claude Code**: `.claude/settings.json`

```json
{
  "permissions": {
    "deny": ["Read(./.env)", "Edit(./.env)", "Bash(php artisan migrate:fresh*)", "Bash(php artisan db:wipe*)"]
  }
}
```

Il deny ferma il tool Read e `cat .env`, non `php -r 'echo file_get_contents(".env");'`: per quello serve il pre-hook.

**Codex**: nel workshop l'unica guardia verificata è il pre-hook (sotto). Le `rules` con `forbidden` confrontano il prefisso letterale del comando (`php ./artisan` le aggira), e i permission profile richiedono la sandbox di Codex, che su Ubuntu 24 e successivi non parte con le impostazioni di default di AppArmor: provali sulla tua macchina prima di fidarti.

**Comando "fatto"** (ogni versione, ogni agente): in `composer.json`

```json
"scripts": {
  "harness:check": [
    "vendor/bin/pint --test --dirty",
    "vendor/bin/phpstan analyse --no-progress",
    "vendor/bin/pest --group=X-INV-1"
  ]
}
```

e in AGENTS.md → Commands: `Prima di dire "fatto": composer harness:check, deve essere verde.` Togli le righe degli strumenti che non hai. `pint --dirty` richiede un repo git.

**Se ti serve il pre-hook** (segreti anche via shell, migrazioni, perimetro del task): copia dal repo del workshop quattro file, e riscrivi solo gli ultimi due.

```bash
mkdir -p .harness/bin .harness/lib .harness/rules
cp "$W/.harness/bin/pre-tool"     .harness/bin/      # impianto: non si tocca
cp "$W/.harness/lib/harness.php"  .harness/lib/      # impianto: non si tocca
cp "$W/.harness/config.php"       .harness/          # le manopole: gated_paths, harness_paths, …
cp "$W/.harness/rules/guard.php"  .harness/rules/    # la policy: tieni SOLO la regola del tuo fallimento
printf '/.harness/state/\n/.harness/runs/\n' >> .gitignore
```

In `rules/guard.php` ogni regola è una closure con l'id come chiave (`'env.access' => function (…)`): cancella tutte le altre, comprese le `task.*` (senza un Task Contract bloccherebbero ogni scrittura in `app/`), e riscrivi il messaggio della regola che resta: l'agente lo legge per sapere cosa fare invece, e quello del workshop cita `fakePayments()`. Poi l'adattatore, copiato dal repo del workshop:
- Claude Code: il blocco `PreToolUse` di `$W/.claude/settings.json`, con `"onFailure": "block"`;
- Codex: il blocco `PreToolUse` di `$W/.codex/hooks.json`, poi `/hooks` per approvarlo (il progetto deve essere trusted).

Lo script è PHP puro e non fa il bootstrap di Laravel, quindi non dipende dalla versione di Laravel; dipende dal `php` che l'hook chiama, che deve essere **8.1 o successivo** (provato con 8.3, quello del workshop). Con PHP 7.4 o 8.0 il guard non si carica, fallisce chiuso e blocca ogni azione: se nel PATH del progetto c'è un PHP più vecchio, scrivi nell'adattatore il path assoluto di un PHP recente (per esempio `/usr/bin/php8.3`). Prova la regola senza l'agente:

```bash
echo '{"session_id":"prova","cwd":"'"$PWD"'","hook_event_name":"PreToolUse","tool_name":"Bash","tool_input":{"command":"cat .env"}}' \
  | php .harness/bin/pre-tool --agent=claude
# → "permissionDecision":"deny" con [harness:env.access]
```

**Fatto quando:** chiedi all'agente la cosa vietata (es. "mostrami il contenuto di `.env`") e viene bloccato; oppure `composer harness:check` è verde.

### Controllo della struttura

```bash
php "$W/takeaway/bin/verify-port" .
```

Guarda solo i collegamenti, in sola lettura: le sezioni di AGENTS.md e la riga `@AGENTS.md` in CLAUDE.md, ogni `@contract` che punta a un file che c'è, ogni invariante con un test nel suo group, i JSON degli adattatori, i file che servono al pre-hook (se ne manca uno, il guard fallisce chiuso e blocca tutto), il branch e il piano. Un pezzo che non hai scelto è SKIP. Non lancia i tuoi test: il "fatto quando" dei passi resta a te.

---

## 4. A coppie, poi lunedì (5')

**A coppie (3').** Racconta all'altro il fallimento e il pezzo che hai scelto. L'altro apre il tuo diff (`git diff main...harness/porting`) e applica i 5 criteri di [criteri-verifica.md](criteri-verifica.md): per ogni criterio un esito, ✅, ❌ o "non dimostrato". Poi vi scambiate.

**Lunedì (2').** Compila in fondo a `docs/harness-porting.md`:

- [ ] **PR del branch `harness/porting`** aperta e assegnata a: ______ (AGENTS.md si rivede in team: è la convenzione dichiarata di tutti).
- [ ] **Primo task reale con un Task Contract**: ______ (copia `$W/takeaway/kit/.harness/tasks/T-NNN.md`; fai abbozzare il contract all'agente, e approvalo tu).
- [ ] **Prima review con i 5 criteri** sul diff di quel task, con il verdetto a tre valori ([metodo-plausibile-vs-corretto.md](metodo-plausibile-vs-corretto.md)).
- [ ] **Prima Lesson** al primo fallimento nuovo, in `.harness/memory/lessons.md`, con la condizione di rimozione.
- [ ] **Promemoria fra 4 settimane** per le domande contro il decay (in fondo a [checklist.md](checklist.md)): data ______.

**Da non fare lunedì:**
- copiare tutta la cartella `.harness/` del workshop;
- scrivere un contract per ogni operazione;
- aggiungere un hook per un fallimento che non hai ancora visto.

Ogni pezzo deve rispondere a "quale fallimento previene?".

---

## 5. Dopo lunedì: i pezzi che verificano da soli

Fuori dal tempo del workshop. Le fasi 0–4 lasciano un AGENTS.md, un contract con un test e al più un guardrail. Gli altri pezzi della giornata (il feedback dopo ogni modifica, il Change Receipt, la ripresa fra sessioni, lo stop) arrivano **solo quando il loro fallimento si presenta** nel tuo progetto: vale la regola di "Da non fare lunedì".

| Se l'agente… | Pezzo | Checklist | Liv. | Richiede |
|---|---|---|---|---|
| scopre errori di tipo o di stile solo alla fine | 5.1 post-edit | FB-1, CAP-3 | L5 | l'impianto (5.0) |
| dice "fatto", e in review ti resta solo il suo riassunto | 5.2 receipt a mano | VER-3, VER-4 | L5 | Task Contract approvato, `progress.md` |
| riparte da zero in una sessione nuova | 5.3 session-start | CTX-6 | L4 | `progress.md` |
| dice "fatto" con i test rossi | 5.4 stop | VER-2, FB-4 | L5 | 5.2 e 5.3 |

L'ordine conta: lo stop esegue la stessa verifica del receipt, e misura il lavoro della sessione a partire da com'era il repo quando il session-start l'ha aperta. `verify-port` non controlla questi pezzi.

**Committa i file dell'harness prima di usarli.** Receipt e stop misurano il diff da un commit base: se `.harness/` o `.gitignore` sono nel diff, risultano fuori da `scope.paths` e il receipt è rosso (`scope_violation`).

**Ogni pezzo si prova due volte.** Prima da terminale, con il payload a mano (il **fatto quando** di ogni passo). Poi in una sessione vera, perché la prova da terminale salta l'adattatore, e i quattro hook falliscono aperti: un blocco sbagliato in `.claude/settings.json`, o su Codex un hook non approvato in `/hooks`, non dà nessun errore. Dopo la sessione cerca il suo evento nel trace:

```bash
grep -h '"event":"post_edit.checked"' .harness/runs/*/trace.jsonl | grep -v '"session":"prova' | tail -1   # 5.3: session.started · 5.4: stop.
```

Dopo ogni prova da terminale, `rm -rf .harness/state .harness/runs`: lo stato delle sessioni di prova resta lì, e il receipt senza `--base` parte dall'ultima sessione che trova, anche se è una prova.

### 5.0 L'impianto comune

I quattro script caricano gli stessi due file e le stesse manopole. Come il pre-hook, girano con il PHP dell'host, **8.1 o successivo** (provato con 8.3), e non fanno il bootstrap di Laravel:

```bash
mkdir -p .harness/bin .harness/lib .harness/rules .harness/memory
cp "$W/.harness/lib/harness.php" "$W/.harness/lib/receipt.php" .harness/lib/   # impianto: non si tocca
[ -f .harness/config.php ] || cp "$W/.harness/config.php" .harness/             # dal pre-hook? tieni il tuo
grep -qx '/.harness/state/' .gitignore || printf '/.harness/state/\n/.harness/runs/\n' >> .gitignore
```

In `config.php` riscrivi:
- **`tools`**: senza necromancer togli `scan` e `affected`, e i test mirati passano subito alla convenzione di path (`app/**/X.php` → `tests/**/XTest.php`, i test cambiati → se stessi; niente di mirato → la suite completa). Se li lasci, a ogni verifica l'app si avvia due volte per niente. Con PHPUnit al posto di Pest: `'pest' => ['php', 'vendor/bin/phpunit']`, che riceve gli stessi argomenti (file di test, `--group=<ID>`, `--log-junit`); nel workshop non è stato provato. Uno strumento che non hai (`vendor/bin/…` assente) risulta "non disponibile", non rosso.
- **`gated_paths`**: le cartelle del tuo codice. Il session-start le cita quando non c'è un Task Contract approvato.
- **`harness_paths`** (lo legge solo il pre-hook): togli `workshop/**`, `.harness/checkpoints/**` e `.harness/fixtures/**`, che esistono solo nel repo del workshop.
- **`timeouts`**: `tests` (300 s) vale per ogni comando di test, `stop` (540 s) per tutta la verifica, sotto i 600 s dell'adattatore. Una modifica senza test mirati lancia la suite completa: se supera `tests` la verifica è rossa per timeout, se supera `stop` lo stop lascia chiudere con un receipt NON VERIFICATO. Con una suite lenta alza i tre valori insieme.

### 5.1 Post-edit: Pint e PHPStan dopo ogni modifica

```bash
cp "$W/.harness/bin/post-edit" .harness/bin/
cp "$W/.harness/rules/post-edit.php" .harness/rules/
```

In `rules/post-edit.php`, `commandsFor` sceglie i file da controllare: la regex `#^(app|database|tests)/.+\.php$#` deve coprire gli stessi path di `paths` in `phpstan.neon`. `feedback` decide cosa torna all'agente, e di solito va bene così. Il post-edit informa e basta: un suo errore non blocca mai l'editing, e Pint **riformatta** il file appena scritto.

Adattatore: il blocco `PostToolUse` di `$W/.claude/settings.json` (Claude Code) o di `$W/.codex/hooks.json` (Codex, poi `/hooks` per approvarlo). Prova senza l'agente, dopo aver messo un errore di tipo in un file:

```bash
F=app/<un file>.php
echo '{"session_id":"prova-edit","cwd":"'"$PWD"'","hook_event_name":"PostToolUse","tool_name":"Edit","tool_input":{"file_path":"'"$PWD/$F"'"}}' \
  | php .harness/bin/post-edit --agent=claude
```

**Fatto quando:** l'output è un JSON con `additionalContext` che dice "PHPStan, app/…: 1 errore" e la riga; corretto l'errore, il comando non stampa niente.

### 5.2 Il Change Receipt a mano

```bash
cp "$W/.harness/bin/receipt" .harness/bin/
cp "$W/.harness/rules/receipt.php" .harness/rules/
cp "$W/takeaway/kit/.harness/memory/progress.md" .harness/memory/
```

Il receipt cerca quattro cose, e senza le prime tre è rosso o si ferma:
- **un solo Task Contract con `status: approved`** in `.harness/tasks/`. Le `evidence` sono comandi; quelli di test (`pest`, `phpunit`, `artisan test`) li lancia con `--log-junit` per contare test e rossi;
- **gli invarianti, con ID nella forma `PREFISSO-INV-n`** (maiuscole e trattini: `CANCEL-INV-1`). Li prende dai `--group=` delle evidence e dalle righe della tabella Invarianti dei contract citati con `@contract` dalle classi cambiate. Un invariante senza test nel suo group è "non coperta", e il receipt è rosso;
- **`.harness/memory/progress.md`** con `task:` uguale all'id del task attivo. "Conflicts incontrati" e "Rischi aperti" finiscono nel receipt come dichiarati `[A]`;
- **AGENTS.md → `## Conflicts`**: le righe che nominano fra backtick un path cambiato finiscono nel receipt come dimostrate `[D]`.

Nel `progress.md` copiato scrivi `task: T-001` (l'id del tuo task attivo). La skill `task-contract` (`$W/.agents/skills/task-contract/`) aiuta l'agente ad abbozzare il contract; se non hai portato le regole `task.*` del pre-hook, togli dalla skill le frasi su ciò che "l'harness nega".

```bash
git add .gitignore .harness && git commit -m "harness: receipt"
# … il lavoro del task, non committato …
php .harness/bin/receipt T-001 --base=HEAD    # il diff dal commit dell'harness; exit 0 verde, 1 rosso, 2 errore
```

Scrive `.harness/runs/receipt-<ora>/receipt.md`. Fuori da una sessione dell'agente non controlla che Progress sia aggiornato: lo fa lo stop.

**Fatto quando:** con la riga che garantisce l'invariante commentata il receipt è ROSSO, l'invariante è `rossa` e l'exit è 1; ripristinato il file (`git checkout -- <file>`), VERDE con exit 0.

Poi in AGENTS.md → Commands: `Prima di dire "fatto": php .harness/bin/receipt T-NNN --base=<commit>, deve essere verde.` Il commit è quello da cui parte il task, non `HEAD`: un commit dell'agente a metà task uscirebbe dal diff. Il `--base` serve finché non porti il session-start: senza, il receipt parte dall'ultima sessione in `.harness/state/`, che con il solo post-edit nasce **dopo** la prima scrittura, e quel file esce da PHPStan e dai test mirati.

### 5.3 Session-start: la sessione nuova riparte da Progress

```bash
cp "$W/.harness/bin/session-start" .harness/bin/
mkdir -p .agents/skills .claude/skills
cp -r "$W/.agents/skills/progress" .agents/skills/
ln -s ../../.agents/skills/progress .claude/skills/progress
```

A ogni avvio, `/clear` o compattazione mette nel contesto dell'agente il task attivo (scope, `must_not_change`, evidence), gli avvisi (ultimo receipt NON VERIFICATO o in escalation, Progress di un altro task) e `progress.md`. La skill `progress` insegna all'agente a riscriverlo prima di chiudere. Il puntatore in AGENTS.md → Topology (`a inizio sessione leggi progress.md`) resta: il contesto è un aiuto, non l'unica strada.

Adattatore: il blocco `SessionStart` dei due file. Codex lo esegue al primo messaggio, non all'apertura. Con un task approvato, il contesto annuncia già lo stop ("con un rosso non ti lascia dire fatto"): porta 5.4 subito dopo, o l'agente conta su un controllo che non c'è.

```bash
echo '{"session_id":"prova-ss","cwd":"'"$PWD"'","hook_event_name":"SessionStart","source":"startup"}' \
  | php .harness/bin/session-start --agent=claude
```

**Fatto quando:** l'output ha `additionalContext` con il task attivo e Progress. In una sessione vera, `.harness/state/heartbeat.json` ha una `session` che non è `prova-ss`, e alla domanda "da dove riparti?" l'agente cita il Prossimo passo di `progress.md`.

### 5.4 Stop: niente "fatto" con un receipt rosso

```bash
cp "$W/.harness/bin/stop" .harness/bin/
```

Adattatore: il blocco `Stop` dei due file (timeout 600 s). Prima di attivarlo, sappi che:
- **parte solo con un Task Contract approvato**: senza, lascia chiudere e scrive `stop.skipped` nel trace. Su un lavoro senza contract non verifica niente;
- **misura la sessione da com'era il repo al primo hook**: per questo arriva dopo il session-start. Se il primo hook a vedere la sessione è lo stop, il lavoro dell'agente risulta già fatto prima della sessione, e lo stop non ha niente da verificare;
- **vuole `progress.md` del task attivo, riscritto in questa sessione**: altrimenti è rosso (`progress_stale`). Per questo arriva dopo la skill `progress`;
- **riformatta con Pint** i file cambiati, prima di verificarli. Con il preset Laravel Pint toglie anche ciò che è diventato inutile, come le variabili nel `use` di una closure: dopo una prova in cui hai commentato una riga, ripristina il file con `git checkout -- <file>`, non togliendo il commento;
- **ha un budget di 3** (`retry_budget`): blocca al primo e al secondo rosso, e il messaggio è un'istruzione per l'agente; al terzo lascia chiudere con un'escalation per te. Con `[ATTESA-UMANO]: <domanda>` nell'ultimo messaggio non verifica niente, e il receipt dice IN ATTESA;
- **fallisce aperto**: un crash lascia chiudere, ma scrive un receipt NON VERIFICATO, e il session-start successivo lo segnala.

Prova senza l'agente, con la stessa sessione dall'inizio alla fine:

```bash
P='{"session_id":"prova-stop","cwd":"'"$PWD"'","hook_event_name":"%s","last_assistant_message":"Fatto."}'
printf "$P" SessionStart | php .harness/bin/session-start --agent=claude > /dev/null
# ora commenta la riga che garantisce l'invariante, e riscrivi progress.md (basta aggiornare updated:)
printf "$P" Stop | php .harness/bin/stop --agent=claude    # tre volte
# poi: git checkout -- <file>, una modifica qualsiasi nello scope, e ancora
printf "$P" Stop | php .harness/bin/stop --agent=claude
```

**Fatto quando:** con l'invariante rotta l'output è un `"decision":"block"` con "tentativo 1/3", poi "2/3", e al terzo arriva l'ESCALATION (`systemMessage`); con il file ripristinato lo stop risponde `{}` e il receipt in `.harness/runs/<run>/receipt.md` è VERDE. Con `progress.md` non riscritto nella sessione, il blocco dice "aggiorna .harness/memory/progress.md".

---

<sub>© 2026 Roberto Gallea · [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/deed.it) · dal workshop "Costruire un harness per coding agent affidabili in Laravel", Laravel Day 2026. Gli snippet di codice e di configurazione sono MIT, come il resto del repo.</sub>
