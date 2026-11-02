<?php

// E6 — post-edit: commandsFor() e feedback() in rules/post-edit.php. Arriva con cp-05 (ingresso:
// la consegna, il controllo e le fixture del post-edit con i loro file campione; l'impianto,
// bin/post-edit e replay --hook=post-edit, è in cp-04), la soluzione è in cp-06.
// Niente costanti, funzioni con nome o require in cima: il file si può caricare più volte.

use Workshop as W;
use Workshop\Ctx;

return [
    'esercizio' => 'E6',
    'ingresso' => [
        '.harness/checkpoints/cp-06.php',
        'workshop/esercizi/E6.md',
        '.harness/fixtures/post-edit',
    ],
    'uscita' => ['.harness/rules/post-edit.php'],

    'controlli' => [
        // I replay lanciano Pint e PHPStan davvero, sui file campione che mettono e tolgono da app/, database/, tests/.
        'replay del post-edit: tutte le fixture PASS' => function (Ctx $c) {
            if (! $c->exists('.harness/fixtures/post-edit')) {
                return $c->fail('manca .harness/fixtures/post-edit', 'php workshop/bin/checkpoint inizia E6');
            }
            $r = $c->run(['php', '.harness/bin/replay', '--all', '--hook=post-edit', '--json'], 300);
            $righe = W\jsonl($r['out']);
            if ($righe === []) {
                return $c->fail('replay non ha prodotto risultati: '.trim($r['err']), 'php .harness/bin/replay --all --hook=post-edit');
            }
            $fail = array_values(array_filter($righe, fn ($x) => ($x['pass'] ?? false) !== true));
            if ($fail === []) {
                return $c->ok(count($righe).'/'.count($righe).' PASS');
            }
            // Un caso per riga: cosa non torna, e perché la fixture esiste.
            $casi = array_map(function ($x) use ($c) {
                $cosa = [...($x['problems'] ?? []), ...array_map(fn ($t) => "manca «{$t}»", $x['missing'] ?? [])];
                if ($x['got'] !== $x['expect']) {
                    array_unshift($cosa, "atteso {$x['expect']}, ottenuto {$x['got']}".($x['reason'] !== '' ? ': '.mb_substr(preg_replace('/\s+/', ' ', $x['reason']) ?? '', 0, 120) : ''));
                }
                $why = (string) ($c->json(".harness/fixtures/post-edit/{$x['name']}.json")['_why'] ?? '');

                return "\n        · {$x['name']}: ".implode('; ', $cosa).($why !== '' ? "\n          {$why}" : '');
            }, $fail);

            return $c->fail(
                count($fail).'/'.count($righe).' FAIL'.implode('', $casi),
                'php .harness/bin/replay --hook=post-edit --verbose .harness/fixtures/post-edit/<nome>.json   (una fixture, con il contesto che riceve l\'agente)',
            );
        },
    ],
];
