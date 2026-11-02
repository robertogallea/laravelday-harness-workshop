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
        foreach ($p->paths() as $path) {
            // Un glob di Grep/Glob (".env*") conta come lettura del file che nomina.
            if ($isEnv($path) || (str_contains($path, '.env') && ! str_contains($path, '.env.example') && glob_match($path, '.env'))) {
                return "Accesso a {$path} negato: contiene segreti. Le variabili sono documentate in .env.example; la configurazione in config/services.php; nei test usa \$this->fakePayments().";
            }
        }
        if ($p->tool === 'shell' && preg_match('/(?<![\w.-])\.env(?!\.example\b)(\.[\w-]+)?(?![\w.-])/', $p->command)) {
            return 'Il comando nomina un file .env: negato, contiene segreti. Leggi .env.example o config/services.php; nei test usa $this->fakePayments().';
        }

        return null;
    },

    'migration.command' => function (Payload $p, array $ctx): ?string {
        if ($p->tool === 'shell' && preg_match('/\bartisan\s+(migrate:(fresh|refresh|reset|rollback)|db:wipe)\b/', $p->command, $m)) {
            return "`{$m[0]}` cancella o riscrive dati: negato. Scrivi una migrazione nuova e additiva e lancia `php artisan migrate`; se serve davvero, chiedilo all'umano con [ATTESA-UMANO].";
        }

        return null;
    },

    'migration.edit-existing' => function (Payload $p, array $ctx) use ($isMigration): ?string {
        foreach ([...$p->writes(), ...$ctx['shell_writes']] as $path) {
            if ($isMigration($path) && $ctx['tracked']($path)) {
                return "{$path} è una migrazione già eseguita: non si modifica. Crea una migrazione nuova e additiva (php artisan make:migration).";
            }
        }

        return null;
    },

    'migration.destructive' => function (Payload $p, array $ctx) use ($isMigration): ?string {
        $guarded = $ctx['config']['guarded_columns'];
        foreach ($p->added as $path => $text) {
            if (! $isMigration($path)) {
                continue;
            }
            // Solo up(): dropColumn e dropIfExists in down() sono il normale rollback di una migrazione additiva.
            $up = preg_match('/function\s+up\s*\(.*?(?=function\s+down\s*\(|\z)/s', $text, $m) ? $m[0] : $text;
            foreach (preg_split('/\R/', $up) ?: [] as $line) {
                if (! preg_match('/->change\(\)|->(dropColumn|dropColumns|renameColumn)\(|Schema::(drop|dropIfExists|rename)\(/', $line)) {
                    continue;
                }
                if (str_contains($line, 'dropIfExists') && preg_match('/Schema::create\(/', $up)) {
                    continue; // una tabella creata e ricreata nella stessa migrazione
                }
                $hit = $guarded === ['*'] || array_filter($guarded, fn ($c) => str_contains($line, "'".substr((string) strrchr('.'.$c, '.'), 1)."'"));
                if ($hit) {
                    return "Operazione distruttiva in {$path}: `".trim($line)."`. Solo migrazioni nuove e additive; un cambio di colonna richiede l'umano e un ADR.";
                }
            }
        }

        return null;
    },

    // lesson: L-003 — una dipendenza nuova entra solo con l'umano (approval_required di T-001).
    // Prima delle regole task.*: una scrittura su composer.json la nega questa, non task.out-of-scope.
    'dependency.new' => function (Payload $p, array $ctx): ?string {
        // `composer require`, anche abbreviato (req, requ…) e con le opzioni prima del comando; install, show e dump-autoload passano.
        if ($p->tool === 'shell' && preg_match('/\bcomposer(?:\.phar)?(?:\s+-\S+)*\s+(?:global\s+)?req\w*\b/', $p->command)) {
            return 'Una dipendenza nuova la sceglie l\'umano (approval_required del Task Contract): non installarla tu. Usa quello che c\'è già in composer.json, oppure chiedi con [ATTESA-UMANO]: <pacchetto, versione e perché>.';
        }
        foreach ([...$p->writes(), ...$ctx['shell_writes']] as $path) {
            if (in_array($path, ['composer.json', 'composer.lock'], true)) {
                return "{$path} decide le dipendenze di tutto il team: non lo modifichi tu. Se ti serve un pacchetto, chiedilo con [ATTESA-UMANO]: <pacchetto, versione e perché>.";
            }
        }

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
