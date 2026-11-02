<?php

declare(strict_types=1);

/*
 * .harness/lib/receipt.php — la verifica prima del "fatto" e il Change Receipt.
 * La usano bin/stop (a ogni stop che ha qualcosa da verificare) e bin/receipt (a mano).
 * Va caricata dopo lib/harness.php.
 *
 * verification() esegue tutti i controlli, sempre tutti: il reason dello stop li elenca
 * tutti, così l'agente li vede in un solo tentativo. Ogni comando esterno ha un timeout,
 * e un suo fallimento è un dato del receipt, non un crash dell'hook:
 *   1. perimetro    file cambiati ∩ must_not_change; file fuori da scope.paths
 *   2. stile e tipi Pint (fix, lo lancia lo stop prima) e PHPStan sui .php cambiati
 *   3. test mirati  necromancer:scan -q → affected-tests --depth=N → convenzione di path → suite
 *   4. evidence     i comandi del contract, con il JUnit; invarianti → test (rules/receipt.php, E7)
 *   5. Progress     del task attivo, aggiornato in questa sessione
 *
 * Il receipt separa ciò che lo script dimostra [D] da ciò che l'agente dichiara [A]
 * (le sezioni "Conflicts incontrati" e "Rischi aperti" di progress.md).
 */

namespace Harness;

use RuntimeException;
use Throwable;

/** Il tempo dello stop è finito: meglio un receipt NON VERIFICATO che un timeout dell'agente. */
final class DeadlineExceeded extends RuntimeException {}

/**
 * run() con un timeout che non supera la scadenza complessiva dello stop (o del post-edit).
 * Dentro un agente laravel/pao riscrive l'output di Pest e PHPStan in JSON per il modello:
 * qui l'output lo legge uno script, che vuole i formati documentati (--error-format=raw).
 */
function run_until(array|string $cmd, string $root, int $timeout, float $deadline, ?string $stdin = null): array
{
    putenv('PAO_DISABLE=1');
    $left = (int) floor($deadline - microtime(true));
    if ($left <= 0) {
        throw new DeadlineExceeded('tempo dello stop esaurito');
    }
    $r = run($cmd, $root, min($timeout, $left), $stdin);
    if ($r['timed_out'] && $left <= $timeout) {
        throw new DeadlineExceeded('tempo dello stop esaurito durante: '.(is_array($cmd) ? implode(' ', $cmd) : $cmd));
    }

    return $r;
}

/** Un comando di config.php['tools'] che punta a vendor/bin/… non installato. */
function tool_missing(string $root, array $cmd): bool
{
    foreach ($cmd as $part) {
        if (str_starts_with($part, 'vendor/bin/') && ! is_file($root.'/'.$part)) {
            return true;
        }
    }

    return false;
}

/** @return list<string> i .php cambiati che esistono ancora (le cancellazioni non si analizzano) */
function php_files(string $root, array $changed): array
{
    return array_values(array_filter($changed, fn ($f) => str_ends_with($f, '.php') && is_file($root.'/'.$f)));
}

/** @return list<string> */
function list_of(mixed $v): array
{
    return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
}

// ---------------------------------------------------------------------------
// La verifica
// ---------------------------------------------------------------------------

/**
 * Pint in modalità fix sui .php cambiati. Lo stop lo lancia prima di calcolare l'impronta
 * del diff, perché cambia i file. I riformattati si riconoscono dall'impronta prima e dopo.
 */
function pint_fix(string $root, array $changed, array $config, float $deadline): array
{
    $php = php_files($root, $changed);
    $cmd = list_of($config['tools']['pint'] ?? []);
    if ($php === []) {
        return ['status' => 'ok', 'fixed' => []];
    }
    if ($cmd === [] || tool_missing($root, $cmd)) {
        return ['status' => 'non disponibile', 'fixed' => []];
    }
    $before = array_map(fn ($f) => file_hash($root, $f), $php);
    $r = run_until([...$cmd, ...$php], $root, (int) ($config['timeouts']['pint'] ?? 30), $deadline);
    $fixed = array_values(array_filter($php, fn ($f, $i) => file_hash($root, $f) !== $before[$i], ARRAY_FILTER_USE_BOTH));

    return match (true) {
        $r['timed_out'] => ['status' => 'non disponibile (timeout)', 'fixed' => $fixed],
        $r['exit'] !== 0 => ['status' => 'errore: '.one_line($r['err'] ?: $r['out']), 'fixed' => $fixed],
        default => ['status' => $fixed === [] ? 'ok' : 'riformattati', 'fixed' => $fixed],
    };
}

/**
 * Tutti i controlli sul lavoro della sessione.
 *
 * @param  array  $task  il Task Contract attivo (read_task + _path)
 * @param  list<string>  $changed  path cambiati nella sessione
 * @param  ?array  $pint  esito di pint_fix(), null se non è stato lanciato (bin/receipt)
 * @param  ?int  $startedAt  inizio della sessione, null se non c'è una sessione
 */
