<?php

declare(strict_types=1);

/*
 * .harness/lib/harness.php — libreria condivisa degli hook dell'harness.
 *
 * PHP puro, zero dipendenze, nessun bootstrap di Laravel: un hook deve funzionare
 * anche quando l'app è rotta, e in meno di 100 ms. Un solo file, un solo require.
 *
 * Contiene: il payload normalizzato Claude Code / Codex (apply_patch compreso), la
 * root del progetto, i glob, il Task Contract (parser YAML minimo del frontmatter),
 * gli output degli hook, lo stato della sessione, il trace, i sottoprocessi con timeout
 * e i file cambiati nella sessione. La verifica dello stop e il receipt: lib/receipt.php.
 *
 * Variabili d'ambiente (le usa replay, per non sporcare il progetto):
 *   HARNESS_TASKS      cartella da cui leggere i Task Contract (default .harness/tasks)
 *   HARNESS_STATE_DIR  cartella dello stato di sessione         (default .harness/state)
 *   HARNESS_RUNS_DIR   cartella delle run e dei trace           (default .harness/runs)
 *   HARNESS_STOP_DRY   file JSON con una verifica precalcolata: lo stop non esegue niente
 */

namespace Harness;

use RuntimeException;
use Throwable;

// ---------------------------------------------------------------------------
// Payload normalizzato
// ---------------------------------------------------------------------------

/**
 * Un payload di hook, uguale per i due agenti.
 *
 *   tool   shell | read | write | patch | other
 *   files  [{path, op: read|write|delete}], path relativi alla root ('../…' se fuori)
 *   added  path => testo aggiunto (content di Write, new_string di Edit, righe '+' della patch)
 */
final class Payload
{
    public function __construct(
        public readonly string $agent,
        public readonly string $event,
        public readonly string $root,
        public readonly string $sessionId,
        public readonly string $tool,
        public readonly string $toolName,
        public readonly string $command,
        public readonly array $files,
        public readonly array $added,
        public readonly array $raw,
    ) {}

    /** Legge il JSON da stdin. --agent=claude|codex vince; altrimenti turn_id esiste solo in Codex. */
    public static function fromStdin(array $argv, string $root): self
    {
        $text = (string) stream_get_contents(STDIN);
        $in = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($in)) {
            throw new RuntimeException('il payload non è un oggetto JSON');
        }
        $agent = null;
        foreach ($argv as $a) {
            if (str_starts_with($a, '--agent=')) {
                $agent = substr($a, 8);
            }
        }

