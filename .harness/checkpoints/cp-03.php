<?php

// E3 — Behavioral Contract: il rimborso parziale. Arriva con cp-02b (ingresso), la soluzione è in cp-03.
// Niente costanti né funzioni con nome: il file si può caricare più volte.

use Workshop as W;
use Workshop\Ctx;

$contract = 'docs/contracts/refunds.partial.md';
$cancel = 'app/Actions/Orders/CancelOrder.php';
$ids = array_map(fn ($n) => "REFUND-INV-{$n}", range(1, 6));

$senzaCommenti = fn (?string $md) => preg_replace('/<!--.*?-->/s', '', (string) $md);

/** Il corpo di una sezione `## Titolo` senza commenti HTML; null se manca. */
$sezione = function (string $md, string $titolo) use ($senzaCommenti): ?string {
    if (! preg_match('/^##\s+'.preg_quote($titolo, '/').'\s*$(.*?)(?=^##\s|\z)/ms', $senzaCommenti($md), $m)) {
        return null;
    }

    return $m[1];
};

/** Celle delle righe di dati di una tabella markdown (intestazione e separatore esclusi). */
$tabella = function (string $md): array {
    $rows = array_values(array_filter(
        array_map('trim', explode("\n", $md)),
        fn ($l) => str_starts_with($l, '|') && ! preg_match('/^\|[\s:|-]+\|$/', $l),
    ));

    return array_map(
        fn ($l) => array_map(fn ($cell) => trim(str_replace("\0", '|', $cell)), explode('|', trim(str_replace('\|', "\0", $l), '|'))),
        array_slice($rows, 1),
    );
};

/** Riga degli invarianti per ID: [regola, group]. */
$invarianti = function (Ctx $c) use ($contract, $sezione, $tabella): array {
    $out = [];
    foreach ($tabella((string) $sezione((string) $c->read($contract), 'Invarianti')) as $cells) {
        if (count($cells) >= 3 && preg_match('/^`?([A-Z]+(?:-[A-Z]+)*-INV-\d+)`?$/', $cells[0], $m)) {
            $out[$m[1]] = ['regola' => $cells[1], 'group' => trim(end($cells), '` ')];
        }
    }

    return $out;
};

/** Da "REFUND-INV-1..6", "REFUND-INV-1…6", "REFUND-INV-1–6" o da un elenco esplicito agli ID. */
$espandi = function (string $testo): array {
    $ids = [];
    preg_match_all('/([A-Z]+(?:-[A-Z]+)*-INV-)(\d+)(?:\s*(?:\.\.|…|–|-)\s*(\d+))?/u', $testo, $m, PREG_SET_ORDER);
    foreach ($m as $x) {
        foreach (range((int) $x[2], (int) ($x[3] ?? '') ?: (int) $x[2]) as $n) {
            $ids[] = $x[1].$n;
        }
    }

    return array_values(array_unique($ids));
};

$glossario = function (Ctx $c) use ($tabella): array {
    return array_map(fn ($cells) => mb_strtolower(trim($cells[0], '*_ ')), $tabella((string) $c->read('docs/glossary.md')));
};

