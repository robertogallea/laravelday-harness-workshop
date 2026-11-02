<?php

// E4 — Task Contract compilato a metà. Arriva con cp-03 (ingresso), la soluzione è in cp-04.
// Niente costanti, funzioni con nome o require in cima: il file si può caricare più volte,
// anche da una copia temporanea (la CI lo legge dai tag). La libreria dell'harness si carica
// dentro i controlli, dalla root del progetto: verify E4 legge T-001 con lo stesso parser del guard.

use Workshop as W;
use Workshop\Ctx;

$task = '.harness/tasks/T-001.md';
$ids = array_map(fn ($n) => "REFUND-INV-{$n}", range(1, 6));

/** Il T-001 letto dal parser dell'harness: frontmatter + `_body`. [] se manca o non ha frontmatter. */
$leggi = function (Ctx $c) use ($task): array {
    require_once $c->root.'/.harness/lib/harness.php';

    return Harness\read_task($c->path($task));
};

/** Il corpo di una sezione `## Titolo`, senza commenti HTML; '' se manca. */
$sezione = function (string $md, string $titolo): string {
    $md = (string) preg_replace('/<!--.*?-->/s', '', $md);

    return preg_match('/^##\s+'.preg_quote($titolo, '/').'\s*$(.*?)(?=^##\s|\z)/ms', $md, $m) ? $m[1] : '';
};

/** Una lista del frontmatter, solo stringhe. */
$lista = fn (array $t, string $k): array => array_values(array_filter(is_array($t[$k] ?? null) ? $t[$k] : [], 'is_string'));

/** Le fixture del pre-tool che le regole di E5 (env.*, migration.*) devono negare. */
$fixtureDiE5 = function (Ctx $c): array {
    $out = [];
    foreach (glob($c->root.'/.harness/fixtures/pre-tool/*.json') ?: [] as $f) {
        $fx = json_decode((string) file_get_contents($f), true);
        if (is_array($fx) && preg_match('/^(env|migration)\./', (string) ($fx['_rule'] ?? ''))) {
            $out[] = basename($f, '.json');
        }
    }
    sort($out);

    return $out;
};

