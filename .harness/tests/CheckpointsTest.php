<?php

use function Workshop\cp_for;
use function Workshop\exercise_for;
use function Workshop\previous_cp;
use function Workshop\sort_cps;
use function Workshop\collisions;
use function Workshop\undeclared;
use function Workshop\tags_from_history;

require_once __DIR__.'/../lib/workshop.php';

it('maps exercises to the checkpoint that closes them', function () {
    expect(cp_for('E1'))->toBe('cp-01')
        ->and(cp_for('E2b'))->toBe('cp-02b')
        ->and(cp_for('e9'))->toBe('cp-09')
        ->and(exercise_for('cp-02b'))->toBe('E2b')
        ->and(exercise_for('cp-01'))->toBe('E1')
        ->and(exercise_for('cp-00'))->toBeNull();
});

it('rejects names that are not exercises', function () {
    cp_for('cp-01');
})->throws(InvalidArgumentException::class);

it('orders checkpoints naturally, with cp-02b between cp-02 and cp-03', function () {
    expect(sort_cps(['cp-03', 'cp-02b', 'cp-10', 'cp-00', 'cp-02']))
        ->toBe(['cp-00', 'cp-02', 'cp-02b', 'cp-03', 'cp-10']);
});

it('finds the checkpoint that carries the ingresso of an exercise', function () {
    expect(previous_cp('cp-03', ['cp-00', 'cp-01', 'cp-02', 'cp-02b']))->toBe('cp-02b')
        ->and(previous_cp('cp-01', ['cp-00']))->toBe('cp-00')
        ->and(previous_cp('cp-01', ['cp-00', 'cp-01', 'cp-02']))->toBe('cp-00')
        ->and(previous_cp('cp-00', ['cp-00']))->toBeNull();
});

it('finds ingresso paths that collide with earlier uscite', function () {
    $decls = [
        'cp-01' => ['ingresso' => ['.harness/memory/facts.md'], 'uscita' => ['.harness/memory/facts.md']],
        'cp-02' => ['ingresso' => ['docs/contracts', '.harness/memory/facts.md'], 'uscita' => ['AGENTS.md']],
        'cp-02b' => ['ingresso' => ['docs/adr/0000-template.md', 'AGENTS.md'], 'uscita' => ['docs/adr/0001-x.md']],
        'cp-03' => ['ingresso' => ['docs/adr/0001-x.md/sub'], 'uscita' => []],
    ];

    expect(collisions($decls))->toBe([
        'cp-02' => ['.harness/memory/facts.md'],
        'cp-02b' => ['AGENTS.md'],
        'cp-03' => ['docs/adr/0001-x.md/sub'],
    ]);
});

it('lets an exercise ship a starter file it then writes', function () {
    expect(collisions(['cp-01' => ['ingresso' => ['a.md'], 'uscita' => ['a.md']]]))->toBe([]);
});

it('lists changed paths outside the declared ones', function () {
    expect(undeclared(
        ['.harness/memory/facts.md', 'docs/contracts/INDEX.md', 'app/Models/Order.php'],
        ['.harness/memory/facts.md', 'docs/contracts'],
    ))->toBe(['app/Models/Order.php']);
});

it('tags the last commit of each checkpoint', function () {
    $history = [
        ['sha' => 'a1', 'cp' => null],
        ['sha' => 'a2', 'cp' => null],
        ['sha' => 'b1', 'cp' => 'cp-00'],
        ['sha' => 'c1', 'cp' => 'cp-01'],
        ['sha' => 'c2', 'cp' => 'cp-01'],
        ['sha' => 'd1', 'cp' => 'cp-02'],
    ];

    expect(tags_from_history($history))->toBe(['cp-00' => 'b1', 'cp-01' => 'c2', 'cp-02' => 'd1']);
});

it('refuses a history where checkpoints go backwards', function () {
    tags_from_history([
        ['sha' => 'b1', 'cp' => 'cp-01'],
        ['sha' => 'c1', 'cp' => 'cp-00'],
    ]);
})->throws(RuntimeException::class, 'cp-00');

it('refuses untagged commits after the first checkpoint commit', function () {
    tags_from_history([
        ['sha' => 'b1', 'cp' => 'cp-00'],
        ['sha' => 'c1', 'cp' => null],
    ]);
})->throws(RuntimeException::class, 'c1');
