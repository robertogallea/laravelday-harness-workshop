<?php

use function Workshop\app_drift;
use function Workshop\feed_line;
use function Workshop\jsonl;
use function Workshop\metrics_claude;
use function Workshop\metrics_codex;
use function Workshop\parse_args;
use function Workshop\tool_calls;

require_once __DIR__.'/../lib/workshop.php';

/** Stream di `claude -p --output-format stream-json --verbose`, ridotto ai campi che servono. */
function claude_stream(): array
{
    $u = fn (int $in, int $cc, int $cr, int $out) => ['input_tokens' => $in, 'cache_creation_input_tokens' => $cc, 'cache_read_input_tokens' => $cr, 'output_tokens' => $out];

    return [
        ['type' => 'system', 'subtype' => 'init', 'session_id' => 'sess-1', 'model' => 'claude-opus-5-5', 'cwd' => '/tmp/w'],
        ['type' => 'assistant', 'message' => ['id' => 'msg_1', 'model' => 'claude-opus-5-5', 'content' => [['type' => 'text', 'text' => 'Guardo il codice.']], 'usage' => $u(10, 1000, 0, 5)]],
        ['type' => 'assistant', 'message' => ['id' => 'msg_1', 'model' => 'claude-opus-5-5', 'content' => [['type' => 'tool_use', 'id' => 't1', 'name' => 'Bash', 'input' => ['command' => "cat .env\n"]]], 'usage' => $u(10, 1000, 0, 5)]],
        ['type' => 'user', 'message' => ['content' => [['type' => 'tool_result', 'tool_use_id' => 't1', 'content' => 'APP_NAME=x']]]],
        ['type' => 'assistant', 'message' => ['id' => 'msg_2', 'model' => 'claude-opus-5-5', 'content' => [
            ['type' => 'tool_use', 'id' => 't2', 'name' => 'Edit', 'input' => ['file_path' => '/tmp/w/app/Services/RefundService.php', 'old_string' => 'a', 'new_string' => 'b']],
            ['type' => 'tool_use', 'id' => 't3', 'name' => 'Read', 'input' => ['file_path' => '/tmp/w/routes/web.php']],
        ], 'usage' => $u(20, 0, 1000, 50)]],
        ['type' => 'assistant', 'message' => ['id' => 'msg_3', 'model' => 'claude-opus-5-5', 'content' => [['type' => 'text', 'text' => 'Fatto: tutti i test passano.']], 'usage' => $u(5, 0, 1100, 30)]],
        ['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'num_turns' => 4, 'session_id' => 'sess-1', 'total_cost_usd' => 0.1234,
            'result' => 'Fatto: tutti i test passano.',
            'usage' => $u(35, 1000, 2100, 85),
            'modelUsage' => [
                'claude-opus-5-5' => ['inputTokens' => 35, 'outputTokens' => 85, 'cacheReadInputTokens' => 2100, 'cacheCreationInputTokens' => 1000, 'costUSD' => 0.12],
                'claude-haiku-5-5' => ['inputTokens' => 100, 'outputTokens' => 10, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0, 'costUSD' => 0.0034],
            ]],
    ];
}

/** Eventi di `codex exec --json`. */
function codex_exec(): array
{
    return [
        ['type' => 'thread.started', 'thread_id' => 'th-9'],
        ['type' => 'turn.started'],
        ['type' => 'item.completed', 'item' => ['id' => 'item_0', 'type' => 'agent_message', 'text' => 'Leggo il controller.']],
        ['type' => 'item.started', 'item' => ['id' => 'item_1', 'type' => 'command_execution', 'command' => "/bin/bash -lc 'cat routes/web.php'", 'status' => 'in_progress']],
        ['type' => 'item.completed', 'item' => ['id' => 'item_1', 'type' => 'command_execution', 'command' => "/bin/bash -lc 'cat routes/web.php'", 'exit_code' => 0, 'status' => 'completed']],
        ['type' => 'item.started', 'item' => ['id' => 'item_2', 'type' => 'file_change', 'changes' => [['path' => '/tmp/w/app/Http/Controllers/Admin/X.php', 'kind' => 'add']], 'status' => 'in_progress']],
        ['type' => 'item.completed', 'item' => ['id' => 'item_2', 'type' => 'file_change', 'changes' => [['path' => '/tmp/w/app/Http/Controllers/Admin/X.php', 'kind' => 'add'], ['path' => 'routes/web.php', 'kind' => 'update']], 'status' => 'completed']],
        ['type' => 'item.completed', 'item' => ['id' => 'item_3', 'type' => 'mcp_tool_call', 'server' => 'laravel-necromancer', 'tool' => 'get_affected_tests', 'status' => 'completed']],
        ['type' => 'item.completed', 'item' => ['id' => 'item_4', 'type' => 'error', 'message' => 'warning']],
        ['type' => 'item.completed', 'item' => ['id' => 'item_5', 'type' => 'agent_message', 'text' => 'Ho finito, i test sono verdi.']],
        ['type' => 'turn.completed', 'usage' => ['input_tokens' => 30000, 'cached_input_tokens' => 20000, 'output_tokens' => 400, 'reasoning_output_tokens' => 100]],
    ];
}