        return self::fromArray($in, $root, $agent);
    }

    public static function fromArray(array $in, string $root, ?string $agent = null): self
    {
        $agent ??= isset($in['turn_id']) ? 'codex' : 'claude';
        $cwd = is_string($in['cwd'] ?? null) && $in['cwd'] !== '' ? $in['cwd'] : $root;
        $name = is_string($in['tool_name'] ?? null) ? $in['tool_name'] : ''; // SessionStart e Stop non ce l'hanno
        $ti = is_array($in['tool_input'] ?? null) ? $in['tool_input'] : [];
        $str = fn (string $k) => is_string($ti[$k] ?? null) ? $ti[$k] : '';
        $command = is_array($ti['command'] ?? null) ? implode(' ', array_map('strval', $ti['command'])) : $str('command');
        $at = fn (string $p) => rel($root, $cwd, $p);

        $files = [];
        $added = [];
        switch ($name) {
            case 'Bash':
            case 'exec_command':
            case 'shell':
            case 'local_shell':
                $tool = 'shell';
                break;
            case 'Read':
                $tool = 'read';
                $files[] = ['path' => $at($str('file_path')), 'op' => 'read'];
                break;
            case 'Grep':
            case 'Glob':
                // Senza path cercano nella cwd: si guarda solo un path esplicito (e il glob, che può nominare un file).
                $tool = 'read';
                $base = $str('path');
                if ($base !== '') {
                    $files[] = ['path' => $at($base), 'op' => 'read'];
                }
                $glob = $name === 'Glob' ? $str('pattern') : $str('glob');
                if ($glob !== '') {
                    $files[] = ['path' => $at(($base !== '' ? rtrim($base, '/').'/' : '').$glob), 'op' => 'read'];
                }
                break;
            case 'Write':
            case 'Edit':
            case 'MultiEdit':
            case 'NotebookEdit':
                $tool = 'write';
                $path = $at($str('file_path') ?: $str('notebook_path'));
                $files[] = ['path' => $path, 'op' => 'write'];
                $added[$path] = match ($name) {
                    'Write' => $str('content'),
                    'Edit' => $str('new_string'),
                    'NotebookEdit' => $str('new_source'),
                    default => implode("\n", array_map(fn ($e) => (string) ($e['new_string'] ?? ''), is_array($ti['edits'] ?? null) ? $ti['edits'] : [])),
                };
                break;
            case 'apply_patch':
                $tool = 'patch';
                [$files, $added] = parse_patch($command !== '' ? $command : $str('patch'), $root, $cwd);
                break;
            default:
                $tool = 'other';
        }
        foreach ($files as $f) {
            if ($f['path'] === '') {
                throw new RuntimeException("{$name} senza un path leggibile");
            }
        }

        return new self($agent, (string) ($in['hook_event_name'] ?? ''), $root, (string) ($in['session_id'] ?? 'no-session'),
            $tool, $name, $tool === 'shell' ? $command : '', $files, $added, $in);
    }

    /** @return list<string> path scritti o cancellati */
    public function writes(): array
    {
        return array_values(array_unique(array_column(array_filter($this->files, fn ($f) => $f['op'] !== 'read'), 'path')));
    }

    /** @return list<string> tutti i path toccati, letture comprese */
    public function paths(): array
    {
        return array_values(array_unique(array_column($this->files, 'path')));
    }
}

/**
 * I file di una patch di Codex: `*** Add|Update|Delete File: P` e `*** Move to: P`.
 * Una patch può toccare più file. I path possono essere relativi (alla cwd) o assoluti.
 *
 * @return array{0: list<array{path: string, op: string}>, 1: array<string, string>}
 */
function parse_patch(string $patch, string $root, string $cwd): array
{
    $files = [];
    $added = [];
    $cur = null;
    foreach (preg_split('/\R/', $patch) ?: [] as $line) {
        if (preg_match('/^\*\*\* (Add|Update|Delete) File: (.+)$/', $line, $m) || preg_match('/^\*\*\* (Move) to: (.+)$/', $line, $m)) {
            $cur = rel($root, $cwd, trim($m[2]));
            $files[] = ['path' => $cur, 'op' => $m[1] === 'Delete' ? 'delete' : 'write'];
            $added[$cur] ??= '';
        } elseif ($cur !== null && str_starts_with($line, '+')) {
            $added[$cur] .= substr($line, 1)."\n";
        }
    }

    return [$files, $added];
}

// ---------------------------------------------------------------------------
// Root e path
// ---------------------------------------------------------------------------

/** Risale da $start fino alla cartella che contiene .harness/ (gli script la passano da __DIR__). */
function root_from(string $start): string
{
    for ($d = rtrim($start, '/'); $d !== '' && $d !== '/' && $d !== '.'; $d = dirname($d)) {
        if (is_dir($d.'/.harness')) {
            return $d;
        }
    }
    throw new RuntimeException("nessuna cartella .harness/ sopra {$start}");
}

/**
 * Path assoluto, o relativo a $cwd, → relativo alla root. Fuori dalla root → '../…'.
 * Niente realpath sul file (può non esistere ancora); la root vale anche nella forma risolta.
 */
function rel(string $root, string $cwd, string $path): string
{
    if ($path === '') {
        return '';
    }
    $abs = normalize(str_starts_with($path, '/') ? $path : rtrim($cwd, '/').'/'.$path);
    foreach (array_unique(array_filter([$root, realpath($root) ?: null])) as $r) {
        $r = normalize($r);
        if ($abs === $r) {
            return '.';
        }
        if (str_starts_with($abs, $r.'/')) {
            return substr($abs, strlen($r) + 1);
        }
    }

    return '..'.$abs;
}

