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
    // E9 — TODO: dai un nome al fallimento. Per esempio:
    //   pre_tool.denied con rule migration.*                 → destructive_migration
    //   pre_tool.denied con rule env.access / must-not-change → protected_file
    //   pre_tool.denied con rule task.out-of-scope           → scope_violation
    //   pre_tool.denied con rule task.no-contract            → no_contract
    //   pre_tool.denied con rule task.self-approval          → self_approval
    //   pre_tool.denied con rule harness.tamper              → harness_tamper
    //   *.error                                              → hook_error
    //   un evento che non è un fallimento (pre_tool.allowed) → none
    return 'unclassified';
};