function verification(string $root, array $task, string $base, array $changed, array $config, ?array $pint, ?int $startedAt, string $runDir, float $deadline): array
{
    $t0 = microtime(true);
    if (! is_dir($runDir)) {
        mkdir($runDir, 0777, true);
    }
    $timeout = fn (string $k) => (int) ($config['timeouts'][$k] ?? 60);
    $scope = [...list_of($task['scope']['paths'] ?? []), ...list_of($config['always_writable'] ?? [])];
    $mnc = list_of($task['must_not_change'] ?? []);

    // 1. Perimetro: le scritture dalla shell sfuggite al pre-tool finiscono qui.
    $numstat = [];
    foreach (preg_split('/\R/', run(['git', 'diff', '--numstat', '--no-renames', $base, '--'], $root, 30)['out']) ?: [] as $line) {
        if (preg_match('/^(\d+|-)\t(\d+|-)\t(.+)$/', $line, $m)) {
            $numstat[$m[3]] = [(int) $m[1], (int) $m[2]];
        }
    }
    $rows = [];
    foreach ($changed as $path) {
        [$add, $del] = $numstat[$path] ?? [is_file($root.'/'.$path) ? count(file($root.'/'.$path) ?: []) : 0, 0];
        $rows[] = ['path' => $path, 'added' => $add, 'removed' => $del, 'in_scope' => any_match($scope, $path), 'protected' => any_match($mnc, $path)];
    }
    $v = ['changed' => $rows];
    $v['perimeter'] = [
        'protected' => array_column(array_filter($rows, fn ($r) => $r['protected']), 'path'),
        'out_of_scope' => array_column(array_filter($rows, fn ($r) => ! $r['in_scope']), 'path'),
    ];

    // 2. Stile e tipi.
    $php = php_files($root, $changed);
    $phpstan = ['status' => 'ok', 'errors' => []];
    $cmd = list_of($config['tools']['phpstan'] ?? []);
    if ($php !== [] && ($cmd === [] || tool_missing($root, $cmd))) {
        $phpstan['status'] = 'non disponibile';
    } elseif ($php !== []) {
        $r = run_until([...$cmd, ...$php], $root, $timeout('phpstan'), $deadline);
        $errors = array_values(array_filter(preg_split('/\R/', trim(str_replace($root.DIRECTORY_SEPARATOR, '', $r['out']))) ?: [], fn ($l) => preg_match('/^.+\.php:\d+:/', $l)));
        $phpstan = match (true) {
            $r['timed_out'] => ['status' => 'non disponibile (timeout)', 'errors' => []],
            $r['exit'] === 0 => ['status' => 'ok', 'errors' => []],
            $errors !== [] => ['status' => 'rosso', 'errors' => $errors],
            default => ['status' => 'errore: '.one_line($r['err'] ?: $r['out']), 'errors' => []],
        };
    }
    $v['static'] = ['pint' => $pint ?? ['status' => 'non eseguito', 'fixed' => []], 'phpstan' => $phpstan];

    // 3. Test mirati.
    $v['selection'] = select_tests($root, $changed, $config, $deadline);
    $v['tests'] = ['tests' => 0, 'failed' => 0, 'failures' => [], 'exit' => 0];
    if ($v['selection']['how'] !== 'none') {
        $junit = $runDir.'/junit-selection.xml';
        @unlink($junit);
        $r = run_until([...list_of($config['tools']['pest'] ?? ['php', 'vendor/bin/pest']), ...$v['selection']['files'], '--log-junit', $junit], $root, $timeout('tests'), $deadline);
        $v['tests'] = junit($junit, $r);
    }

    // 4. Evidence del contract, poi invarianti → test.
    $v['evidence'] = [];
    foreach (list_of($task['evidence'] ?? []) as $i => $command) {
        $junit = $runDir."/junit-evidence-{$i}.xml";
        @unlink($junit);
        $isTest = (bool) preg_match('/\b(pest|phpunit)\b|artisan\s+test\b/', $command);
        $r = run_until($isTest ? $command.' --log-junit '.escapeshellarg($junit) : $command, $root, $timeout('tests'), $deadline);
        $res = $isTest ? junit($junit, $r) : ['tests' => null, 'failed' => null, 'failures' => [], 'no_tests' => false];
        $v['evidence'][] = [
            'command' => $command, 'exit' => $r['exit'], 'timed_out' => $r['timed_out'], 'ms' => $r['ms'],
            'group' => preg_match('/--group[= ](\S+)/', $command, $g) ? $g[1] : null,
        ] + $res;
    }
    $v['invariants'] = invariants($root, $changed, $v['evidence'], $config, $runDir, $deadline);

    // Conflicts [D]: le righe di AGENTS.md → Conflicts il cui path è nel diff.
    $v['conflicts'] = touched_conflicts($root, $changed);

    // 5. Progress, più le parti dichiarate [A].
    [$v['progress'], $v['declared']] = progress($root, (string) ($task['id'] ?? ''), $startedAt);
    $v['ms'] = (int) round((microtime(true) - $t0) * 1000);

    return $v;
}

