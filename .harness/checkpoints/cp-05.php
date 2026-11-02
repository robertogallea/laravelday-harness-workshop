<?php

// E5 — pre-hook: le regole env.* e migration.* del guard. Arriva con cp-04 (ingresso: tutto
// l'impianto dell'harness, adattatori compresi, con stop, session-start e receipt), la soluzione è in cp-05.
// Niente costanti, funzioni con nome o require in cima: il file si può caricare più volte.

use Workshop as W;
use Workshop\Ctx;

/** Gli adattatori sono congelati da cp-04: Codex lega l'approvazione degli hook all'hash della definizione. */
$adattatori = [
    '.claude/settings.json' => '800450aff20d8a7b3f2bfe8d76f9e681989a8153c598f9baaec2fbba658cda85',
    '.codex/hooks.json' => 'e5264bac9fbab4c61d9ca5c586418530e00d9b35918ab31b67318fe473278288',
];

/** Il payload di una fixture in una riga: quello che l'agente voleva fare, senza aprire il JSON. */
$inChiaro = function (Ctx $c, string $nome): string {
    $fx = $c->json(".harness/fixtures/pre-tool/{$nome}.json");
    if (! is_array($fx)) {
        return '(fixture illeggibile)';
    }
    if (isset($fx['_stdin'])) {
        $raw = preg_replace('/\s+/', ' ', (string) $fx['_stdin']) ?? '';

        return 'stdin malformato: '.(mb_strlen($raw) > 70 ? mb_substr($raw, 0, 69).'…' : $raw);
    }
    $tool = (string) ($fx['tool_name'] ?? '(senza tool_name)');
    $in = is_array($fx['tool_input'] ?? null) ? $fx['tool_input'] : [];
    $cmd = is_string($in['command'] ?? null) ? $in['command'] : '';
    if ($tool === 'apply_patch' && preg_match_all('/^\*\*\* (Add|Update|Delete) File: (.+)$/m', $cmd, $m, PREG_SET_ORDER)) {
        $righe = array_map(fn ($x) => "{$x[1]} {$x[2]}", $m);
        // Della patch conta cosa aggiunge alla migrazione: la riga più sospetta, se c'è.
        if (preg_match('/^\+.*(->change\(\)|dropColumn|renameColumn|Schema::(drop|rename)).*$/m', $cmd, $r)) {
            $righe[] = trim(substr($r[0], 1));
        }

        return "{$tool}: ".implode(' · ', $righe);
    }
    $path = (string) ($in['file_path'] ?? $in['path'] ?? '');
    $path = str_replace('{{ROOT}}/', '', $path);
    $cosa = $cmd !== '' ? $cmd : $path;
    if ($cosa === '') {
        return "{$tool} (senza path)";
    }
    if ($tool === 'Write' && preg_match('/^.*(->change\(\)|dropColumn|renameColumn|Schema::(drop|rename)).*$/m', (string) ($in['content'] ?? ''), $r)) {
        $cosa .= ' · '.trim($r[0]);
    }

    return "{$tool}: {$cosa}";
};

