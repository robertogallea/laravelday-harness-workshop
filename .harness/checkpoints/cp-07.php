<?php

// E7 — Change Receipt: invariantRows() in rules/receipt.php. Arriva con cp-06 (ingresso: la
// consegna, questo file e le fixture del receipt; bin/receipt e lib/receipt.php sono in cp-04),
// la soluzione è in cp-07. `verify cp-07` è anche il controllo di sala prima della run 2.
// Niente costanti, funzioni con nome o require in cima: il file si può caricare più volte.

use Workshop as W;
use Workshop\Ctx;

/** Una riga in chiaro: "REFUND-INV-6 non coperta (0 test)". */
$riga = fn (mixed $r): string => is_array($r)
    ? ($r['id'] ?? '(senza id)').' '.(is_string($r['outcome'] ?? null) ? $r['outcome'] : '(senza outcome)').' ('.(is_int($r['tests'] ?? null) ? $r['tests'] : '?').' test)'
    : '(non è un array: '.get_debug_type($r).')';

return [
    'esercizio' => 'E7',
    'ingresso' => [
        '.harness/checkpoints/cp-07.php',
        'workshop/esercizi/E7.md',
        '.harness/fixtures/receipt',
    ],
    'uscita' => ['.harness/rules/receipt.php'],

    'controlli' => [
        // La funzione è pura: le fixture sono il suo input (ID del contract, esiti dei group) e l'output atteso.
        'invariantRows: le fixture del receipt' => function (Ctx $c) use ($riga) {
            $files = glob($c->path('.harness/fixtures/receipt').'/*.json') ?: [];
            if ($files === []) {
                return $c->fail('mancano le fixture in .harness/fixtures/receipt', 'php workshop/bin/checkpoint inizia E7');
            }
            try {
                $policy = (static fn (string $f) => require $f)($c->path('.harness/rules/receipt.php'));
                $fn = $policy['invariantRows'] ?? null;
                if (! is_callable($fn)) {
                    return $c->fail('rules/receipt.php non restituisce una closure invariantRows', 'git checkout cp-06 -- .harness/rules/receipt.php');
                }
            } catch (Throwable $e) {
                return $c->fail('rules/receipt.php non si carica: '.$e->getMessage());
            }

            $casi = [];
            foreach ($files as $f) {
                $nome = basename($f, '.json');
                $fx = json_decode((string) file_get_contents($f), true);
                $attese = $fx['_expect'] ?? [];
                // Un warning (chiave mancante) è un errore: nel receipt vero finirebbe in mezzo al JSON dello stop.
                set_error_handler(function (int $no, string $msg, string $file, int $line): never {
                    throw new ErrorException($msg, 0, $no, $file, $line);
                });
                try {
                    $ottenute = $fn($fx['contractIds'], $fx['groupResults']);
                } catch (Throwable $e) {
                    $casi[] = "\n        · {$nome}: ".get_class($e).': '.W\one_line($e->getMessage(), 120)."\n          ".($fx['_why'] ?? '');

                    continue;
                } finally {
                    restore_error_handler();
                }
                $ottenute = is_array($ottenute) ? array_values($ottenute) : [];
                // Si confrontano id, tests e outcome: altre chiavi nelle righe sono ammesse.
                $norm = fn (mixed $r) => is_array($r) ? [$r['id'] ?? null, $r['tests'] ?? null, $r['outcome'] ?? null] : $r;
                if (array_map($norm, $ottenute) === array_map($norm, $attese)) {
                    continue;
                }
                $diff = [];
                foreach (range(0, max(count($attese), count($ottenute)) - 1) as $i) {
                    $a = $attese[$i] ?? null;
                    $o = $ottenute[$i] ?? null;
                    if ($norm($a) !== $norm($o)) {
                        $diff[] = 'riga '.($i + 1).': attesa '.($a === null ? 'nessuna' : $riga($a)).', ottenuta '.($o === null ? 'nessuna' : $riga($o));
                    }
                }
                $casi[] = "\n        · {$nome}: ".implode('; ', array_slice($diff, 0, 3)).(count($diff) > 3 ? ' e altre '.(count($diff) - 3) : '')."\n          ".($fx['_why'] ?? '');
            }

            return $casi === []
                ? $c->ok(count($files).'/'.count($files).' fixture')
                : $c->fail(count($casi).'/'.count($files).' FAIL'.implode('', $casi), 'apri la fixture in .harness/fixtures/receipt/<nome>.json: contractIds e groupResults sono l\'input, _expect le righe');
        },
    ],

    // Il controllo di sala prima della run 2: gli stessi del pre-check di `php workshop/bin/run 2`.
    // T-001 approvato, adattatori canonici e replay verdi li controllano già cp-04 e cp-05.
    'allineamento' => [
        'codice dell\'app uguale a cp-00 (ammessi docblock @contract e group di Pest)' => function (Ctx $c) {
            $diff = $c->git('diff', 'cp-00', '--', 'app', 'database', 'routes', 'tests');
            if ($diff['exit'] !== 0) {
                return $c->fail('git diff cp-00: '.trim($diff['err']), 'git fetch origin --tags --force');
            }
            $drift = W\app_drift($diff['out']);

            return $drift === [] ?: $c->fail(
                'la run 2 deve partire dallo stesso codice della run 1: '.implode(', ', $drift),
                'git checkout cp-07 -- '.implode(' ', $drift),
            );
        },
        'tutto committato (la run 2 parte da HEAD)' => function (Ctx $c) {
            $sporchi = array_values(array_filter(explode("\n", $c->git('status', '--porcelain')['out']))); // niente trim: " M" ha uno spazio in testa

            return $sporchi === [] ?: $c->fail(
                count($sporchi).' file non committati: '.W\one_line(implode(', ', array_map(fn ($l) => substr($l, 3), $sporchi)), 100),
                'git add -A && git commit -m "pronto per la run 2"',
            );
        },
        'progress.md punta a T-001' => function (Ctx $c) {
            $md = (string) $c->read('.harness/memory/progress.md');

            return preg_match('/^task:\s*T-001\s*$/m', $md) === 1 ?: $c->warn(
                'il session-start non darà all\'agente il task attivo dalla memoria',
                'git checkout cp-07 -- .harness/memory/progress.md',
            );
        },
        'heartbeat dell\'agente della run 2' => function (Ctx $c) {
            $agent = trim((string) $c->read('workshop/agent'));
            if (! in_array($agent, ['claude', 'codex'], true)) {
                // WARN e non FAIL: la CI non ha un agente. In aula è il momento di sceglierlo.
                return $c->warn('nessun agente scelto per la run 2: non so di chi controllare gli hook', 'php .harness/bin/doctor --agent=claude   (oppure codex)');
            }
            $come = $agent === 'codex'
                ? 'apri codex nella cartella del progetto, /hooks → approva i 4 hook, chiudi. Riapri codex e manda «rispondi solo: ok, non toccare nulla» (Codex esegue il SessionStart al primo messaggio, non all\'apertura), chiudi e poi php .harness/bin/doctor --agent=codex'
                : 'apri claude nella cartella del progetto e chiudilo, poi php .harness/bin/doctor --agent=claude';
            $hb = $c->json('.harness/state/heartbeat.json')[$agent] ?? null;
            if (! is_array($hb) || strtotime((string) ($hb['ts'] ?? '')) === false) {
                return $c->fail(
                    "{$agent} non ha mai eseguito gli hook in questo progetto: la run 2 sarebbe una run 1",
                    $come,
                );
            }

            $ts = (int) strtotime((string) $hb['ts']);
            $giorni = (int) floor((time() - $ts) / 86400);

            // Come doctor: un heartbeat vecchio dice che gli hook giravano, non che girano ancora.
            return $giorni <= 7
                ? $c->ok("{$agent}, ultimo SessionStart ".date('Y-m-d H:i', $ts))
                : $c->warn("l'ultimo SessionStart di {$agent} è di {$giorni} giorni fa", $come);
        },
        'la suite del docente non è nel repo' => function (Ctx $c) {
            $refs = trim($c->git('for-each-ref', '--format=%(refname)', 'refs/heads/eval', 'refs/workshop/', 'refs/remotes/origin/eval')['out']);

            // WARN e non FAIL: la CI lancia questo verify nel repo di build, dove la suite c'è.
            return $refs === '' ?: $c->warn(
                'l\'agente della run 2 la vedrebbe ('.str_replace("\n", ', ', $refs).'): php workshop/bin/run 2 si rifiuta di partire',
                'fai la run 2 in un clone del repo pubblico',
            );
        },
    ],
];