/**
 * La selezione dei test, in catena: necromancer (scan, poi affected-tests) → convenzione di
 * path → suite completa. Se l'app non si avvia necromancer fallisce: si passa alla convenzione
 * e il rosso, se c'è, arriva dai test (con un messaggio migliore).
 */
function select_tests(string $root, array $changed, array $config, float $deadline): array
{
    $php = php_files($root, $changed);
    $sel = ['how' => 'none', 'boot' => 'ok', 'necromancer' => 'non usato', 'files' => []];
    if ($php === []) {
        return $sel;
    }
    $code = array_values(array_filter($php, fn ($f) => ! str_starts_with($f, 'tests/')));

    // a, b. necromancer: scan -q prima, perché un file nuovo altrimenti risulta Unmapped.
    $affected = [];
    $scan = list_of($config['tools']['scan'] ?? []);
    if ($code !== [] && $scan !== []) {
        $r = run_until($scan, $root, (int) ($config['timeouts']['scan'] ?? 30), $deadline);
        if ($r['exit'] === 0) {
            $cmd = [...list_of($config['tools']['affected'] ?? []), '--depth='.(int) ($config['affected_depth'] ?? 1)];
            $a = run_until($cmd, $root, (int) ($config['timeouts']['affected'] ?? 30), $deadline, implode("\n", $changed)."\n");
            $sel['necromancer'] = $a['exit'] === 0 ? 'ok' : 'errore';
            foreach ($a['exit'] === 0 ? preg_split('/\R/', trim($a['out'])) ?: [] : [] as $line) {
                $f = rel($root, $root, trim($line));
                if (str_starts_with($f, 'tests/') && is_file($root.'/'.$f)) {
                    $affected[] = $f;
                }
            }
        } else {
            // App che non si avvia, o necromancer non installato? (con -q scan non dice perché)
            $list = run_until(['php', 'artisan', 'list', '--raw'], $root, 30, $deadline);
            $sel['boot'] = $list['exit'] === 0 ? 'ok' : 'non si avvia';
            $sel['necromancer'] = match (true) {
                $list['exit'] !== 0 => 'non disponibile',
                ! str_contains($list['out'], 'necromancer:') => 'assente',
                default => 'errore (exit '.$r['exit'].')',
            };
        }
    }

    // c. Convenzione di path: app/**/X.php → tests/**/XTest.php; i test cambiati → se stessi.
    $byName = [];
    if (is_dir($root.'/tests')) {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/tests', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (str_ends_with($file->getFilename(), 'Test.php')) {
                $byName[$file->getFilename()][] = rel($root, $root, $file->getPathname());
            }
        }
    }
    $convention = array_values(array_filter($php, fn ($f) => str_starts_with($f, 'tests/') && str_ends_with($f, 'Test.php')));
    foreach ($code as $f) {
        array_push($convention, ...($byName[basename($f, '.php').'Test.php'] ?? []));
    }

    $files = array_values(array_unique([...$affected, ...$convention]));
    sort($files);
    $sel['files'] = $files;
    // d. Niente di mirato ma codice PHP cambiato (una migrazione, una rotta): la suite completa.
    $sel['how'] = match (true) {
        $affected !== [] => 'affected',
        $files !== [] => 'convention',
        default => 'full',
    };

    return $sel;
}

/** Esito di un comando di test dal suo JUnit: test, rossi, nomi dei rossi. */
function junit(string $file, array $r): array
{
    $res = ['tests' => 0, 'failed' => 0, 'failures' => [], 'exit' => $r['exit']];
    $xml = is_file($file) ? @simplexml_load_file($file) : false;
    if ($xml !== false) {
        foreach ($xml->xpath('//testcase') ?: [] as $tc) {
            $res['tests']++;
            if (isset($tc->failure) || isset($tc->error)) {
                $res['failed']++;
                $class = (string) ($tc['class'] ?? '');
                $res['failures'][] = ($class !== '' ? substr($class, (int) strrpos('\\'.$class, '\\')).' > ' : '').(string) $tc['name'];
            }
        }
    }
    // Exit ≠ 0 senza test rossi: un group vuoto ("No tests found") oppure un errore prima dei test.
    $res['no_tests'] = $res['tests'] === 0 && $r['exit'] !== 0 && str_contains($r['out'].$r['err'], 'No tests found');
    if ($r['exit'] !== 0 && $res['failed'] === 0 && ! $res['no_tests'] && ! $r['timed_out']) {
        $res['failures'][] = 'il comando esce con '.$r['exit'].': '.one_line(trim($r['err']) !== '' ? $r['err'] : $r['out']);
        $res['failed'] = max(1, $res['failed']);
    }
    if ($r['timed_out']) {
        $res['failures'][] = 'timeout';
    }

    return $res;
}

/**
 * Gli invarianti da dimostrare: quelli dei Behavioral Contract delle classi cambiate
 * (@contract nel docblock), più i group degli evidence che sono ID di invarianti.
 * Per ogni ID, i test del suo group: dall'evidence se c'è, altrimenti con un pest --group.
 * Le righe le scrive rules/receipt.php (E7); finché è un TODO la tabella resta "da completare".
 */
