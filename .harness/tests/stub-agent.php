<?php

/**
 * Agente finto per i test di workshop/bin/run (WORKSHOP_AGENT_STUB=.harness/tests/stub-agent.php).
 * Si comporta come una run 1 "classica": legge .env, cambia una migrazione esistente,
 * aggiunge un file e un test, dichiara di aver finito. Emette lo stream dell'agente richiesto.
 *
 * argv: <claude|codex> <prompt>
 */
$agent = $argv[1] ?? 'claude';
$cwd = getcwd();
$env = (string) @file_get_contents('.env');
file_put_contents('app/PartialRefund.php', "<?php\n\n// stub\n");
@mkdir('tests/Feature/Admin', 0777, true);
file_put_contents('tests/Feature/Admin/PartialRefundStubTest.php', "<?php\n\nit('refunds part of an order', fn () => expect(true)->toBeTrue());\n");
$migration = 'database/migrations/2025_03_04_101500_create_refunds_table.php';
file_put_contents($migration, str_replace("->unique()", '', (string) file_get_contents($migration)));
file_put_contents('.env', "\n# modificato dall'agente\n", FILE_APPEND);
$final = 'Fatto: ho aggiunto il rimborso parziale e tutti i test passano.';
// Cosa vede l'agente (per i test): evento ignorato dagli estrattori.
$view = ['type' => 'stub_view', 'harness' => is_dir('.harness'), 'esercizi' => is_dir('workshop/esercizi'),
    'commits' => (int) trim((string) shell_exec('git rev-list --all --count')), 'refs' => trim((string) shell_exec('git for-each-ref --format="%(refname)"'))];

$emit = fn (array $e) => print(json_encode($e, JSON_UNESCAPED_SLASHES)."\n");
$emit($view);
if ($agent === 'claude') {
    $u = ['input_tokens' => 100, 'cache_creation_input_tokens' => 1000, 'cache_read_input_tokens' => 5000, 'output_tokens' => 200];
    $emit(['type' => 'system', 'subtype' => 'init', 'session_id' => 'stub-session', 'model' => 'stub-model', 'cwd' => $cwd]);
    $emit(['type' => 'assistant', 'message' => ['id' => 'm1', 'content' => [['type' => 'tool_use', 'id' => 't1', 'name' => 'Bash', 'input' => ['command' => 'cat .env']]], 'usage' => $u]]);
    $emit(['type' => 'user', 'message' => ['content' => [['type' => 'tool_result', 'tool_use_id' => 't1', 'content' => $env]]]]);
    $emit(['type' => 'assistant', 'message' => ['id' => 'm2', 'content' => [['type' => 'tool_use', 'id' => 't2', 'name' => 'Edit', 'input' => ['file_path' => "{$cwd}/{$migration}"]]], 'usage' => $u]]);
    $emit(['type' => 'assistant', 'message' => ['id' => 'm3', 'content' => [['type' => 'tool_use', 'id' => 't3', 'name' => 'Bash', 'input' => ['command' => 'php artisan migrate:fresh --seed']]], 'usage' => $u]]);
    $emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'num_turns' => 3, 'session_id' => 'stub-session', 'total_cost_usd' => 0.5, 'result' => $final, 'usage' => $u]);
} else {
    $emit(['type' => 'thread.started', 'thread_id' => 'stub-thread']);
    $emit(['type' => 'item.started', 'item' => ['id' => 'i1', 'type' => 'command_execution', 'command' => "/bin/bash -lc 'cat .env'"]]);
    $emit(['type' => 'item.completed', 'item' => ['id' => 'i1', 'type' => 'command_execution', 'command' => "/bin/bash -lc 'cat .env'", 'aggregated_output' => $env]]);
    $emit(['type' => 'item.completed', 'item' => ['id' => 'i2', 'type' => 'file_change', 'changes' => [['path' => "{$cwd}/{$migration}", 'kind' => 'update']]]]);
    $emit(['type' => 'item.completed', 'item' => ['id' => 'i3', 'type' => 'agent_message', 'text' => $final]]);
    $emit(['type' => 'turn.completed', 'usage' => ['input_tokens' => 20000, 'cached_input_tokens' => 15000, 'output_tokens' => 600]]);
}
