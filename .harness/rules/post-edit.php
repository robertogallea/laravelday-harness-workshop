<?php

declare(strict_types=1);

/*
 * .harness/rules/post-edit.php — cosa controllare dopo una scrittura, e cosa dire all'agente (E6).
 * Il post-edit fallisce aperto: informa, non blocca. Il "fatto" lo decide lo stop.
 *
 * bin/post-edit chiama commandsFor() per ogni file scritto (al massimo 5 per tool call),
 * lancia i comandi nell'ordine, dalla root e senza shell, poi passa tutti gli esiti a feedback().
 * Se nessun file ha comandi (Markdown, Blade), $results è vuoto e feedback() non viene chiamata.
 *
 * $results ha un elemento per ogni comando di ogni file, non uno per file: una Write su un .php
 * ne produce 2 (prima pint, poi phpstan), una patch su 3 file 6, al massimo 10.
 * Ogni elemento ha sempre queste sei chiavi:
 *   path     il file scritto, relativo alla root          'app/Actions/Refunds/RefundOrder.php'
 *   tool     la chiave del comando in commandsFor()       'pint' | 'phpstan'
 *   exit     l'exit code del comando; null se non è partito o è andato in timeout
 *   skipped  null se il comando è finito, altrimenti il perché:
 *            'missing' (vendor/bin/… non c'è) | 'timeout'
 *   changed  true se dopo il comando il file è diverso. Pint che riformatta esce comunque con 0:
 *            è changed a dirlo, non exit
 *   output   stdout + stderr, con i path relativi alla root; '' se il comando non è partito
 *
 * Da Pint serve solo changed: il suo output non si legge. Da PHPStan servono exit e output:
 *   exit 0                                  pulito
 *   exit ≠ 0 e righe file:riga:messaggio    gli errori da riferire
 *   exit ≠ 0 e nessuna di quelle righe      non è riuscito a partire (es. config assente): taci
 * L'output di PHPStan non contiene solo gli errori: "Note: Using configuration file…" c'è anche
 * con exit 0. E cambia con l'ambiente: dentro un agente (Claude Code, Codex) ogni errore finisce con
 * " [identifier=return.type]" e in coda c'è un blocco di istruzioni, da terminale no. Quindi
 * prendi solo le righe file:riga:messaggio, e l'identifier trattalo come facoltativo.
 *
 * Esempio, da terminale: una Write su app/Support/E6Centesimi.php con un errore di tipo:
 *   [
 *     ['path' => 'app/Support/E6Centesimi.php', 'tool' => 'pint', 'exit' => 0,
 *      'skipped' => null, 'changed' => false, 'output' => '… PASS … 1 file'],
 *     ['path' => 'app/Support/E6Centesimi.php', 'tool' => 'phpstan', 'exit' => 1,
 *      'skipped' => null, 'changed' => false,
 *      'output' => "app/Support/E6Centesimi.php:11:Method App\Support\E6Centesimi::inEuro() should return int but returns string.\n\nNote: Using configuration file phpstan.neon."],
 *   ]
 * Se PHPStan non è installato, il suo elemento diventa:
 *     ['path' => 'app/Support/E6Centesimi.php', 'tool' => 'phpstan', 'exit' => null,
 *      'skipped' => 'missing', 'changed' => false, 'output' => '']
 */

return [
    // fn (string $path, array $tools): array<string, list<string>> — i comandi da lanciare sul file
    // appena scritto, per nome. $tools sono i comandi di config.php → tools, per esempio
    // $tools['pint'] = ['php', 'vendor/bin/pint']: aggiungi il path in fondo.
    'commandsFor' => function (string $path, array $tools): array {
        // Gli stessi path di phpstan.neon. Blade finisce in .php ma è un template.
        if (! preg_match('#^(app|database|tests)/.+\.php$#', $path) || str_ends_with($path, '.blade.php')) {
            return [];
        }

        // Prima Pint, che riformatta: PHPStan deve vedere il file com'è dopo, righe comprese.
        return [
            'pint' => [...$tools['pint'], $path],
            'phpstan' => [...$tools['phpstan'], $path],
        ];
    },

    // fn (list<array> $results): ?string — dagli esiti al testo per l'agente (additionalContext).
    'feedback' => function (array $results): ?string {
        $lines = [];
        $shown = 0;
        foreach ($results as $r) {
            if ($r['skipped'] !== null) {
                continue; // uno strumento che manca non è un problema dell'agente
            }
            if ($r['changed']) {
                $lines[] = "Pint ha riformattato {$r['path']}: rileggilo prima di modificarlo di nuovo.";
            }
            if ($r['tool'] === 'phpstan' && $r['exit'] !== 0) {
                preg_match_all('/^.+\.php:(\d+):(.+?)(?: \[identifier=[^\]]+\])?$/m', $r['output'], $m, PREG_SET_ORDER);
                if ($m === []) {
                    continue; // PHPStan non è riuscito a partire: lo dirà lo stop
                }
                $lines[] = "PHPStan, {$r['path']}: ".count($m).(count($m) === 1 ? ' errore' : ' errori');
                foreach ($m as [, $line, $message]) {
                    if (++$shown > 10) { // i primi 10 della tool call, non di ogni file
                        $lines[] = "- … gli altri: vendor/bin/phpstan analyse {$r['path']}";
                        break;
                    }
                    $lines[] = "- riga {$line}: {$message}";
                }
            }
        }
        $text = implode("\n", $lines);

        // Il taglio lo decido io, non l'impianto: meglio perdere la coda di un messaggio che un file.
        return $text === '' ? null : (mb_strlen($text) > 1500 ? mb_substr($text, 0, 1499).'…' : $text);
    },
];