function normalize(string $path): string
{
    $parts = [];
    foreach (explode('/', $path) as $seg) {
        if ($seg === '..') {
            array_pop($parts);
        } elseif ($seg !== '' && $seg !== '.') {
            $parts[] = $seg;
        }
    }

    return '/'.implode('/', $parts);
}

/** Glob sui path relativi: `**` qualunque profondità, `*` dentro un segmento, `?` un carattere. */
function glob_match(string $pattern, string $path): bool
{
    $re = strtr(preg_quote($pattern, '#'), ['\*\*/' => '(?:.*/)?', '\*\*' => '.*', '\*' => '[^/]*', '\?' => '[^/]']);

    return (bool) preg_match('#^'.$re.'$#', $path);
}

/** @param list<string> $patterns */
function any_match(array $patterns, string $path): bool
{
    foreach ($patterns as $p) {
        if (is_string($p) && glob_match($p, $path)) {
            return true;
        }
    }

    return false;
}

/**
 * Euristica: i path che un comando shell potrebbe scrivere. Le destinazioni di `>`/`>>`
 * (non `2>&1`, non /dev/null) sempre; con un verbo che scrive (sed -i, tee, rm, mv, cp, …)
 * anche tutti gli argomenti che non sono opzioni. Path relativi alla root.
 *
 * @return list<string>
 */
function shell_write_targets(string $command, string $root, string $cwd): array
{
    $targets = [];
    // Non `->` né `=>`: in un heredoc di PHP sono codice, non redirezioni.
    if (preg_match_all('/(?<![<>&=-])\d?>>?\s*([^\s;&|<>]+)/', $command, $m)) {
        foreach ($m[1] as $t) {
            if ($t !== '/dev/null' && ! str_starts_with($t, '&')) {
                $targets[] = trim($t, '\'"');
            }
        }
    }
    if (preg_match('/(^|[\s;&|(])((sed|perl)\s+(-\w*\s+)*-\w*i|tee|rm|mv|cp|truncate|touch|chmod|ln|install|dd)\b/', $command)) {
        foreach (preg_split('/[\s;&|()]+/', $command) ?: [] as $tok) {
            $tok = trim($tok, '\'"');
            if ($tok !== '' && ! str_starts_with($tok, '-') && preg_match('#[./]#', $tok)) {
                $targets[] = $tok;
            }
        }
    }

    return array_values(array_unique(array_map(fn ($t) => rel($root, $cwd, $t), $targets)));
}

// ---------------------------------------------------------------------------
// Task Contract
// ---------------------------------------------------------------------------

function tasks_dir(string $root): string
{
    return getenv('HARNESS_TASKS') ?: $root.'/.harness/tasks';
}

/**
 * Il task attivo: l'unico Task Contract con status: approved. Più di uno è un errore
 * (il pre-tool fallisce chiuso sulle scritture). `_file` è il file letto, `_path` il path
 * logico del contract nel progetto (.harness/tasks/<nome>), anche quando replay lo legge altrove.
 */
function active_task(string $root): ?array
{
    $approved = [];
    foreach (glob(tasks_dir($root).'/T-*.md') ?: [] as $file) {
        $t = read_task($file);
        if (($t['status'] ?? null) === 'approved') {
            $approved[] = $t + ['_file' => $file, '_path' => '.harness/tasks/'.basename($file)];
        }
    }
    if (count($approved) > 1) {
        throw new RuntimeException('più di un Task Contract approvato: '.implode(', ', array_column($approved, '_path')));
    }

    return $approved[0] ?? null;
}

/** Frontmatter del contract più il corpo markdown (`_body`). [] se manca il frontmatter. */
function read_task(string $file): array
{
    $text = is_file($file) ? (string) file_get_contents($file) : '';
    if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $text, $m)) {
        return [];
    }

    return frontmatter($m[1]) + ['_body' => $m[2]];
}

/**
 * Parser YAML minimo, per il sottoinsieme del kit: scalari, liste `- x` e `[a, b]`,
 * due livelli di chiavi (scope: paths:), commenti `#` dopo uno spazio. Niente multilinea né ancore.
 */
