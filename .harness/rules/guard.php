<?php

declare(strict_types=1);

/*
 * .harness/rules/guard.php — la POLICY del pre-tool: la parte che si riscrive per ogni progetto.
 *
 * Ogni regola è fn (Payload $p, array $ctx): ?string
 *   null     → questa regola non ha niente da dire
 *   stringa  → deny; il testo lo legge l'agente, quindi digli cosa fare invece
 * La prima regola che nega vince. L'id ('env.access') finisce nel motivo, nel trace e nelle Lessons.
 *
 * E5: due famiglie di regole, env.* e migration.*, cinque TODO. Ognuno si chiude da solo e si prova da solo:
 *   php .harness/bin/replay .harness/fixtures/pre-tool/<fixture>.json   (le fixture sono nel TODO)
 *   php .harness/bin/verify E5                                          (tutte, alla fine)
 *
 * $p    Harness\Payload: tool (shell|read|write|patch|other), command, files [{path, op}],
 *       added [path => testo aggiunto], writes(), paths(). Path relativi alla root.
 * $ctx  config  .harness/config.php
 *       task    il Task Contract approvato (o null): id, scope.paths, must_not_change, _path
 *       tracked fn (string $path): bool — il file esisteva al commit base?
 *       shell_writes  path che il comando shell potrebbe scrivere (euristica)
 */

use Harness\Payload;

use function Harness\any_match;
use function Harness\glob_match;

$isEnv = fn (string $path): bool => (bool) preg_match('#(^|/)\.env(\.[^/]+)?$#', $path) && ! str_ends_with($path, '.env.example');
$isMigration = fn (string $path): bool => (bool) preg_match('#^database/migrations/[^/]+\.php$#', $path);