function invariants(string $root, array $changed, array $evidence, array $config, string $runDir, float $deadline): array
{
    $ids = [];
    $contracts = [];
    foreach ($changed as $f) {
        $src = str_ends_with($f, '.php') && ! str_starts_with($f, 'tests/') ? (string) @file_get_contents($root.'/'.$f) : '';
        if (preg_match_all('/@contract\s+(\S+\.md)/', $src, $m)) {
            foreach ($m[1] as $c) {
                $contracts[$c] = true;
                if (preg_match_all('/^\|\s*([A-Z]+(?:-[A-Z]+)*-INV-\d+)\s*\|/m', (string) @file_get_contents($root.'/'.$c), $rows)) {
                    array_push($ids, ...$rows[1]);
                }
            }
        }
    }
    foreach ($evidence as $e) {
        if (is_string($e['group'] ?? null) && preg_match('/^[A-Z]+(?:-[A-Z]+)*-INV-\d+$/', $e['group'])) {
            $ids[] = $e['group'];
        }
    }
    $ids = array_values(array_unique($ids));

    $groups = [];
    foreach ($ids as $i => $id) {
        $ev = array_values(array_filter($evidence, fn ($e) => ($e['group'] ?? null) === $id));
        if ($ev !== []) {
            $groups[$id] = ['tests' => (int) $ev[0]['tests'], 'failed' => (int) $ev[0]['failed']];

            continue;
        }
        $junit = $runDir."/junit-group-{$i}.xml";
        @unlink($junit);
        $r = run_until([...list_of($config['tools']['pest'] ?? ['php', 'vendor/bin/pest']), '--group='.$id, '--log-junit', $junit], $root, (int) ($config['timeouts']['tests'] ?? 300), $deadline);
        $res = junit($junit, $r);
        $groups[$id] = ['tests' => $res['tests'], 'failed' => $res['failed']];
    }

    $out = ['ids' => $ids, 'contracts' => array_keys($contracts), 'groups' => $groups, 'rows' => [], 'todo' => false, 'error' => null];
    try {
        $policy = require $root.'/.harness/rules/receipt.php';
        $rows = $ids === [] ? [] : ($policy['invariantRows'])($ids, $groups);
        $out['rows'] = array_values(array_filter(is_array($rows) ? $rows : [], fn ($r) => is_array($r) && isset($r['id'], $r['outcome'])));
        $out['todo'] = $ids !== [] && $out['rows'] === [];
    } catch (Throwable $e) {
        $out['error'] = 'rules/receipt.php: '.one_line($e->getMessage());
    }

    return $out;
}

/** Le righe di Conflicts di AGENTS.md che nominano (fra backtick) un path cambiato. */
function touched_conflicts(string $root, array $changed): array
{
    $md = (string) @file_get_contents($root.'/AGENTS.md');
    $section = preg_match('/^##\s+Conflicts\s*$(.*?)(?=^##\s|\z)/ms', $md, $m) ? $m[1] : '';
    $out = [];
    foreach (preg_split('/\R/', $section) ?: [] as $line) {
        $cells = array_map('trim', explode('|', trim($line, " |\t")));
        if (count($cells) < 2 || ! preg_match_all('/`([^`]*\/[^`]*)`/', $cells[1], $paths)) {
            continue;
        }
        foreach ($paths[1] as $p) {
            if (array_filter($changed, fn ($f) => $f === $p || str_starts_with($f, rtrim($p, '/').'/'))) {
                $out[] = ['id' => $cells[0], 'where' => $p];
                break;
            }
        }
    }

    return $out;
}

/**
 * Progress [D]: il task di progress.md è il task attivo, e il file è stato scritto in questa
 * sessione (mtime: `updated:` lo scrive l'agente). Più le sezioni dichiarate [A].
 *
 * @return array{0: array, 1: array}
 */
function progress(string $root, string $taskId, ?int $startedAt): array
{
    $file = $root.'/.harness/memory/progress.md';
    $t = read_task($file); // stesso formato: frontmatter + corpo
    $task = is_string($t['task'] ?? null) ? $t['task'] : null;
    $fresh = $startedAt === null || ! is_file($file) ? null : filemtime($file) >= $startedAt;
    $problems = [];
    if (! is_file($file)) {
        $problems[] = 'manca .harness/memory/progress.md';
    } elseif ($task !== $taskId) {
        $problems[] = 'task: '.($task ?? '—').' invece di '.$taskId;
    }
    if ($fresh === false) {
        $problems[] = 'non aggiornato in questa sessione';
    }
    $section = function (string $title) use ($t): array {
        $body = (string) ($t['_body'] ?? '');
        if (! preg_match('/^##\s+'.preg_quote($title, '/').'\s*$(.*?)(?=^##\s|\z)/ms', $body, $m)) {
            return [];
        }
        $items = array_map(fn ($l) => trim((string) preg_replace('/^\s*[-*]\s+/', '', $l)), preg_split('/\R/', trim($m[1])) ?: []);

        return array_values(array_filter($items, fn ($l) => $l !== '' && ! str_starts_with($l, '<!--')));
    };

    return [
        ['task' => $task, 'fresh' => $fresh, 'problem' => $problems === [] ? null : implode('; ', $problems)],
        ['conflicts' => $section('Conflicts incontrati'), 'risks' => $section('Rischi aperti')],
    ];
}