/** Righe del rollout di Codex (`$CODEX_HOME/sessions/.../rollout-*.jsonl`). */
function codex_rollout(): array
{
    $usage = fn (int $in, int $cached, int $out) => ['input_tokens' => $in, 'cached_input_tokens' => $cached, 'cache_write_input_tokens' => 0, 'output_tokens' => $out, 'reasoning_output_tokens' => 10, 'total_tokens' => $in + $out];

    return [
        ['type' => 'session_meta', 'payload' => ['id' => 'th-9', 'cwd' => '/tmp/w']],
        ['type' => 'turn_context', 'payload' => ['model' => 'gpt-5.6-terra', 'cwd' => '/tmp/w']],
        ['type' => 'token_usage_record', 'payload' => ['usage' => $usage(14000, 0, 90)]],
        ['type' => 'event_msg', 'payload' => ['type' => 'token_count', 'info' => ['total_token_usage' => $usage(14000, 0, 90)]]],
        ['type' => 'token_usage_record', 'payload' => ['usage' => $usage(16000, 14000, 210)]],
        ['type' => 'event_msg', 'payload' => ['type' => 'token_count', 'info' => ['total_token_usage' => $usage(30000, 14000, 300)]]],
        ['type' => 'token_usage_record', 'payload' => ['usage' => $usage(1, 0, 1)]],
        ['type' => 'event_msg', 'payload' => ['type' => 'token_count', 'info' => ['total_token_usage' => $usage(30001, 14000, 301)]]],
    ];
}

it('parses JSONL and skips lines that are not JSON objects', function () {
    expect(jsonl("{\"a\":1}\nnot json\n\n[1]\n{\"b\":2}\n"))->toBe([['a' => 1], ['b' => 2]]);
});

it('lists the tool calls of a Claude event, with paths relative to the run folder', function () {
    $calls = array_merge(...array_map(fn ($e) => tool_calls('claude', $e, '/tmp/w'), claude_stream()));

    expect($calls)->toBe([
        ['id' => 't1', 'tool' => 'Bash', 'detail' => 'cat .env'],
        ['id' => 't2', 'tool' => 'Edit', 'detail' => 'app/Services/RefundService.php'],
        ['id' => 't3', 'tool' => 'Read', 'detail' => 'routes/web.php'],
    ]);
});

it('lists each Codex tool call once: commands when they start, patches and MCP when they complete', function () {
    $calls = array_merge(...array_map(fn ($e) => tool_calls('codex', $e, '/tmp/w'), codex_exec()));

    expect($calls)->toBe([
        ['id' => 'item_1', 'tool' => 'exec', 'detail' => 'cat routes/web.php'],
        ['id' => 'item_2', 'tool' => 'patch', 'detail' => 'app/Http/Controllers/Admin/X.php, routes/web.php'],
        ['id' => 'item_3', 'tool' => 'laravel-necromancer.get_affected_tests', 'detail' => ''],
    ]);
});

it('prints one short line per tool call', function () {
    expect(feed_line(12, ['tool' => 'Bash', 'detail' => 'php artisan test --filter=Partial']))->toBe('[12] Bash: php artisan test --filter=Partial')
        ->and(feed_line(3, ['tool' => 'x.y', 'detail' => '']))->toBe('[3] x.y')
        ->and(strlen(feed_line(1, ['tool' => 'Bash', 'detail' => str_repeat('a', 500)])))->toBeLessThan(140);
});