return [
    'harness.tamper' => function (Payload $p, array $ctx): ?string {
        foreach ([...$p->writes(), ...$ctx['shell_writes']] as $path) {
            if (any_match($ctx['config']['harness_paths'], $path)) {
                return "{$path} fa parte dell'harness: non lo modifichi tu. Se pensi che vada cambiato, scrivilo in .harness/memory/progress.md e chiedilo all'umano.";
            }
        }

        return null;
    },

    'env.access' => function (Payload $p, array $ctx) use ($isEnv): ?string {
        // E5 — TODO 1: nega se uno dei file toccati ($p->paths(), letture comprese) è un .env.
        //              Usa $isEnv($path): .env.example deve restare leggibile.
        //              Diventano verdi: claude-read-env, claude-grep-env (restano verdi: claude-read-env-example).
        // E5 — TODO 2: Codex non ha un tool Read, le letture passano dalla shell.
        //              Nega se $p->tool === 'shell' e $p->command nomina un file .env (cat .env, grep X .env,
        //              cat .env | grep …), ma non .env.example.
        //              Diventano verdi: codex-cat-env, codex-cat-env-pipe, claude-bash-cat-env, claude-bash-php-env (restano verdi: codex-cat-env-example).
        // Il motivo lo legge l'agente: digli dove trovare quello che cerca invece.
        return null;
    },

    'migration.command' => function (Payload $p, array $ctx): ?string {
        // E5 — TODO 3: nega i comandi che cancellano o riscrivono dati (solo se $p->tool === 'shell'):
        //              artisan migrate:fresh | migrate:refresh | migrate:reset | migrate:rollback, db:wipe.
        //              `php artisan migrate` (in avanti) resta permesso.
        //              Diventa verde: codex-migrate-fresh (resta verde: codex-migrate).
        return null;
    },

    'migration.edit-existing' => function (Payload $p, array $ctx) use ($isMigration): ?string {
        // E5 — TODO 4: nega la scrittura di una migrazione che esisteva già al commit base:
        //              $isMigration($path) && $ctx['tracked']($path). Va creata una migrazione nuova.
        //              Guarda $p->writes() e anche $ctx['shell_writes'] (sed -i, cp, > …).
        //              Diventano verdi: claude-edit-existing-migration, codex-patch-existing-migration.
        return null;
    },

    'migration.destructive' => function (Payload $p, array $ctx) use ($isMigration): ?string {
        // E5 — TODO 5: nel testo aggiunto a una migrazione ($p->added[$path]), dentro up():
        //              ->change(), dropColumn, renameColumn, Schema::drop / dropIfExists / rename.
        //              dropUnique è permesso; in down() il rollback è normale (perché?).
        //              $ctx['config']['guarded_columns'] dice quali colonne proteggere (['*'] = tutte).
        //              Diventano verdi: codex-patch-amount-change, claude-write-migration-drop-column
        //              (resta verde: codex-patch-additive-ok, che fa dropUnique e dropColumn in down()).
        return null;
    },

    'task.self-approval' => function (Payload $p, array $ctx): ?string {
        $task = $ctx['task'];
        foreach ([...$p->writes(), ...$ctx['shell_writes']] as $path) {
            if (! str_starts_with($path, '.harness/tasks/')) {
                continue;
            }
            if ($task !== null && $path === $task['_path']) {
                return "{$path} è approvato e non cambia più: proponi la modifica (es. allargare lo scope) in chat o in .harness/memory/progress.md e aspetta l'umano.";
            }
            if (preg_match('/^\s*status:\s*["\']?approved/m', $p->added[$path] ?? '')) {
                return "Non puoi approvare un Task Contract: lascia status: draft e chiedi l'approvazione all'umano con [ATTESA-UMANO].";
            }
        }
        $scriveTask = array_filter($ctx['shell_writes'], fn ($path) => str_starts_with($path, '.harness/tasks/')) !== [];
        if ($scriveTask && str_contains($p->command, 'approved')) {
            return "Non puoi approvare un Task Contract, nemmeno dalla shell: lascia status: draft e chiedi l'approvazione all'umano.";
        }

        return null;
    },

    // Le regole task.* guardano anche le scritture dalla shell (cat > …, sed -i, cp, …). È un'euristica:
    // lo stop confronta comunque il git diff con il contract.
    'task.must-not-change' => function (Payload $p, array $ctx): ?string {
        $task = $ctx['task'];
        foreach ([...$p->writes(), ...$ctx['shell_writes']] as $path) {
            if ($task !== null && any_match($task['must_not_change'] ?? [], $path)) {
                return "{$path} è in must_not_change di {$task['id']}: non si tocca. Se il task lo richiede davvero, fermati e chiedilo con [ATTESA-UMANO].";
            }
        }

        return null;
    },

    'task.no-contract' => function (Payload $p, array $ctx): ?string {
        if ($ctx['task'] !== null) {
            return null;
        }
        foreach ([...$p->writes(), ...$ctx['shell_writes']] as $path) {
            foreach ($ctx['config']['gated_paths'] as $gated) {
                if (str_starts_with($path, $gated)) {
                    return "Nessun Task Contract approvato: prima di modificare {$path} abbozza .harness/tasks/T-NNN.md (status: draft) e chiedi l'approvazione all'umano.";
                }
            }
        }

        return null;
    },

    'task.out-of-scope' => function (Payload $p, array $ctx): ?string {
        $task = $ctx['task'];
        if ($task === null) {
            return null;
        }
        // Dalla shell contano solo i path gated: un `> /tmp/out.txt` o un log in storage/ non sono il task.
        $gated = fn (string $path): bool => array_filter($ctx['config']['gated_paths'], fn ($g) => str_starts_with($path, $g)) !== [];
        $allowed = array_merge($task['scope']['paths'] ?? [], $ctx['config']['always_writable']);
        foreach ($p->writes() as $path) {
            if (! any_match($allowed, $path)) {
                return "{$path} è fuori da scope.paths di {$task['id']}. Se serve, proponi di allargare lo scope e aspetta l'approvazione.";
            }
        }
        // Un target della shell può essere una cartella (mkdir -p app/Actions/Refunds): basta che lo scope copra il suo contenuto.
        foreach (array_filter($ctx['shell_writes'], $gated) as $path) {
            if (! any_match($allowed, $path) && ! any_match($allowed, $path.'/x')) {
                return "{$path} è fuori da scope.paths di {$task['id']}. Se serve, proponi di allargare lo scope e aspetta l'approvazione.";
            }
        }

        return null;
    },
];