return [
    'esercizio' => 'E5',
    'ingresso' => [
        '.harness/checkpoints/cp-05.php',
        'workshop/esercizi/E5.md',
        '.harness/bin/pre-tool',
        '.harness/bin/post-edit',
        '.harness/bin/stop',
        '.harness/bin/session-start',
        '.harness/bin/receipt',
        '.harness/bin/replay',
        '.harness/lib/harness.php',
        '.harness/lib/receipt.php',
        '.harness/config.php',
        '.harness/rules/guard.php',
        '.harness/rules/post-edit.php',
        '.harness/rules/receipt.php',
        '.harness/rules/classify.php',
        '.harness/fixtures/pre-tool',
        '.harness/fixtures/stop',
        '.harness/fixtures/session-start',
        '.harness/fixtures/tasks/T-fixture.md',
        '.claude/settings.json',
        '.claude/skills/task-contract',
        '.claude/skills/progress',
        '.codex/hooks.json',
        '.agents/skills/task-contract/SKILL.md',
        '.agents/skills/progress/SKILL.md',
    ],
    'uscita' => ['.harness/rules/guard.php'],

    'doctor' => [
        // L'unico modo di sapere da fuori che l'agente esegue gli hook (su Codex: approvati in /hooks)
        // è che il session-start abbia scritto l'heartbeat. Codex lo esegue al primo messaggio, non all'apertura.
        // Solo con --agent: la CI lancia doctor senza.
        'heartbeat: l\'agente esegue gli hook del progetto' => function (Ctx $c) {
            $agent = getopt('', ['agent:'])['agent'] ?? null;
            if (! in_array($agent, ['claude', 'codex'], true)) {
                return $c->skip('aggiungi --agent=claude oppure --agent=codex');
            }
            $come = $agent === 'codex'
                ? 'apri codex nella cartella del progetto, /hooks → approva i 4 hook, chiudi. Riapri codex e manda «rispondi solo: ok, non toccare nulla» (Codex esegue il SessionStart al primo messaggio, non all\'apertura), chiudi e rilancia doctor'
                : 'apri claude nella cartella del progetto (accetta la fiducia nella cartella), chiudi e rilancia doctor';
            $hb = $c->json('.harness/state/heartbeat.json')[$agent] ?? null;
            $ts = is_array($hb) ? strtotime((string) ($hb['ts'] ?? '')) : false;
            if ($ts === false) {
                return $c->fail("nessun SessionStart di {$agent} visto in questo progetto", $come);
            }
            $giorni = (int) floor((time() - $ts) / 86400);

            return $giorni <= 7 ?: $c->warn("l'ultimo SessionStart di {$agent} è di {$giorni} giorni fa", $come);
        },
        // Con core.symlinks=false (Windows) i symlink diventano file di testo: git non vede differenze, Claude perde le skill.
        'skill di Claude: i symlink di .claude/skills risolvono' => function (Ctx $c) {
            $rotti = array_values(array_filter(
                ['task-contract', 'progress'],
                fn ($nome) => $c->exists(".agents/skills/{$nome}/SKILL.md") && ! is_file($c->path(".claude/skills/{$nome}/SKILL.md")),
            ));

            return $rotti === [] ?: $c->warn(
                'non risolvono: '.implode(', ', array_map(fn ($n) => ".claude/skills/{$n}", $rotti)).' (symlink diventati file?)',
                'git config core.symlinks true && git checkout -- .claude/skills',
            );
        },
    ],

    'controlli' => [
        'replay del pre-tool: tutte le fixture PASS' => function (Ctx $c) use ($inChiaro) {
            if (! $c->exists('.harness/bin/replay')) {
                return $c->fail('manca .harness/bin/replay', 'php workshop/bin/checkpoint inizia E5');
            }
            $r = $c->run(['php', '.harness/bin/replay', '--all', '--hook=pre-tool', '--json'], 120);
            $righe = W\jsonl($r['out']);
            if ($righe === []) {
                return $c->fail('replay non ha prodotto risultati: '.trim($r['err']), 'php .harness/bin/replay --all');
            }
            $fail = array_values(array_filter($righe, fn ($x) => ($x['pass'] ?? false) !== true));

            if ($fail === []) {
                return $c->ok(count($righe).'/'.count($righe).' PASS');
            }
            // Un caso per riga, con il payload in chiaro: si capisce cosa manca senza aprire il JSON.
            $casi = array_map(
                fn ($x) => "\n        · {$x['name']}: atteso {$x['expect']}".($x['rule'] ? " [{$x['rule']}]" : '')
                    .", ottenuto {$x['got']}".($x['got_rule'] ? " [{$x['got_rule']}]" : '')
                    ."\n          ".$inChiaro($c, (string) $x['name']),
                $fail,
            );

            return $c->fail(
                count($fail).'/'.count($righe).' FAIL'.implode('', $casi),
                'php .harness/bin/replay .harness/fixtures/pre-tool/<nome>.json   (una fixture, con il motivo)',
            );
        },
        'replay dello stop e del session-start: tutte PASS (forniti, non si toccano)' => function (Ctx $c) {
            $fail = [];
            foreach (['stop', 'session-start'] as $hook) {
                if (! $c->exists(".harness/fixtures/{$hook}")) {
                    return $c->fail("manca .harness/fixtures/{$hook}", 'php workshop/bin/checkpoint inizia E5');
                }
                $r = $c->run(['php', '.harness/bin/replay', '--all', "--hook={$hook}", '--json'], 120);
                $righe = W\jsonl($r['out']);
                if ($righe === []) {
                    return $c->fail("replay --hook={$hook} non ha prodotto risultati: ".trim($r['err']));
                }
                foreach ($righe as $x) {
                    if (($x['pass'] ?? false) !== true) {
                        $fail[] = "{$hook}/{$x['name']} (atteso {$x['expect']}, ottenuto {$x['got']})";
                    }
                }
            }

            return $fail === [] ?: $c->fail(implode(', ', $fail), 'git checkout cp-04 -- .harness/bin .harness/lib .harness/fixtures');
        },
        'adattatori identici a quelli di cp-04' => function (Ctx $c) use ($adattatori) {
            $diversi = array_keys(array_filter($adattatori, fn ($hash, $path) => ! $c->exists($path) || hash_file('sha256', $c->path($path)) !== $hash, ARRAY_FILTER_USE_BOTH));

            return $diversi === [] ?: $c->fail(
                'cambiati o mancanti: '.implode(', ', $diversi).' (su Codex un hook cambiato va riapprovato in /hooks)',
                'git checkout cp-04 -- '.implode(' ', $diversi),
            );
        },
    ],
];