// ---------------------------------------------------------------------------
// Dalla verifica al verdetto
// ---------------------------------------------------------------------------

/** Una verifica letta da un file (HARNESS_STOP_DRY, replay): i campi mancanti valgono "verde". */
function verification_defaults(array $v): array
{
    $v += ['changed' => [], 'perimeter' => [], 'static' => [], 'selection' => [], 'tests' => [], 'evidence' => [], 'invariants' => [], 'conflicts' => [], 'progress' => [], 'declared' => [], 'ms' => 0];
    $v['perimeter'] += ['protected' => [], 'out_of_scope' => []];
    $v['static'] += ['pint' => ['status' => 'ok', 'fixed' => []], 'phpstan' => ['status' => 'ok', 'errors' => []]];
    $v['selection'] += ['how' => 'none', 'boot' => 'ok', 'necromancer' => 'non usato', 'files' => []];
    $v['tests'] += ['tests' => 0, 'failed' => 0, 'failures' => [], 'exit' => 0];
    $v['invariants'] += ['ids' => [], 'contracts' => [], 'groups' => [], 'rows' => [], 'todo' => false, 'error' => null];
    $v['progress'] += ['task' => null, 'fresh' => null, 'problem' => null];
    $v['declared'] += ['conflicts' => [], 'risks' => []];
    foreach ($v['changed'] as $i => $row) {
        $v['changed'][$i] = (is_array($row) ? $row : ['path' => (string) $row]) + ['added' => 0, 'removed' => 0, 'in_scope' => true, 'protected' => false];
    }

    return $v;
}

/**
 * Cosa non va, una riga per problema, scritta come istruzione per l'agente.
 *
 * @return list<array{class: string, text: string}>
 */
function problems(array $v): array
{
    $out = [];
    $add = function (string $class, string $text) use (&$out): void {
        $out[] = ['class' => $class, 'text' => $text];
    };
    $list = fn (array $xs, int $max = 3) => implode(', ', array_map(fn ($x) => "`{$x}`", array_slice($xs, 0, $max))).(count($xs) > $max ? ' e altri '.(count($xs) - $max) : '');

    if ($v['perimeter']['protected'] !== []) {
        $add('protected_file', 'hai modificato file in must_not_change: '.$list($v['perimeter']['protected']).'. Ripristinali (git checkout <base> -- <path>) e lavora dentro lo scope.');
    }
    $outside = array_values(array_diff($v['perimeter']['out_of_scope'], $v['perimeter']['protected']));
    if ($outside !== []) {
        $add('scope_violation', 'file fuori da scope.paths: '.$list($outside).'. Toglili, oppure chiedi all\'umano di allargare lo scope con [ATTESA-UMANO].');
    }
    if (($v['static']['phpstan']['status'] ?? '') === 'rosso') {
        $add('static_analysis', 'PHPStan: '.count($v['static']['phpstan']['errors']).' errori, il primo: '.one_line((string) $v['static']['phpstan']['errors'][0]));
    }
    // Test rossi: un errore uguale in più comandi (un ParseError rompe tutti i group) è una riga sola.
    $red = [];
    $collect = function (string $where, array $failures) use (&$red): void {
        $key = implode('; ', array_slice($failures, 0, 3)).(count($failures) > 3 ? ' e altri '.(count($failures) - 3) : '');
        $red[$key][] = $where;
    };
    if ((int) $v['tests']['failed'] > 0 || (int) $v['tests']['exit'] !== 0) {
        $collect('test mirati ('.$v['selection']['how'].')', $v['tests']['failures'] ?: ['il comando esce con '.$v['tests']['exit']]);
    }
    $noTests = [];
    foreach ($v['evidence'] as $e) {
        if ((int) $e['exit'] === 0) {
            continue;
        }
        if ($e['no_tests'] ?? ($e['tests'] === 0 && ($e['group'] ?? null) !== null)) {
            $noTests[] = $e['group'] ?? "`{$e['command']}`";
        } else {
            $collect("`{$e['command']}`", (array) $e['failures'] ?: ['exit '.$e['exit']]);
        }
    }
    foreach ($red as $what => $where) {
        $add('test_failure', 'rossi in '.implode(', ', $where).': '.$what);
    }
    foreach ($v['invariants']['rows'] as $row) {
        if (($row['outcome'] ?? '') === 'non coperta' && ! in_array($row['id'], $noTests, true)) {
            $noTests[] = $row['id'];
        } elseif (($row['outcome'] ?? '') === 'rossa' && ! array_filter($v['evidence'], fn ($e) => ($e['group'] ?? null) === $row['id'] && (int) $e['exit'] !== 0)) {
            $add('test_failure', "{$row['id']}: i suoi test sono rossi.");
        }
    }
    if ($noTests !== []) {
        $add('missing_evidence', 'nessun test per '.implode(', ', $noTests).": ogni invariante va provata da almeno un test con ->group('<ID>').");
    }
    if ($v['progress']['problem'] !== null) {
        $add('progress_stale', 'aggiorna .harness/memory/progress.md (skill progress): '.$v['progress']['problem'].'.');
    }

    return $out;
}