function frontmatter(string $yaml): array
{
    $data = [];
    $k1 = $k2 = null;
    $scalar = function (string $v) {
        $v = trim($v);
        if (preg_match('/^\[(.*)\]$/', $v, $l)) {
            return array_values(array_filter(array_map(fn ($x) => trim($x, " '\""), explode(',', $l[1])), 'strlen'));
        }

        return preg_match('/^(["\'])(.*)\1$/', $v, $q) ? $q[2] : $v;
    };
    foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
        $line = rtrim((string) preg_replace('/(^|\s)#.*$/', '', $line));
        if (trim($line) === '') {
            continue;
        }
        $indent = strlen($line) - strlen(ltrim($line));
        $line = trim($line);
        if (str_starts_with($line, '- ') || $line === '-') {
            $item = $scalar(substr($line, 2));
            if ($k1 === null) {
                continue;
            }
            if ($k2 !== null && $indent > 0 && is_array($data[$k1])) {
                $data[$k1][$k2] = is_array($data[$k1][$k2] ?? null) ? $data[$k1][$k2] : [];
                $data[$k1][$k2][] = $item;
            } else {
                $data[$k1] = is_array($data[$k1]) ? $data[$k1] : [];
                $data[$k1][] = $item;
            }
        } elseif (preg_match('/^([\w.-]+):\s*(.*)$/', $line, $kv)) {
            if ($indent === 0) {
                [$k1, $k2] = [$kv[1], null];
                $data[$k1] = $kv[2] === '' ? [] : $scalar($kv[2]);
            } elseif ($k1 !== null) {
                $k2 = $kv[1];
                $data[$k1] = is_array($data[$k1]) ? $data[$k1] : [];
                $data[$k1][$k2] = $kv[2] === '' ? [] : $scalar($kv[2]);
            }
        }
    }

    return $data;
}

/** I problemi di forma di un contract (quelli che renderebbero cieco il guard). [] = ok. */
function task_problems(array $t): array
{
    $p = [];
    if (! preg_match('/^T-[\w-]+$/', (string) (is_string($t['id'] ?? null) ? $t['id'] : ''))) {
        $p[] = 'id mancante o non nella forma T-NNN';
    }
    if (! in_array($t['status'] ?? null, ['draft', 'approved', 'done'], true)) {
        $p[] = 'status deve essere draft, approved oppure done';
    }
    if (! is_array($t['scope']['paths'] ?? null) || $t['scope']['paths'] === []) {
        $p[] = 'scope.paths deve essere una lista non vuota';
    }
    foreach (['must_not_change', 'evidence', 'approval_required'] as $k) {
        if (isset($t[$k]) && ! is_array($t[$k])) {
            $p[] = "{$k} deve essere una lista";
        }
    }

    return $p;
}

// ---------------------------------------------------------------------------
// Output degli hook (un formato per entrambi gli agenti)
// ---------------------------------------------------------------------------

final class Out
{
    /** PreToolUse: nega, con un motivo che legge l'agente. */
    public static function deny(string $reason): never
    {
        self::json(['hookSpecificOutput' => ['hookEventName' => 'PreToolUse', 'permissionDecision' => 'deny', 'permissionDecisionReason' => $reason]]);
    }

    /** PostToolUse / SessionStart. Mai decision:block nel post-edit: Codex sostituirebbe il risultato del tool. */
    public static function context(string $event, string $text, int $max = 1500): never
    {
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max - 14)."\n… (troncato)";
        }
        self::json(['hookSpecificOutput' => ['hookEventName' => $event, 'additionalContext' => $text]]);
    }

    /** Stop: il reason è un'istruzione (su Codex diventa un nuovo prompt). */
    public static function block(string $reason): never
    {
        self::json(['decision' => 'block', 'reason' => $reason]);
    }

    /** Via libera: `{}` per lo Stop (Codex lo vuole JSON), niente per il resto. Mai "allow" esplicito. */
    public static function pass(string $event): never
    {
        if ($event === 'Stop') {
            echo '{}';
        }
        exit(0);
    }

    /** Stop che lascia chiudere, con un messaggio per l'umano (escalation): Codex lo mostra nella TUI. */
    public static function release(string $systemMessage): never
    {
        self::json(['systemMessage' => $systemMessage]);
    }

    /** Fail-closed del pre-tool: stderr + exit 2 blocca in entrambi gli agenti. */
    public static function failClosed(Throwable|string $e): never
    {
        $msg = $e instanceof Throwable ? $e->getMessage() : $e;
        fwrite(STDERR, "[harness:fail-closed] errore interno del guard, azione bloccata per sicurezza: {$msg}\n");
        exit(2);
    }

    private static function json(array $out): never
    {
        echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit(0);
    }
}

