<?php

// E9 (opzionale) — trace events: classify() in rules/classify.php dà un nome al fallimento di ogni
// evento di trace. Arriva con cp-08 (ingresso: la consegna, questo file e le fixture; il file con il
// TODO è in cp-04), la soluzione è in cp-09, che è anche la versione consegnata (tag laravelday-2026).
// Niente costanti, funzioni con nome o require in cima: il file si può caricare più volte.

use Workshop as W;
use Workshop\Ctx;

/** La closure di rules/classify.php, oppure un Esito FAIL se non si carica. */
$carica = function (Ctx $c): Closure|W\Esito {
    try {
        $fn = (static fn (string $f) => require $f)($c->path('.harness/rules/classify.php'));
    } catch (Throwable $e) {
        return $c->fail('rules/classify.php non si carica: '.W\one_line($e->getMessage()));
    }

    return $fn instanceof Closure ? $fn : $c->fail('rules/classify.php non restituisce una closure', 'git checkout cp-08 -- .harness/rules/classify.php');
};

/**
 * Una chiamata come la fa l'impianto, ma più severa: lì un'eccezione diventa "unclassified" in silenzio,
 * qui un warning (una chiave che manca) è un errore, perché nel trace vero finirebbe nello stesso posto.
 */
$chiama = function (Closure $fn, array $event): string {
    set_error_handler(function (int $no, string $msg, string $file, int $line): never {
        throw new ErrorException($msg, 0, $no, $file, $line);
    });
    try {
        $class = $fn($event);
    } finally {
        restore_error_handler();
    }

    return is_string($class) ? $class : '(non è una stringa: '.get_debug_type($class).')';
};

/** Un evento in una riga: "pre_tool.denied [env.access]", "stop.blocked [test_failure, …]". */
$inChiaro = function (array $e): string {
    $extra = match (true) {
        isset($e['rule']) => (string) $e['rule'],
        is_array($e['classes'] ?? null) && $e['classes'] !== [] => implode(', ', $e['classes']),
        ($e['phpstan_errors'] ?? 0) > 0 => "phpstan_errors: {$e['phpstan_errors']}",
        ($e['pint_fixed'] ?? []) !== [] => 'pint_fixed',
        ($e['skipped'] ?? []) !== [] => 'skipped: '.implode(', ', (array) $e['skipped']),
        default => '',
    };

    return ($e['event'] ?? '(senza event)').($extra !== '' ? " [{$extra}]" : '');
};

return [
    'esercizio' => 'E9',
    'ingresso' => [
        '.harness/checkpoints/cp-09.php',
        'workshop/esercizi/E9.md',
        '.harness/fixtures/classify',
    ],
    'uscita' => ['.harness/rules/classify.php'],

    'controlli' => [
        'classify: le fixture (esiti campione e due trace veri della run 2)' => function (Ctx $c) use ($carica, $chiama, $inChiaro) {
            $files = glob($c->path('.harness/fixtures/classify').'/*.json') ?: [];
            if ($files === []) {
                return $c->fail('mancano le fixture in .harness/fixtures/classify', 'php workshop/bin/checkpoint inizia E9');
            }
            $fn = $carica($c);
            if ($fn instanceof W\Esito) {
                return $fn;
            }
            $tot = 0;
            $casi = [];
            foreach ($files as $f) {
                $fx = json_decode((string) file_get_contents($f), true);
                $sbagliati = [];
                foreach ((array) ($fx['cases'] ?? []) as $caso) {
                    $tot++;
                    try {
                        $got = $chiama($fn, (array) $caso['in']);
                    } catch (Throwable $e) {
                        $got = get_class($e).': '.W\one_line($e->getMessage(), 80);
                    }
                    if ($got !== $caso['class']) {
                        $sbagliati[] = $inChiaro((array) $caso['in'])." → atteso {$caso['class']}, ottenuto {$got}";
                    }
                }
                if ($sbagliati !== []) {
                    // Al massimo tre righe per fixture: i trace veri hanno decine di eventi uguali.
                    $casi[] = "\n        · ".basename($f, '.json').': '.implode('; ', array_slice(array_unique($sbagliati), 0, 3))
                        .(count(array_unique($sbagliati)) > 3 ? ' e altri '.(count(array_unique($sbagliati)) - 3) : '')
                        ."\n          ".($fx['_why'] ?? '');
                }
            }

            return $casi === []
                ? $c->ok("{$tot}/{$tot} eventi, ".count($files).' fixture')
                : $c->fail(count($casi).'/'.count($files).' fixture con classi sbagliate'.implode('', $casi), 'apri .harness/fixtures/classify/<nome>.json: "in" è la riga di trace, "class" la classe attesa');
        },
        'nessun evento resta unclassified (anche quelli che le fixture non elencano)' => function (Ctx $c) use ($carica, $chiama, $inChiaro) {
            $fn = $carica($c);
            if ($fn instanceof W\Esito) {
                return $fn;
            }
            // Righe minime, con le sole chiavi che l'impianto scrive sempre: una regola nuova, un evento nuovo, campi assenti.
            $eventi = [
                ['event' => 'pre_tool.denied', 'rule' => 'una-regola.che-non-esiste-ancora'],
                ['event' => 'pre_tool.denied'],
                ['event' => 'post_edit.checked'],
                ['event' => 'stop.blocked'],
                ['event' => 'stop.blocked', 'classes' => []],
                ['event' => 'stop.escalated'],
                ['event' => 'replay.error'],
                ['event' => 'un_hook.nuovo'],
                [],
            ];
            $problemi = [];
            foreach ($eventi as $e) {
                try {
                    $got = $chiama($fn, $e);
                } catch (Throwable $ex) {
                    $problemi[] = ($inChiaro($e) ?: '{}').': '.get_class($ex).' '.W\one_line($ex->getMessage(), 80);

                    continue;
                }
                if ($got === 'unclassified' || ! preg_match('/^[a-z][a-z_]*$/', $got)) {
                    $problemi[] = $inChiaro($e)." → {$got}";
                }
            }

            return $problemi === [] ?: $c->fail(
                implode('; ', $problemi),
                'ogni riga ha una classe: none se non è un fallimento, policy_block per un deny che non conosci, hook_error per *.error',
            );
        },
    ],

    // La versione consegnata: laravelday-2026 è un tag fatto a mano (release e tag-checkpoints sono
    // congelati a cp-00), quindi qualcuno deve accorgersi se cp-09 si sposta e lui no. La CI.
    'allineamento' => [
        'tag laravelday-2026 sullo stesso commit di cp-09' => function (Ctx $c) {
            $lv = W\git_out($c->root, 'rev-parse', '--verify', '--quiet', 'refs/tags/laravelday-2026^{commit}');
            if ($lv === null) {
                return $c->skip('il tag non c\'è (arriva con il rilascio di fine giornata)');
            }
            $cp = W\git_out($c->root, 'rev-parse', '--verify', '--quiet', 'refs/tags/cp-09^{commit}');

            return $cp === $lv ?: $c->fail(
                'laravelday-2026 è su '.substr($lv, 0, 12).', cp-09 su '.($cp === null ? 'niente' : substr($cp, 0, 12)),
                'nel repo di build: git tag -a -f laravelday-2026 -m "LaravelDay 2026: versione consegnata" cp-09^{commit}',
            );
        },
    ],
];