return [
    'esercizio' => 'E4',
    'ingresso' => [
        '.harness/checkpoints/cp-04.php',
        'workshop/esercizi/E4.md',
        $task,
        '.harness/memory/decisions.md',
        '.harness/memory/progress.md',
        '.harness/memory/lessons.md',
        '.harness/lib/harness.php',
    ],
    'uscita' => [$task],

    'controlli' => [
        'T-001: frontmatter leggibile dal guard' => function (Ctx $c) use ($leggi, $task) {
            if (! $c->exists($task)) {
                return $c->fail("manca {$task}", 'php workshop/bin/checkpoint inizia E4');
            }
            $t = $leggi($c);
            if ($t === []) {
                return $c->fail('nessun frontmatter fra due righe ---');
            }
            $problemi = Harness\task_problems($t);
            if (($t['id'] ?? null) !== 'T-001') {
                $problemi[] = 'id deve essere T-001';
            }

            return $problemi === [] ?: $c->fail(implode('; ', $problemi));
        },
        'must_not_change: segreti, migrazioni esistenti, RefundService, gateway' => function (Ctx $c) use ($leggi, $lista) {
            $mnc = $lista($leggi($c), 'must_not_change');
            $esempi = [
                '.env' => '.env', '.env.testing' => '.env',
                'database/migrations/2025_03_04_101500_create_refunds_table.php' => 'le migrazioni 2025_*',
                'database/migrations/2025_02_10_090000_create_orders_table.php' => 'le migrazioni 2025_*',
                'app/Services/RefundService.php' => 'RefundService',
                'app/Payments/FakePaymentGateway.php' => 'app/Payments', 'app/Payments/HttpPaymentGateway.php' => 'app/Payments',
            ];
            $scoperti = [];
            foreach ($esempi as $path => $cosa) {
                if (! Harness\any_match($mnc, $path)) {
                    $scoperti[$cosa][] = $path;
                }
            }

            return $scoperti === [] ?: $c->fail('non coperti: '.implode('; ', array_map(
                fn ($cosa, $paths) => "{$cosa} (es. {$paths[0]})",
                array_keys($scoperti),
                $scoperti,
            )));
        },
        'must_not_change non blocca il lavoro del task' => function (Ctx $c) use ($leggi, $lista) {
            $mnc = $lista($leggi($c), 'must_not_change');
            $bloccati = array_values(array_filter(
                ['database/migrations/2026_11_14_090000_allow_partial_refunds.php', 'app/Actions/Refunds/RefundOrderPartially.php', 'tests/Feature/Refunds/PartialRefundTest.php'],
                fn ($p) => Harness\any_match($mnc, $p),
            ));

            return $bloccati === [] ?: $c->fail('must_not_change nega anche '.implode(', ', $bloccati).': restringi i glob (es. database/migrations/2025_*)');
        },
        'scope: la migrazione nuova prevista (D3)' => function (Ctx $c) use ($leggi) {
            $scope = $leggi($c)['scope']['paths'] ?? [];
            $scope = is_array($scope) ? array_values(array_filter($scope, 'is_string')) : [];

            return Harness\any_match($scope, 'database/migrations/2026_11_14_090000_allow_partial_refunds.php')
                ?: $c->fail('scope.paths non contiene database/migrations/*_partial_refunds*.php', 'git checkout cp-03 -- .harness/tasks/T-001.md (lo scope è dato)');
        },
        'evidence: un --group per ogni REFUND-INV-1…6, più la suite intera' => function (Ctx $c) use ($leggi, $lista, $ids) {
            $evidence = $lista($leggi($c), 'evidence');
            $problemi = [];
            foreach ($evidence as $cmd) {
                if (preg_match('/--group[= ]\S*,/', $cmd)) {
                    $problemi[] = "«{$cmd}»: con PHPUnit 12 --group=A,B non trova test, serve un --group per ID";
                }
            }
            $mancano = array_values(array_filter($ids, fn ($id) => ! array_filter($evidence, fn ($cmd) => preg_match('/--group[= ]'.$id.'(?![\d,])/', $cmd))));
            if ($mancano !== []) {
                $problemi[] = 'nessun comando per: '.implode(', ', $mancano).' (es. vendor/bin/pest --group=REFUND-INV-2)';
            }
            if (! array_filter($evidence, fn ($cmd) => preg_match('/\b(pest|artisan\s+test)\b/', $cmd) && ! str_contains($cmd, '--group'))) {
                $problemi[] = 'manca la suite intera (vendor/bin/pest): un invariante verde non basta se si rompe il resto';
            }

            return $problemi === [] ?: $c->fail(implode('; ', $problemi));
        },
        'approval_required non vuoto' => fn (Ctx $c) => $lista($leggi($c), 'approval_required') !== []
            ?: $c->fail('scrivi almeno una modifica che vuoi vedere prima che l\'agente la faccia (es. dipendenze composer nuove)'),
        'una motivazione per ogni voce di must_not_change' => function (Ctx $c) use ($leggi, $lista, $sezione) {
            $t = $leggi($c);
            $perche = $sezione((string) ($t['_body'] ?? ''), 'Perché questo perimetro');
            $senza = array_values(array_filter($lista($t, 'must_not_change'), fn ($p) => ! str_contains($perche, "`{$p}`")));

            return $senza === [] ?: $c->fail('in «## Perché questo perimetro» manca la riga per: '.implode(', ', $senza).' (il path fra backtick, poi il perché)');
        },
        'Risultato atteso: la stessa richiesta due volte (Idempotency-Key)' => function (Ctx $c) use ($leggi, $sezione) {
            $r = $sezione((string) ($leggi($c)['_body'] ?? ''), 'Risultato atteso');

            return str_contains($r, 'Idempotency-Key') ?: $c->fail('di\' cosa succede con la stessa Idempotency-Key due volte (REFUND-INV-5)');
        },
        'status: approved (lo cambi tu, non l\'agente)' => function (Ctx $c) use ($leggi) {
            if (($leggi($c)['status'] ?? null) !== 'approved') {
                return $c->fail('T-001 è ancora draft: quando il contract ti convince, approvalo tu (status: approved)');
            }
            try {
                $attivo = Harness\active_task($c->root);
            } catch (Throwable $e) {
                return $c->fail($e->getMessage(), 'lascia approved un solo contract in .harness/tasks/');
            }

            return ($attivo['id'] ?? null) === 'T-001' ?: $c->fail('il guard non vede T-001 come task attivo');
        },
    ],

    'allineamento' => [
        'impianto dell\'harness presente' => function (Ctx $c) {
            $mancano = array_values(array_filter([
                '.harness/bin/pre-tool', '.harness/bin/post-edit', '.harness/bin/stop', '.harness/bin/session-start', '.harness/bin/replay',
                '.harness/config.php', '.harness/rules/guard.php', '.harness/rules/post-edit.php', '.harness/rules/receipt.php', '.harness/rules/classify.php',
                '.harness/fixtures/tasks/T-fixture.md', '.claude/settings.json', '.codex/hooks.json',
            ], fn ($p) => ! $c->exists($p)));

            // L'impianto è l'ingresso di E5: dopo `prendi E4` manca ancora, ed è normale.
            return $mancano === [] ?: $c->warn('mancano: '.implode(', ', $mancano).' (arrivano con l\'ingresso di E5)', 'php workshop/bin/checkpoint inizia E5');
        },
        'replay del pre-tool: falliscono solo le regole di E5' => function (Ctx $c) use ($fixtureDiE5) {
            if (! $c->exists('.harness/bin/replay') || ! $c->exists('.harness/rules/guard.php')) {
                return $c->skip('l\'impianto arriva con php workshop/bin/checkpoint inizia E5');
            }
            $r = $c->run(['php', '.harness/bin/replay', '--all', '--hook=pre-tool', '--json'], 120);
            $fail = [];
            foreach (W\jsonl($r['out']) as $row) {
                if (($row['pass'] ?? true) === false) {
                    $fail[] = (string) $row['name'];
                }
            }
            sort($fail);
            $attesi = $fixtureDiE5($c);
            $inPiu = array_values(array_diff($fail, $attesi));
            $giaFatte = array_values(array_diff($attesi, $fail));
            if ($inPiu !== []) {
                return $c->fail('falliscono anche: '.implode(', ', $inPiu).' (gate, scope o fail-closed)', 'php .harness/bin/replay --all');
            }

            return $giaFatte === [] ?: $c->warn('passano già: '.implode(', ', $giaFatte).'. Hai iniziato E5? Allora usa verify cp-05');
        },
    ],
];
