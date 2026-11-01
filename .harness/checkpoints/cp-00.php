<?php

// cp-00: il "repo medio" della run 1. Nessuna esercitazione si chiude qui.
// Niente costanti né funzioni con nome: il file si può caricare più volte.
// I controlli valgono in tutti i checkpoint; `allineamento` solo con `verify cp-00`.

use Workshop\Ctx;

return [
    'esercizio' => null,

    'controlli' => [
        'test del progetto verdi' => function (Ctx $c) {
            $r = $c->run(['php', 'vendor/bin/pest', '--colors=never'], 600);
            if ($r['exit'] === 0) {
                return true;
            }
            $tail = implode(' ', array_slice(array_filter(explode("\n", trim($r['out']."\n".$r['err']))), -3));

            return $c->fail(trim($tail) ?: 'pest è uscito con '.$r['exit'], 'php artisan test');
        },
        'AGENTS.md presente' => fn (Ctx $c) => $c->exists('AGENTS.md')
            ?: $c->fail('manca', 'git checkout cp-00 -- AGENTS.md'),
        'CLAUDE.md importa AGENTS.md' => fn (Ctx $c) => str_contains((string) $c->read('CLAUDE.md'), '@AGENTS.md')
            ?: $c->fail('CLAUDE.md deve contenere la riga @AGENTS.md', 'git checkout cp-00 -- CLAUDE.md'),
        'laravel/ai assente dal lock' => fn (Ctx $c) => ! str_contains((string) $c->read('composer.lock'), '"name": "laravel/ai"')
            ?: $c->fail('laravel/ai è nel composer.lock', 'git checkout cp-00 -- composer.json composer.lock && composer install'),
        'memoria: facts.md presente' => fn (Ctx $c) => $c->exists('.harness/memory/facts.md')
            ?: $c->fail('manca', 'git checkout cp-00 -- .harness/memory/facts.md'),
    ],

    'allineamento' => [
        'nessun hook registrato (run 1 senza harness)' => function (Ctx $c) {
            $claude = $c->json('.claude/settings.json');
            if (is_array($claude) && array_key_exists('hooks', $claude)) {
                return $c->fail('.claude/settings.json registra degli hook', 'togli la chiave "hooks" da .claude/settings.json');
            }
            if ($c->exists('.codex/hooks.json')) {
                return $c->fail('.codex/hooks.json esiste', 'git rm .codex/hooks.json');
            }

            return true;
        },
    ],
];
