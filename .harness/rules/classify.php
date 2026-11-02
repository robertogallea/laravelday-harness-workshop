<?php

declare(strict_types=1);

/*
 * .harness/rules/classify.php — la classe di fallimento di un evento di trace (E9, opzionale).
 *
 * fn (array $event): string — $event è la riga di trace: event, rule, tool, files, …
 * La classe finisce nel campo `class` di ogni riga di .harness/runs/<run>/trace.jsonl:
 * è quello che legge "Ultima volta che è scattato" delle Lessons e le metriche.
 */

return function (array $event): string {
    $name = (string) ($event['event'] ?? '');

    // Un hook andato in errore è un fallimento dell'harness, non dell'agente.
    if (str_ends_with($name, '.error')) {
        return 'hook_error';
    }

    if ($name === 'pre_tool.denied') {
        $rule = (string) ($event['rule'] ?? '');

        return match (true) {
            in_array($rule, ['env.access', 'task.must-not-change'], true) => 'protected_file',
            str_starts_with($rule, 'migration.') => 'destructive_migration',
            $rule === 'task.out-of-scope' => 'scope_violation',
            $rule === 'task.no-contract' => 'no_contract',
            $rule === 'task.self-approval' => 'self_approval',
            $rule === 'harness.tamper' => 'harness_tamper',
            default => 'policy_block', // una regola nuova (L-003) ha comunque una classe
        };
    }

    // Il post-edit non blocca, ma dice cosa ha trovato: un errore di tipo conta più di una riformattazione.
    if ($name === 'post_edit.checked') {
        return match (true) {
            (int) ($event['phpstan_errors'] ?? 0) > 0 => 'static_analysis',
            ($event['pint_fixed'] ?? []) !== [] => 'lint',
            ($event['skipped'] ?? []) !== [] => 'env_unavailable',
            default => 'none',
        };
    }

    // Lo stop porta già le classi del receipt, in ordine di gravità.
    if ($name === 'stop.blocked') {
        $first = is_array($event['classes'] ?? null) ? reset($event['classes']) : false;

        return is_string($first) && $first !== '' ? $first : 'policy_block';
    }
    if ($name === 'stop.escalated') {
        return 'budget_exhausted';
    }

    // pre_tool.allowed, session.started, stop.passed|skipped|waiting: non sono fallimenti.
    return 'none';
};
