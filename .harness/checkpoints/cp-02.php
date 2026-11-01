<?php

// E2 — Riparazione di leggibilità: AGENTS.md. Arriva con cp-01 (ingresso), la soluzione è in cp-02.
// Niente costanti né funzioni con nome: il file si può caricare più volte.

use Workshop\Ctx;

$sezioni = ['Topology', 'Commands', 'Environment', 'Pitfalls', 'Conflicts'];

/** Il corpo di una sezione `## Titolo` di AGENTS.md, senza commenti HTML; null se manca. */
$sezione = function (Ctx $c, string $titolo): ?string {
    $md = preg_replace('/<!--.*?-->/s', '', (string) $c->read('AGENTS.md'));
    if (! preg_match('/^##\s+'.preg_quote($titolo, '/').'\s*$(.*?)(?=^##\s|\z)/ms', $md, $m)) {
        return null;
    }

    return $m[1];
};

/** Righe di dati di una tabella markdown (intestazione e separatore esclusi). */
$righe = function (string $md): array {
    $rows = array_values(array_filter(
        array_map('trim', explode("\n", $md)),
        fn ($l) => str_starts_with($l, '|') && ! preg_match('/^\|[\s:|-]+\|$/', $l),
    ));

    return array_slice($rows, 1);
};

return [
    'esercizio' => 'E2',
    'ingresso' => [
        '.harness/checkpoints/cp-02.php',
        'workshop/esercizi/E2.md',
        'docs/contracts/INDEX.md',
        'docs/contracts/orders.cancel.md',
        'tests/Feature/Orders/CancelOrderTest.php',
    ],
    'uscita' => ['AGENTS.md'],

    'controlli' => [
        'AGENTS.md ha le 5 sezioni, in ordine' => function (Ctx $c) use ($sezioni) {
            preg_match_all('/^##\s+(.+?)\s*$/m', (string) $c->read('AGENTS.md'), $m);
            $trovate = array_values(array_intersect($m[1], $sezioni));

            return $trovate === $sezioni ?: $c->fail('trovo: '.($trovate ? implode(', ', $trovate) : 'nessuna').' — servono '.implode(', ', $sezioni));
        },
        'Conflicts: regola della convenzione dichiarata' => function (Ctx $c) use ($sezione) {
            $conflicts = $sezione($c, 'Conflicts');
            if ($conflicts === null) {
                return $c->fail('manca la sezione ## Conflicts');
            }

            return stripos($conflicts, 'convenzione dichiarata') !== false
                ?: $c->fail('manca la regola generale: il codice nuovo segue la convenzione dichiarata (la trovi in workshop/esercizi/E2.md)');
        },
        'Conflicts: almeno 4 righe con RefundService, OrderPolicy, refunds.amount, Idempotency-Key' => function (Ctx $c) use ($sezione, $righe) {
            $conflicts = $sezione($c, 'Conflicts');
            if ($conflicts === null) {
                return $c->fail('manca la sezione ## Conflicts');
            }
            $termini = ['RefundService', 'OrderPolicy', 'refunds.amount', 'Idempotency-Key'];
            $rows = $righe($conflicts);
            $mancano = array_values(array_filter($termini, fn ($t) => ! str_contains($conflicts, $t)));
            if ($mancano !== []) {
                return $c->fail('mancano: '.implode(', ', $mancano));
            }
            // Una riga per Conflict: le righe che nominano almeno un termine devono essere almeno 4.
            $n = count(array_filter($rows, fn ($r) => array_filter($termini, fn ($t) => str_contains($r, $t)) !== []));

            return $n >= 4 ?: $c->fail("i 4 termini stanno in {$n} righe: serve una riga per Conflict (C1–C4)");
        },
        'Topology: ogni percorso esiste' => function (Ctx $c) use ($sezione) {
            $topology = $sezione($c, 'Topology');
            if ($topology === null) {
                return $c->fail('manca la sezione ## Topology');
            }
            preg_match_all('/`([^`\s<>]*\/[^`\s<>]*)`/', $topology, $m);
            $percorsi = array_values(array_unique($m[1]));
            if ($percorsi === []) {
                return $c->fail('nessun percorso fra backtick (es. `app/Actions/`)');
            }
            $mancanti = array_values(array_filter(
                $percorsi,
                fn ($p) => str_contains($p, '*') ? glob($c->path(rtrim($p, '/'))) === [] : ! $c->exists(rtrim($p, '/')),
            ));

            return $mancanti === [] ?: $c->fail('non esistono: '.implode(', ', $mancanti));
        },
        'Commands: riga [ATTESA-UMANO]' => function (Ctx $c) use ($sezione) {
            return str_contains((string) $sezione($c, 'Commands'), '[ATTESA-UMANO]')
                ?: $c->fail('manca la riga del marker [ATTESA-UMANO] in ## Commands (la trovi in workshop/esercizi/E2.md)');
        },
        'Pitfalls: leggi il contract prima di toccare un\'operazione' => function (Ctx $c) use ($sezione) {
            return stripos((string) $sezione($c, 'Pitfalls'), 'contract') !== false
                ?: $c->fail('in ## Pitfalls manca la regola sul Behavioral Contract (docs/contracts/INDEX.md)');
        },
        'CLAUDE.md importa AGENTS.md' => fn (Ctx $c) => str_contains((string) $c->read('CLAUDE.md'), '@AGENTS.md')
            ?: $c->fail('CLAUDE.md deve contenere la riga @AGENTS.md', 'git checkout cp-00 -- CLAUDE.md'),
        'AGENTS.md entro 120 righe' => function (Ctx $c) {
            $n = substr_count(rtrim((string) $c->read('AGENTS.md')), "\n") + 1;

            return $n <= 120 ?: $c->fail("{$n} righe: un AGENTS.md lungo è un manuale, non una mappa");
        },
    ],
];