/** @return list<string> le classi di fallimento, senza ripetizioni */
function failure_classes(array $v): array
{
    return array_values(array_unique(array_column(problems($v), 'class')));
}

// ---------------------------------------------------------------------------
// Il receipt: receipt.md + metrics.json in runs/<run>/
// ---------------------------------------------------------------------------

const VERDICTS = [
    'VERDE' => '✅ VERDE', 'ROSSO' => '❌ ROSSO', 'IN ATTESA' => '⏸️ IN ATTESA',
    'NON VERIFICATO' => '⚠️ NON VERIFICATO', 'ESCALATION' => '🛑 ESCALATION',
];

/**
 * Il Change Receipt in markdown. $meta: run, agent, task (array), base, verdict, e se servono
 * attempt/budget, question ([ATTESA-UMANO]), error (NON VERIFICATO), restart (ESCALATION).
 */
function receipt_md(?array $v, array $meta): string
{
    $task = $meta['task'] ?? [];
    $id = (string) ($task['id'] ?? '—');
    $run = (string) $meta['run'];
    $verdict = (string) $meta['verdict'];
    $yes = fn (bool $b) => $b ? 'sì' : '**no**';
    $o = [];
    $o[] = '<!-- php .harness/bin/receipt · [D] lo dimostra lo script (deterministico, ripetibile) · [A] lo dichiara l\'agente in progress.md, senza verifica -->';
    $o[] = '';
    $o[] = "# Change Receipt — {$id} · ".(VERDICTS[$verdict] ?? $verdict);
    $o[] = '';
    $o[] = "Run `{$run}` · agente `".($meta['agent'] ?? '—').'` · '.date('Y-m-d H:i').' · trace: `.harness/runs/'.$run.'/trace.jsonl`';
    $o[] = '';

    $o[] = '## Esito [D]';
    $o[] = '';
    if ($verdict === 'IN ATTESA') {
        $o[] = 'L\'agente si è fermato per una decisione umana: lo stop non ha eseguito i test, il task **non** è finito.';
        $o[] = '';
        $o[] = '> '.str_replace("\n", "\n> ", trim((string) ($meta['question'] ?? '')));
    } elseif ($verdict === 'NON VERIFICATO') {
        $o[] = 'Lo stop non ha potuto verificare (ha lasciato chiudere per non bloccare l\'agente in un loop): '.one_line((string) ($meta['error'] ?? 'errore sconosciuto'));
        $o[] = '';
        $o[] = 'Rilancia la verifica a mano: `php .harness/bin/receipt`.';
    } elseif ($v !== null) {
        $problems = problems($v);
        $o[] = $problems === [] ? 'Tutti i controlli sono verdi.' : implode("\n", array_map(fn ($p) => "- `{$p['class']}` — {$p['text']}", $problems));
        if (isset($meta['attempt'])) {
            $o[] = '';
            $o[] = "Tentativo {$meta['attempt']}/{$meta['budget']} dello stop.";
        }
    }
    $o[] = '';

    if ($verdict === 'ESCALATION') {
        $o[] = '## Escalation';
        $o[] = '';
        $o[] = "Lo stop ha trovato rossi {$meta['budget']} volte di seguito: l'agente ha esaurito i tentativi e la decisione passa a te.";
        $o[] = '';
        $o[] = '- Classi di fallimento: '.implode(', ', array_map(fn ($c) => "`{$c}`", failure_classes($v ?? verification_defaults([]))));
        $o[] = '- Il budget riparte da zero: puoi dare all\'agente un\'indicazione e lasciarlo riprovare.';
        $o[] = '- Oppure riparti dall\'ultimo checkpoint verde: salva il tentativo (`git switch -c tentativo && git add -A && git commit -m "tentativo fallito"`), poi `git switch -c retry '.($meta['restart'] ?? '<cp>').'`.';
        $o[] = '';
    }

    $o[] = '## Task [D]';
    $o[] = '';
    $o[] = "{$id} \"".($task['_title'] ?? '').'" · status `'.($task['status'] ?? '—').'` · `'.($task['_path'] ?? '').'` · commit base `'.substr((string) ($meta['base'] ?? ''), 0, 7).'`';
    $o[] = '';
    if ($v === null) {
        return implode("\n", $o)."\n";
    }

    $o[] = '## Diff [D]';
    $o[] = '';
    if ($v['changed'] === []) {
        $o[] = 'Nessun file cambiato.';
    } else {
        $o[] = '| File | +/− | Nello scope | must_not_change |';
        $o[] = '|---|---|---|---|';
        foreach ($v['changed'] as $r) {
            $o[] = "| {$r['path']} | +{$r['added']} −{$r['removed']} | ".$yes((bool) $r['in_scope']).' | '.($r['protected'] ? '**toccato**' : '—').' |';
        }
    }
    $o[] = '';

    $o[] = '## Stile e tipi [D]';
    $o[] = '';
    $pint = $v['static']['pint'];
    $o[] = '- Pint: '.$pint['status'].($pint['fixed'] ? ' ('.implode(', ', array_map(fn ($f) => "`{$f}`", $pint['fixed'])).')' : '');
    $stan = $v['static']['phpstan'];
    $o[] = '- PHPStan: '.$stan['status'].($stan['errors'] ? "\n  - ".implode("\n  - ", array_map(fn ($e) => one_line((string) $e), array_slice($stan['errors'], 0, 5))) : '');
    $o[] = '';

    $o[] = '## Test mirati [D]';
    $o[] = '';
    $sel = $v['selection'];
    $how = ['affected' => 'necromancer affected-tests', 'convention' => 'convenzione di path (app/**/X.php → tests/**/XTest.php)', 'full' => 'suite completa (nessun test mirato)', 'none' => 'nessuno: niente PHP cambiato'];
    $o[] = 'Selezione: '.($how[$sel['how']] ?? $sel['how']).' · necromancer: '.$sel['necromancer'].($sel['boot'] !== 'ok' ? ' · **l\'app non si avvia**' : '');
    if ($sel['files'] !== []) {
        $o[] = '';
        $o[] = implode(', ', array_map(fn ($f) => "`{$f}`", $sel['files']));
    }
    if ($sel['how'] !== 'none') {
        $t = $v['tests'];
        $o[] = '';
        $o[] = ((int) $t['failed'] === 0 && (int) $t['exit'] === 0 ? '✅' : '❌')." {$t['tests']} test, {$t['failed']} ".((int) $t['failed'] === 1 ? 'rosso' : 'rossi').($t['failures'] ? ":\n- ".implode("\n- ", array_slice($t['failures'], 0, 10)) : '');
    }
    $o[] = '';

    $o[] = '## Evidenze [D]';
    $o[] = '';
    if ($v['evidence'] === []) {
        $o[] = 'Il contract non ha `evidence`.';
    } else {
        $o[] = '| Comando | Esito | Test | Durata |';
        $o[] = '|---|---|---|---|';
        foreach ($v['evidence'] as $e) {
            $esito = match (true) {
                ! empty($e['timed_out']) => '⚠️ timeout',
                (int) $e['exit'] === 0 => '✅',
                ! empty($e['no_tests']) => '❌ nessun test',
                default => '❌ '.(int) $e['failed'].((int) $e['failed'] === 1 ? ' rosso' : ' rossi'),
            };
            $o[] = "| `{$e['command']}` | {$esito} | ".($e['tests'] ?? '—').' | '.number_format(((int) $e['ms']) / 1000, 1, ',', '').' s |';
        }
    }
    $o[] = '';

    $o[] = '## Invarianti → test [D]';
    $o[] = '';
    $inv = $v['invariants'];
    if ($inv['ids'] === []) {
        $o[] = 'Nessun invariante da dimostrare: nessuna classe cambiata con `@contract`, nessun `--group` di invariante in `evidence`.';
    } elseif ($inv['error'] !== null) {
        $o[] = '⚠️ '.$inv['error'];
    } elseif ($inv['todo']) {
        $o[] = 'Da completare (E7, `invariantRows` in `.harness/rules/receipt.php`). Gli ID da dimostrare: '.implode(', ', $inv['ids']).'.';
    } else {
        $o[] = '| Invariante | Test | Esito |';
        $o[] = '|---|---|---|';
        foreach ($inv['rows'] as $r) {
            $icon = ['verde' => '✅', 'rossa' => '❌', 'non coperta' => '❌ non coperta'][$r['outcome']] ?? $r['outcome'];
            $o[] = "| {$r['id']} | ".($r['tests'] ?? '—')." | {$icon} |";
        }
        $o[] = '';
        $o[] = 'Un invariante con 0 test è ❌ "non coperta": l\'agente non lo può dimostrare.';
    }
    $o[] = '';

    $o[] = '## Progress [D]';
    $o[] = '';
    $p = $v['progress'];
    $o[] = $p['problem'] === null ? '`.harness/memory/progress.md`: task `'.($p['task'] ?? '—').'`'.($p['fresh'] === null ? ' (freschezza non verificabile senza una sessione)' : ', aggiornato in questa sessione').'.' : '❌ `.harness/memory/progress.md`: '.$p['problem'].'.';
    $o[] = '';

    $o[] = '## Conflicts [D] + [A]';
    $o[] = '';
    $o[] = $v['conflicts'] === []
        ? '- [D] Il diff non tocca file elencati in AGENTS.md → Conflicts.'
        : implode("\n", array_map(fn ($c) => "- [D] {$c['id']}: il diff tocca `{$c['where']}`.", $v['conflicts']));
    $o[] = $v['declared']['conflicts'] === [] ? '- [A] Nessun Conflict dichiarato in progress.md.' : implode("\n", array_map(fn ($c) => '- [A] "'.$c.'"', $v['declared']['conflicts']));
    $o[] = '';

    $o[] = '## Rischi aperti [A]';
    $o[] = '';
    $o[] = $v['declared']['risks'] === [] ? '- Nessun rischio dichiarato in progress.md.' : implode("\n", array_map(fn ($r) => '- "'.$r.'"', $v['declared']['risks']));
    $o[] = '';

    $o[] = '## Metriche [D]';
    $o[] = '';
    $o[] = 'Richieste al modello, token e costo: _da estrarre_ dal transcript (`metrics.json`, `workshop/bin/run`). Verifica: '.number_format(((int) $v['ms']) / 1000, 1, ',', '').' s.';

    return implode("\n", $o)."\n";
}