// ---------------------------------------------------------------------------
// Stato di sessione e trace
// ---------------------------------------------------------------------------

final class State
{
    public static function dir(string $root): string
    {
        return getenv('HARNESS_STATE_DIR') ?: $root.'/.harness/state';
    }

    /**
     * Stato della sessione, creato dal primo hook che la vede:
     *   run         id della run (cartella del trace e del receipt)
     *   base        HEAD all'inizio: il diff della sessione parte da qui
     *   dirty       file già modificati all'inizio (path => impronta): non sono lavoro dell'agente
     *   started_at  inizio della sessione (Progress deve essere più recente)
     *   attempts    stop rossi consecutivi (budget)
     *   last        impronta e verdetto dell'ultimo stop che ha verificato
     */
    public static function load(string $root, string $session): array
    {
        $file = self::dir($root).'/'.self::safe($session).'.json';
        $s = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (is_array($s)) {
            return $s;
        }
        try {
            $task = active_task($root)['id'] ?? 'notask';
        } catch (Throwable) {
            $task = 'notask'; // il nome della run non deve bloccare niente
        }
        $head = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse --verify --quiet HEAD 2>/dev/null'));
        $base = $head !== '' ? $head : null;
        $dirty = [];
        foreach ($base === null ? [] : changed_files($root, $base) as $path) {
            $dirty[$path] = file_hash($root, $path);
        }
        $s = ['run' => date('Y-m-d\THi').'-'.$task, 'base' => $base, 'dirty' => $dirty, 'started_at' => time(), 'attempts' => 0, 'last' => null];
        self::save($root, $session, $s);

        return $s;
    }