it('normalises Claude metrics from the result event, summing every model', function () {
    $m = metrics_claude(claude_stream());

    expect($m)->toMatchArray([
        'agent' => 'claude',
        'model' => 'claude-opus-5-5',
        'session_id' => 'sess-1',
        'requests' => 3,
        'tool_calls' => 3,
        'input_tokens' => 35 + 1000 + 2100 + 100,
        'cached_tokens' => 2100,
        'output_tokens' => 95,
        'cost_usd' => 0.1234,
        'cost_source' => 'agente',
        'num_turns' => 4,
        'completed' => true,
        'final_message' => 'Fatto: tutti i test passano.',
    ]);
});

it('falls back to deduplicated assistant messages when Claude has no result (timeout)', function () {
    $stream = array_slice(claude_stream(), 0, -1);
    $m = metrics_claude($stream);

    // msg_1 compare due volte con lo stesso usage: si conta una volta sola.
    expect($m['requests'])->toBe(3)
        ->and($m['input_tokens'])->toBe((10 + 1000) + (20 + 1000) + (5 + 1100))
        ->and($m['cached_tokens'])->toBe(2100)
        ->and($m['output_tokens'])->toBe(85)
        ->and($m['cost_usd'])->toBeNull()
        ->and($m['completed'])->toBeFalse()
        ->and($m['final_message'])->toBe('Fatto: tutti i test passano.');
});

it('normalises Codex metrics: requests from usage records, tokens from the last cumulative count, cost from the price list', function () {
    $prices = ['gpt-5.6-terra' => ['input' => 2.0, 'cached_input' => 0.2, 'output' => 10.0]];
    $m = metrics_codex(codex_exec(), codex_rollout(), $prices);

    expect($m)->toMatchArray([
        'agent' => 'codex',
        'model' => 'gpt-5.6-terra',
        'session_id' => 'th-9',
        'requests' => 3,
        'tool_calls' => 3,
        'input_tokens' => 30001,
        'cached_tokens' => 14000,
        'output_tokens' => 301,
        'cost_source' => 'listino',
        'completed' => true,
        'final_message' => 'Ho finito, i test sono verdi.',
    ]);
    expect($m['cost_usd'])->toEqualWithDelta((16001 * 2.0 + 14000 * 0.2 + 301 * 10.0) / 1e6, 1e-9);
});

it('uses turn.completed when the Codex rollout is missing, and leaves unknown values empty', function () {
    $m = metrics_codex(codex_exec(), null, []);

    expect($m['requests'])->toBeNull()
        ->and($m['input_tokens'])->toBe(30000)
        ->and($m['cached_tokens'])->toBe(20000)
        ->and($m['output_tokens'])->toBe(400)
        ->and($m['model'])->toBeNull()
        ->and($m['cost_usd'])->toBeNull();
});

it('marks a Codex run without turn.completed as not completed', function () {
    expect(metrics_codex(array_slice(codex_exec(), 0, -1), null, [])['completed'])->toBeFalse();
});

it('accepts in run 2 only @contract docblocks and Pest groups on top of the cp-00 app code', function () {
    $ok = <<<'DIFF'
    diff --git a/app/Services/RefundService.php b/app/Services/RefundService.php
    --- a/app/Services/RefundService.php
    +++ b/app/Services/RefundService.php
    @@ -14,2 +14,6 @@
    +/**
    + * @contract docs/contracts/refunds.full.md
    + */
     final class RefundService
    diff --git a/tests/Feature/Payments/RefundServiceTest.php b/tests/Feature/Payments/RefundServiceTest.php
    --- a/tests/Feature/Payments/RefundServiceTest.php
    +++ b/tests/Feature/Payments/RefundServiceTest.php
    @@ -20,1 +20,1 @@
    -});
    +})->group('REFUND-FULL-INV-1');
    DIFF;
    expect(app_drift($ok))->toBe([]);

    $bad = $ok."\n".<<<'DIFF'
    diff --git a/app/Services/RefundService.php b/app/Services/RefundService.php
    @@ -20,1 +20,1 @@
    -        if (! $actor->isStaff()) {
    +        if (! $actor->can('refund', $order)) {
    DIFF;
    expect(app_drift($bad))->toBe(['app/Services/RefundService.php']);
});

it('splits script arguments into positionals and --options', function () {
    expect(parse_args(['run', '1', '--agent=codex', '--force', '--eval=refs/x=y']))
        ->toBe([['1'], ['agent' => 'codex', 'force' => true, 'eval' => 'refs/x=y']]);
});