/**
 * metrics.json accanto al receipt: i campi che lo script dimostra (`deterministic`), quelli che
 * l'agente dichiara (`declared`) e i contatori degli hook dal trace. Token e costo restano null:
 * li estrae dal transcript chi lancia la run (workshop/bin/run).
 */
function metrics_json(?array $v, array $meta, string $runDir): array
{
    $hooks = ['pre_tool' => 0, 'denied' => 0, 'stop_blocked' => 0, 'escalated' => 0, 'waiting' => 0, 'errors' => 0];
    foreach (preg_split('/\R/', (string) @file_get_contents($runDir.'/trace.jsonl')) ?: [] as $line) {
        $e = (string) (json_decode($line, true)['event'] ?? '');
        $hooks['pre_tool'] += (int) str_starts_with($e, 'pre_tool.');
        $hooks['denied'] += (int) ($e === 'pre_tool.denied');
        $hooks['stop_blocked'] += (int) ($e === 'stop.blocked');
        $hooks['escalated'] += (int) ($e === 'stop.escalated');
        $hooks['waiting'] += (int) ($e === 'stop.waiting');
        $hooks['errors'] += (int) str_ends_with($e, '.error');
    }
    $det = null;
    if ($v !== null) {
        $rows = $v['invariants']['rows'];
        $det = [
            'base' => $meta['base'] ?? null,
            'files' => count($v['changed']),
            'protected_touched' => $v['perimeter']['protected'],
            'out_of_scope' => $v['perimeter']['out_of_scope'],
            'selection' => $v['selection']['how'],
            'boot' => $v['selection']['boot'],
            'tests' => ['total' => (int) $v['tests']['tests'], 'failed' => (int) $v['tests']['failed']],
            'evidence' => ['commands' => count($v['evidence']), 'red' => count(array_filter($v['evidence'], fn ($e) => (int) $e['exit'] !== 0))],
            'invariants' => $v['invariants']['todo'] ? null : [
                'total' => count($rows),
                'green' => count(array_filter($rows, fn ($r) => $r['outcome'] === 'verde')),
                'uncovered' => array_values(array_column(array_filter($rows, fn ($r) => $r['outcome'] === 'non coperta'), 'id')),
            ],
            'progress_fresh' => $v['progress']['problem'] === null,
            'classes' => failure_classes($v),
            'ms' => (int) $v['ms'],
        ];
    }

    return [
        'schema' => 1,
        'run' => $meta['run'],
        'agent' => $meta['agent'] ?? null,
        'task' => $meta['task']['id'] ?? null,
        'verdict' => $meta['verdict'],
        'attempt' => $meta['attempt'] ?? null,
        'budget' => $meta['budget'] ?? null,
        'generated_at' => date('c'),
        'deterministic' => $det,
        'declared' => $v === null ? null : $v['declared'],
        'hooks' => $hooks,
        'transcript_path' => $meta['transcript_path'] ?? null,
        'requests' => null, 'tool_calls' => null, 'tokens' => null, 'cost_usd' => null,
    ];
}