    public static function save(string $root, string $session, array $s): void
    {
        $dir = self::dir($root);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.self::safe($session).'.json', json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Dopo /clear o una compattazione l'agente riparte con un session_id nuovo, ma il lavoro è lo stesso:
     * la sessione nuova eredita base, file già sporchi, run e tentativi dall'ultima sessione.
     * Senza, il lavoro non committato della sessione precedente finirebbe fra i file "già modificati".
     */
    public static function inherit(string $root, string $session): void
    {
        $dir = self::dir($root);
        if (is_file($dir.'/'.self::safe($session).'.json')) {
            return;
        }
        $prev = array_filter(glob($dir.'/*.json') ?: [], fn ($f) => basename($f) !== 'heartbeat.json');
        usort($prev, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $s = $prev ? json_decode((string) file_get_contents($prev[0]), true) : null;
        if (is_array($s) && isset($s['run'])) {
            self::save($root, $session, $s);
        }
    }

    /** L'ultimo SessionStart visto per agente: la prova, per doctor, che l'agente esegue davvero gli hook. */
    public static function heartbeat(string $root, string $agent, array $data): void
    {
        $dir = self::dir($root);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $fh = fopen($dir.'/heartbeat.json', 'c+');
        if ($fh === false) {
            return;
        }
        flock($fh, LOCK_EX);
        $all = json_decode((string) stream_get_contents($fh), true);
        $all = is_array($all) ? $all : [];
        $all[$agent] = ['ts' => date('c')] + $data;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    private static function safe(string $session): string
    {
        return preg_replace('/[^\w.-]/', '_', $session) ?: 'no-session';
    }
}

/** La cartella delle run: runs/<run>/{trace.jsonl, receipt.md, metrics.json}. */
function run_dir(string $root, string $run): string
{
    return (getenv('HARNESS_RUNS_DIR') ?: $root.'/.harness/runs').'/'.$run;
}

/**
 * I file cambiati rispetto a $base: `git diff --name-only` (commit fatti dopo $base
 * compresi) più i non tracciati. Lo stato e le run dell'harness non contano.
 *
 * @return list<string>
 */
function changed_files(string $root, string $base): array
{
    $tracked = run(['git', 'diff', '--name-only', '--no-renames', $base, '--'], $root, 30);
    $untracked = run(['git', 'ls-files', '--others', '--exclude-standard'], $root, 30);
    if ($tracked['exit'] !== 0) {
        throw new RuntimeException('git diff '.$base.': '.trim($tracked['err']));
    }
    $files = array_filter(preg_split('/\R/', $tracked['out']."\n".$untracked['out']) ?: [], fn ($f) => $f !== ''
        && ! str_starts_with($f, '.harness/state/') && ! str_starts_with($f, '.harness/runs/'));
    $files = array_values(array_unique($files));
    sort($files);

    return $files;
}

/** I file cambiati in questa sessione: quelli già modificati all'inizio contano solo se sono cambiati ancora. */
function session_changes(string $root, array $state): array
{
    $dirty = is_array($state['dirty'] ?? null) ? $state['dirty'] : [];

    return array_values(array_filter(
        changed_files($root, (string) $state['base']),
        fn ($f) => ! array_key_exists($f, $dirty) || $dirty[$f] !== file_hash($root, $f),
    ));
}

/** Impronta del contenuto di un file del progetto ('-' se non esiste più). */
function file_hash(string $root, string $path): string
{
    return is_file($root.'/'.$path) ? (string) sha1_file($root.'/'.$path) : '-';
}

/**
 * Un sottoprocesso con un timeout: i comandi esterni (git, artisan, Pest, Pint) non
 * devono mai bloccare un hook. Con una stringa passa dalla shell (le evidence del contract).
 *
 * @return array{exit: int, out: string, err: string, ms: int, timed_out: bool}
 */
function run(array|string $cmd, string $cwd, int $timeout, ?string $stdin = null): array
{
    $t0 = microtime(true);
    $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (! is_resource($p)) {
        return ['exit' => 127, 'out' => '', 'err' => 'impossibile avviare il comando', 'ms' => 0, 'timed_out' => false];
    }
    fwrite($pipes[0], (string) $stdin);
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = $err = '';
    $timedOut = false;
    $exit = -1;
    while (true) {
        $read = [$pipes[1], $pipes[2]];
        $w = $e = null;
        if (@stream_select($read, $w, $e, 0, 100000) > 0) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
        }
        $status = proc_get_status($p);
        if (! $status['running']) {
            $exit = $status['exitcode'];
            break;
        }
        if (microtime(true) - $t0 > $timeout) {
            $timedOut = true;
            proc_terminate($p, 9);
            break;
        }
    }
    $out .= (string) stream_get_contents($pipes[1]);
    $err .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($p);

    return ['exit' => $timedOut ? 124 : ($exit >= 0 ? $exit : $closed), 'out' => $out, 'err' => $err, 'ms' => (int) round((microtime(true) - $t0) * 1000), 'timed_out' => $timedOut];
}

/**
 * Una riga JSON per invocazione di hook in runs/<run>/trace.jsonl. Non lancia mai:
 * un trace rotto non deve rompere un hook. Il campo `class` lo decide rules/classify.php (E9).
 */
function trace(Payload $p, string $event, array $data = []): void
{
    try {
        $run = State::load($p->root, $p->sessionId)['run'];
        $dir = run_dir($p->root, $run);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $line = ['ts' => date('c'), 'run' => $run, 'session' => $p->sessionId, 'agent' => $p->agent, 'event' => $event] + $data;
        $line['class'] = classify($p->root, $line);
        file_put_contents($dir.'/trace.jsonl', json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable) {
    }
}

/** La classe di fallimento di un evento, da rules/classify.php. Non lancia mai. */
function classify(string $root, array $event): ?string
{
    try {
        static $fn = null;
        $fn ??= is_file($root.'/.harness/rules/classify.php') ? require $root.'/.harness/rules/classify.php' : null;
        $class = is_callable($fn) ? $fn($event) : null;

        return is_string($class) ? $class : null;
    } catch (Throwable) {
        return 'unclassified';
    }
}
