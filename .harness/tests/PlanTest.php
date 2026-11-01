<?php

use function Workshop\plan_inizia;
use function Workshop\plan_prendi;
use function Workshop\plan_salta;
use function Workshop\forbidden_in_plan;

require_once __DIR__.'/../lib/workshop.php';

function commands(array $plan): array
{
    return array_values(array_map(
        fn (array $s) => implode(' ', $s['cmd']),
        array_filter($plan, fn (array $s) => $s['type'] === 'run'),
    ));
}

it('starts an exercise: commit the closed work, bring the ingresso, verify the previous exercise', function () {
    $plan = plan_inizia([
        'esercizio' => 'E2',
        'tag' => 'cp-01',
        'dirty' => true,
        'precedente' => 'E1',
        'ingresso' => ['docs/contracts', '.harness/checkpoints/cp-02.php'],
        'gia_iniziati' => [],
    ]);

    expect(commands($plan))->toBe([
        'git add -A',
        'git commit -m E1: lavoro mio',
        'git checkout cp-01 -- docs/contracts .harness/checkpoints/cp-02.php',
        'git add -A',
        'git commit -m ingresso E2 da cp-01',
        'php .harness/bin/verify E1',
    ]);
    expect(forbidden_in_plan($plan))->toBe([]);
});

it('does not commit a clean tree and keeps starter files the participant already started', function () {
    $plan = plan_inizia([
        'esercizio' => 'E1',
        'tag' => 'cp-00',
        'dirty' => false,
        'precedente' => null,
        'ingresso' => ['.harness/memory/facts.md', 'workshop/esercizi/E1.md'],
        'gia_iniziati' => ['.harness/memory/facts.md'],
    ]);

    expect(commands($plan))->toBe([
        'git checkout cp-00 -- workshop/esercizi/E1.md',
        'git add -A',
        'git commit -m ingresso E1 da cp-00',
    ]);
    $notes = array_column(array_filter($plan, fn ($s) => $s['type'] === 'note'), 'text');
    expect(implode("\n", $notes))->toContain('.harness/memory/facts.md');
});

it('takes the solution of one exercise without leaving the branch', function () {
    $plan = plan_prendi([
        'esercizio' => 'E1',
        'tag' => 'cp-01',
        'dirty' => true,
        'uscita' => ['.harness/memory/facts.md'],
        'sovrascritti' => ['.harness/memory/facts.md'],
        'stato' => ['.harness/state/abc.json'],
    ]);

    expect(commands($plan))->toBe([
        'git add -A',
        'git commit -m wip prima di prendere E1',
        'git checkout cp-01 -- .harness/memory/facts.md',
        'git add -A',
        'git commit -m soluzione di E1 presa da cp-01',
        'php .harness/bin/verify cp-01',
    ]);
    expect(array_column(array_filter($plan, fn ($s) => $s['type'] === 'rm'), 'path'))->toBe(['.harness/state/abc.json']);
    $notes = implode("\n", array_column(array_filter($plan, fn ($s) => $s['type'] === 'note'), 'text'));
    expect($notes)->toContain('.harness/memory/facts.md');
    expect(forbidden_in_plan($plan))->toBe([]);
});

it('jumps to a checkpoint on a new branch, keeping the old one', function () {
    $plan = plan_salta([
        'tag' => 'cp-01',
        'dirty' => true,
        'branch' => 'mio-cp-01',
        'stato' => [],
    ]);

    expect(commands($plan))->toBe([
        'git add -A',
        'git commit -m wip prima del salto a cp-01',
        'git switch -c mio-cp-01 cp-01',
        'php .harness/bin/verify cp-01',
    ]);
    expect(forbidden_in_plan($plan))->toBe([]);
});

it('flags destructive git commands in a plan', function () {
    $plan = [
        ['type' => 'run', 'cmd' => ['git', 'reset', '--hard', 'cp-05']],
        ['type' => 'run', 'cmd' => ['git', 'stash']],
        ['type' => 'run', 'cmd' => ['git', 'clean', '-fdx']],
        ['type' => 'run', 'cmd' => ['git', 'switch', '--detach', 'cp-05']],
        ['type' => 'run', 'cmd' => ['git', 'status']],
    ];

    expect(forbidden_in_plan($plan))->toHaveCount(4);
});