/** Scrive receipt.md e metrics.json nella cartella della run. Restituisce il path del receipt. */
function write_receipt(string $runDir, ?array $v, array $meta): string
{
    if (! is_dir($runDir)) {
        mkdir($runDir, 0777, true);
    }
    file_put_contents($runDir.'/receipt.md', receipt_md($v, $meta), LOCK_EX);
    file_put_contents($runDir.'/metrics.json', json_encode(metrics_json($v, $meta, $runDir), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", LOCK_EX);

    return $runDir.'/receipt.md';
}

/** Il titolo del contract: la prima riga `# T-NNN — Titolo` del corpo. */
function task_title(array $task): string
{
    return preg_match('/^#\s+[\w-]+\s*[—–-]\s*(.+)$/mu', (string) ($task['_body'] ?? ''), $m) ? trim($m[1]) : '';
}

/** L'ultimo checkpoint raggiungibile da $base: da lì si riparte dopo un'escalation. */
function last_checkpoint(string $root, string $base): ?string
{
    $r = run(['git', 'describe', '--tags', '--abbrev=0', '--match', 'cp-*', $base], $root, 10);

    return $r['exit'] === 0 && trim($r['out']) !== '' ? trim($r['out']) : null;
}

function one_line(string $s, int $max = 200): string
{
    $s = trim((string) preg_replace('/\s+/', ' ', $s));

    return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
}
