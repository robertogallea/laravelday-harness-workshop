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
        // E6 — TODO 1: solo i .php sotto app/, database/, tests/ (gli stessi path di phpstan.neon):
        //   prima 'pint' (in modalità fix, che è il default), poi 'phpstan', entrambi sul solo $path.
        //   Per tutto il resto (Markdown, Blade, routes/, config/) nessun comando: [].
        return [];
    },

    // fn (list<array> $results): ?string — dagli esiti al testo per l'agente (additionalContext).
    'feedback' => function (array $results): ?string {
        // E6 — TODO 2: null se è tutto pulito. Altrimenti poche righe, al massimo 1500 caratteri:
        //   - i file che Pint ha riformattato (changed): l'agente li deve rileggere prima di modificarli;
        //   - gli errori di PHPStan (exit ≠ 0 e righe file:riga:messaggio), i primi 10 della tool call,
        //     ciascuno con il file, la riga e il messaggio.
        //   Uno strumento che non è partito (skipped) non è un problema dell'agente: taci.
        return null;
    },
];