return [
    'esercizio' => 'E3',
    'ingresso' => [
        '.harness/checkpoints/cp-03.php',
        'workshop/esercizi/E3.md',
        $contract,
        'docs/contracts/refunds.full.md',
        'docs/contracts/orders.status.md',
        'docs/contracts/INDEX.md',
        'tests/Feature/Payments/RefundServiceTest.php',
        'tests/Feature/Admin/OrderRefundControllerTest.php',
    ],
    'uscita' => [$contract, 'docs/contracts/INDEX.md', 'docs/glossary.md', $cancel, 'AGENTS.md'],

    'controlli' => [
        'refunds.partial.md: titolo e 6 sezioni' => function (Ctx $c) use ($contract, $sezione, $senzaCommenti) {
            $md = $c->read($contract);
            if ($md === null) {
                return $c->fail('manca '.$contract, 'php workshop/bin/checkpoint inizia E3');
            }
            $mancano = array_values(array_filter(
                ['Input', 'Output', 'Invarianti', 'Side effects', 'Errori', 'Non-goals'],
                fn ($t) => trim((string) $sezione($md, $t)) === '',
            ));
            if (! preg_match('/^#\s+\S/m', $senzaCommenti($md))) {
                array_unshift($mancano, 'titolo (# nome)');
            }

            return $mancano === [] ?: $c->fail('mancano o sono vuote: '.implode(', ', $mancano));
        },
        'invarianti REFUND-INV-1…6, ciascuno con group = ID' => function (Ctx $c) use ($invarianti, $ids) {
            $righe = $invarianti($c);
            $problemi = [];
            foreach ($ids as $id) {
                $r = $righe[$id] ?? null;
                $problemi[] = match (true) {
                    $r === null => "{$id} manca",
                    mb_strlen($r['regola']) < 20 || stripos($r['regola'], 'da scrivere') !== false => "{$id}: la regola è vuota",
                    $r['group'] !== $id => "{$id}: il group è «{$r['group']}»",
                    default => null,
                };
            }
            $problemi = array_values(array_filter($problemi));

            return $problemi === [] ?: $c->fail(implode('; ', $problemi));
        },
        'REFUND-INV-5 cita Idempotency-Key' => function (Ctx $c) use ($invarianti) {
            $r = $invarianti($c)['REFUND-INV-5'] ?? null;

            return ($r !== null && str_contains($r['regola'], 'Idempotency-Key'))
                ?: $c->fail('la regola di REFUND-INV-5 deve dire da dove viene la chiave: l\'header Idempotency-Key');
        },
        'Errori cita REFUND-INV-2 e REFUND-INV-5' => function (Ctx $c) use ($contract, $sezione) {
            $errori = (string) $sezione((string) $c->read($contract), 'Errori');
            $mancano = array_values(array_filter(['REFUND-INV-2', 'REFUND-INV-5'], fn ($id) => ! preg_match('/'.$id.'(?!\d)/', $errori)));

            return $mancano === [] ?: $c->fail('nessun errore per: '.implode(', ', $mancano));
        },
        'INDEX.md elenca refunds.partial.md con i 6 ID' => function (Ctx $c) use ($tabella, $espandi, $ids) {
            foreach ($tabella((string) $c->read('docs/contracts/INDEX.md')) as $cells) {
                $riga = implode(' | ', $cells);
                if (str_contains($riga, 'refunds.partial.md')) {
                    $mancano = array_values(array_diff($ids, $espandi($riga)));

                    return $mancano === [] ?: $c->fail('nella riga mancano: '.implode(', ', $mancano));
                }
            }

            return $c->fail('nessuna riga con [refunds.partial.md](refunds.partial.md)');
        },
        '@contract di CancelOrder punta a un file esistente' => function (Ctx $c) use ($cancel) {
            if (! preg_match('/^\s*\*\s*@contract\s+(\S+)/m', (string) $c->read($cancel), $m)) {
                return $c->fail("nessun @contract nel docblock di {$cancel}");
            }

            return $c->exists($m[1]) ?: $c->fail("@contract {$m[1]}: il file non esiste");
        },
        'glossario: saldo rimborsabile e chiave di idempotenza' => function (Ctx $c) use ($glossario) {
            $termini = $glossario($c);
            $mancano = array_values(array_filter(
                ['saldo rimborsabile', 'chiave di idempotenza'],
                fn ($t) => array_filter($termini, fn ($x) => str_contains($x, $t)) === [],
            ));

            return $mancano === [] ?: $c->fail('mancano in docs/glossary.md (prima colonna): '.implode(', ', $mancano));
        },
        'Topology cita docs/glossary.md' => fn (Ctx $c) => str_contains((string) $sezione((string) $c->read('AGENTS.md'), 'Topology'), 'docs/glossary.md')
            ?: $c->fail('aggiungi in ## Topology una riga `docs/glossary.md` — quando aprirlo'),
        'app/ uguale a cp-00, salvo i docblock @contract' => function (Ctx $c) {
            if (W\git_out($c->root, 'rev-parse', '--verify', '--quiet', 'refs/tags/cp-00') === null) {
                return $c->fail('il tag cp-00 non c\'è in locale', 'git fetch origin --tags --force');
            }
            $drift = W\app_drift((string) W\git_out($c->root, 'diff', '--no-renames', 'cp-00', '--', 'app'));

            return $drift === [] ?: $c->fail('codice cambiato in: '.implode(', ', $drift), 'git checkout cp-00 -- '.implode(' ', $drift).' (poi rimetti il docblock @contract)');
        },
    ],
];
