<?php

// E1 — Cosa ha dovuto indovinare. Arriva con cp-00 (ingresso), la soluzione è in cp-01.

use Workshop\Ctx;

use function Workshop\parse_facts;
use function Workshop\read_only_problem;

$facts = '.harness/memory/facts.md';

return [
    'esercizio' => 'E1',
    'ingresso' => [$facts, 'workshop/esercizi/E1.md', '.harness/checkpoints/cp-01.php'],
    'uscita' => [$facts],

    'controlli' => [
        'facts.md ha la tabella Fatto | Come verificarlo' => function (Ctx $c) use ($facts) {
            $md = $c->read($facts);
            if ($md === null) {
                return $c->fail('manca '.$facts, 'php workshop/bin/checkpoint inizia E1');
            }

            return preg_match('/\|\s*Fatto\s*\|\s*Come verificarlo\s*\|/i', $md) === 1
                ?:$c->fail('non trovo la tabella con le colonne "Fatto" e "Come verificarlo"');
        },
        'almeno 5 fatti (la riga d\'esempio non conta)' => function (Ctx $c) use ($facts) {
            $n = count(array_filter(parse_facts((string) $c->read($facts)), fn ($r) => ! $r['esempio']));

            return $n >= 5 ?: $c->fail("ne trovo {$n}: authz, unità di refunds.amount, perimetro del fake, idempotenza, rimborsi per ordine");
        },
        'ogni "come verificarlo" è di sola lettura e termina con exit 0' => function (Ctx $c) use ($facts) {
            $out = [];
            foreach (parse_facts((string) $c->read($facts)) as $i => $row) {
                $titolo = sprintf('  riga %d: %s', $i + 1, $row['comando'] !== '' ? $row['comando'] : mb_strimwidth($row['fatto'], 0, 40, '…'));
                if ($row['comando'] === '') {
                    $out[] = $c->named($titolo, $c->fail('manca il comando che lo riverifica'));
                    continue;
                }
                $problem = read_only_problem($row['comando']);
                if ($problem !== null) {
                    $out[] = $c->named($titolo, $c->fail($problem));
                    continue;
                }
                $r = $c->run($row['comando'], 60);
                $err = trim(strtok(trim($r['err']) ?: trim($r['out']), "\n") ?: '');
                $out[] = $c->named($titolo, match (true) {
                    $r['timed_out'] => $c->fail('oltre 60 s'),
                    $r['exit'] === 0 => $c->ok(),
                    default => $c->fail("exit {$r['exit']}".($err !== '' ? ": {$err}" : '').' (grep senza risultati esce con 1)'),
                });
            }

            return $out ?: $c->fail('nessuna riga da eseguire');
        },
    ],
];
