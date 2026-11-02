<?php

// .harness/config.php — le manopole dell'harness. Quando porti l'harness su un altro
// progetto riscrivi questo file e .harness/rules/; bin/ e lib/ restano come sono.

return [
    // Scrivere qui richiede un Task Contract approvato (D4: tutto il codice dell'app).
    // tests/, docs/ e le bozze in .harness/tasks restano liberi: l'agente può preparare
    // contract e test prima dell'approvazione, ma non codice né migrazioni.
    'gated_paths' => ['app/', 'database/', 'routes/', 'config/'],

    // Sempre scrivibili, anche fuori da scope.paths: bozze di contract e memoria.
    'always_writable' => ['.harness/tasks/*.md', '.harness/memory/*.md'],

    // L'agente non modifica il proprio harness né le istruzioni che lo guidano: se pensa
    // che vadano cambiati lo scrive in .harness/memory/progress.md, e decide l'umano.
    'harness_paths' => [
        '.harness/bin/**', '.harness/lib/**', '.harness/rules/**', '.harness/config.php',
        '.harness/checkpoints/**', '.harness/fixtures/**', '.harness/state/**', '.harness/runs/**',
        '.claude/**', '.codex/**', '.agents/**', '.mcp.json', 'AGENTS.md', 'CLAUDE.md', 'workshop/**',
    ],

    // Colonne protette dalle operazioni distruttive nelle migrazioni. ['*'] = tutte.
    // Con un elenco: ['refunds.amount', 'orders.amount'].
    'guarded_columns' => ['*'],

    // Stop (D5): con questo marcatore nell'ultimo messaggio l'agente sta aspettando l'umano.
    'waiting_marker' => '[ATTESA-UMANO]',
    // Stop rossi consecutivi prima dell'escalation: blocca al 1° e al 2°, al 3° passa all'umano.
    'retry_budget' => 3,
    // affected-tests: 1 = test diretti e a distanza 1 (con 2 una modifica a Order riporta quasi tutta la suite).
    'affected_depth' => 1,
    // Secondi. Lo stop si ferma prima del timeout dell'adattatore (600 s) e scrive NON VERIFICATO;
    // il post-edit prima dei suoi 120 s, e lascia perdere i comandi che restano.
    'timeouts' => ['scan' => 30, 'affected' => 30, 'tests' => 300, 'phpstan' => 90, 'pint' => 30, 'stop' => 540, 'post_edit' => 100],
    'max_context_chars' => 1500,

    // I comandi della verifica, lanciati dalla root senza shell. Su un altro progetto si cambiano qui.
    'tools' => [
        'pest' => ['php', 'vendor/bin/pest'],
        'pint' => ['php', 'vendor/bin/pint'],
        'phpstan' => ['php', 'vendor/bin/phpstan', 'analyse', '--error-format=raw', '--no-progress'],
        'scan' => ['php', 'artisan', 'necromancer:scan', '-q'],
        'affected' => ['php', 'artisan', 'necromancer:affected-tests', '--stdin', '--paths'],
    ],
];
