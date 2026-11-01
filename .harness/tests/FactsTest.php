<?php

use function Workshop\parse_facts;
use function Workshop\read_only_problem;

require_once __DIR__.'/../lib/workshop.php';

it('parses the facts table, commands without backticks', function () {
    $md = <<<'MD'
    # Facts

    Testo libero.

    | Fatto | Come verificarlo | Verificato |
    |---|---|---|
    | _Esempio:_ i test usano SQLite. | `grep -n DB_DATABASE phpunit.xml` | 2026-11-01 |
    | Gli importi sono in euro. | `php artisan model:show Refund` | |
    | Con pipe | `php artisan route:list -v \| grep idempotent` | |
    | | | |
    MD;

    expect(parse_facts($md))->toBe([
        ['fatto' => '_Esempio:_ i test usano SQLite.', 'comando' => 'grep -n DB_DATABASE phpunit.xml', 'verificato' => '2026-11-01', 'esempio' => true],
        ['fatto' => 'Gli importi sono in euro.', 'comando' => 'php artisan model:show Refund', 'verificato' => '', 'esempio' => false],
        ['fatto' => 'Con pipe', 'comando' => 'php artisan route:list -v | grep idempotent', 'verificato' => '', 'esempio' => false],
    ]);
});

it('returns no rows when there is no facts table', function () {
    expect(parse_facts("# Facts\n\nniente\n"))->toBe([]);
});

it('accepts read-only commands', function (string $cmd) {
    expect(read_only_problem($cmd))->toBeNull();
})->with([
    'php artisan model:show Refund',
    'php artisan route:list --path=admin/orders -v',
    'php artisan db:show',
    'php artisan about --only=environment',
    'git grep -n -e "isStaff()" -e "@can(\'refund\'" -- app resources',
    'grep -rn "can(\'refund\'" resources/',
    'php artisan route:list -v | grep idempotent',
    'cat tests/Pest.php',
    'vendor/bin/pest --list-tests',
    'php vendor/bin/pest --filter=FakePaymentGateway',
    'grep -n PAYMENTS .env.example',
    'grep -nE "isStaff|can\(" app/Models/User.php',
    'grep -n "a;b" README.md',
]);

it('rejects commands that can write, chain, or read secrets', function (string $cmd, string $why) {
    expect(read_only_problem($cmd))->toContain($why);
})->with([
    ['', 'vuoto'],
    ['rm -rf vendor', 'rm'],
    ['php artisan migrate:fresh', 'migrate:fresh'],
    ['php artisan tinker --execute="User::truncate()"', 'tinker'],
    ['cat .env', '.env'],
    ['grep -n KEY .env', '.env'],
    ['grep -rn x . > out.txt', '>'],
    ['php artisan about; rm x', ';'],
    ['php artisan about && rm x', '&'],
    ['php artisan about || rm x', '|'],
    ['echo $(whoami)', '$('],
    ['echo `whoami`', '`'],
    ['git diff --output=x.patch', '--output'],
    ['git push', 'git push'],
    ['php -r "unlink(\'x\');"', 'php -r'],
    ['php artisan route:list | sh', 'sh'],
]);
