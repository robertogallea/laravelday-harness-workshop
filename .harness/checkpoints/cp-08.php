<?php

// E8 — memoria e flywheel: da una Lesson (L-003) a una regola del guard, con il suo payload
// campione; poi la promozione di una Decision ad ADR 0002. Arriva con cp-07 (ingresso: la
// consegna e questo file), la soluzione è in cp-08. La promozione è comprimibile: WARN, mai FAIL.
// Niente costanti, funzioni con nome o require in cima: il file si può caricare più volte.

use Workshop as W;
use Workshop\Ctx;

$campi = ['Fallimento osservato', 'Fallimento prevenuto', 'Pezzo di harness', 'Quando rimuoverlo', 'Ultima volta che è scattato'];

/** I campi di L-003 in lessons.md (`- **Campo:** testo`); null se L-003 non c'è. */
$lesson = function (Ctx $c): ?array {
    $md = (string) preg_replace('/<!--.*?-->/s', '', (string) $c->read('.harness/memory/lessons.md'));
    if (! preg_match('/^##\s+L-003\b.*?$(.*?)(?=^##\s|\z)/ms', $md, $m)) {
        return null;
    }
    $out = [];
    preg_match_all('/^[ \t]*[-*][ \t]+\*\*([^*]+?):?\*\*:?[ \t]*(.*)$/m', $m[1], $righe, PREG_SET_ORDER);
    foreach ($righe as [, $campo, $testo]) {
        $out[trim($campo, " :")] = trim($testo);
    }

    return $out;
};

/** L'id della regola marcata `lesson: L-003` in rules/guard.php (il commento sopra la chiave); null se manca. */
$idL003 = function (Ctx $c): ?string {
    $src = (string) $c->read('.harness/rules/guard.php');

    return preg_match('/lesson:\s*L-003\b[^\n]*\n(?:[ \t]*(?:\/\/|\*|#)[^\n]*\n)*[ \t]*[\'"]([\w.-]+)[\'"]\s*=>/', $src, $m) ? $m[1] : null;
};

/** Gli id delle regole del guard, nell'ordine del file. */
$idRegole = function (Ctx $c): array {
    preg_match_all('/^[ \t]*[\'"]([\w.-]+)[\'"]\s*=>\s*(?:static\s+)?(?:function|fn)\b/m', (string) $c->read('.harness/rules/guard.php'), $m);

    return $m[1];
};

/**
 * Il caso fissato del verify (catalogo, domanda 11): una dipendenza nuova senza approvazione.
 * Sta qui e non in fixtures/pre-tool: una fixture "deny" prima che la regola esista farebbe
 * diventare rosso verify E5. Le allow tengono onesta la regola: negare ogni comando composer non basta.
 */
$casi = [
    'claude-composer-require-dev' => ['deny', 'claude', 'composer require --dev laravel/boost'],
    'codex-composer-req' => ['deny', 'codex', 'composer req spatie/laravel-data'],
    'claude-cd-and-require' => ['deny', 'claude', 'cd {{ROOT}} && composer --no-interaction require guzzlehttp/guzzle:^7.9'],
    'codex-composer-install' => ['allow', 'codex', 'composer install --no-interaction'],
    'claude-composer-dump-autoload' => ['allow', 'claude', 'composer dump-autoload'],
    'codex-composer-show' => ['allow', 'codex', 'composer show laravel/framework'],
];

/** Un caso → un payload PreToolUse come lo manda l'agente. */
$payload = fn (string $agent, string $cmd): array => $agent === 'codex'
    ? ['session_id' => 'replay', 'turn_id' => 'turn-replay', 'transcript_path' => null, 'cwd' => '{{ROOT}}', 'hook_event_name' => 'PreToolUse',
        'model' => 'gpt-replay', 'permission_mode' => 'default', 'tool_name' => 'Bash', 'tool_input' => ['command' => $cmd], 'tool_use_id' => 'call-replay']
    : ['session_id' => 'replay', 'transcript_path' => null, 'cwd' => '{{ROOT}}', 'hook_event_name' => 'PreToolUse',
        'permission_mode' => 'default', 'tool_name' => 'Bash', 'tool_input' => ['command' => $cmd, 'description' => 'composer'], 'tool_use_id' => 'toolu_replay'];

/** Il corpo di una sezione `## Titolo` (o `###`) senza commenti HTML; null se manca. */
$sezione = function (string $md, string $titolo): ?string {
    $md = (string) preg_replace('/<!--.*?-->/s', '', $md);

    return preg_match('/^#{2,3}\s+'.preg_quote($titolo, '/').'\s*$(.*?)(?=^#{2,3}\s|\z)/ms', $md, $m) ? $m[1] : null;
};

/** L'ADR 0002 del partecipante; null se non c'è (o ce n'è più d'uno). */
$adr = function (Ctx $c): ?string {
    $files = glob($c->path('docs/adr/0002-*.md')) ?: [];

    return count($files) === 1 ? 'docs/adr/'.basename($files[0]) : null;
};

return [
    'esercizio' => 'E8',
    'ingresso' => [
        '.harness/checkpoints/cp-08.php',
        'workshop/esercizi/E8.md',
    ],
    'uscita' => [
        '.harness/memory/lessons.md',
        '.harness/rules/guard.php',
        '.harness/fixtures/pre-tool/codex-composer-require-boost.json',
        '.harness/memory/decisions.md',
        'docs/adr/0002-chiave-di-idempotenza-unica.md',
    ],

    'controlli' => [
        'L-003 in lessons.md: i cinque campi' => function (Ctx $c) use ($lesson, $campi) {
            $l = $lesson($c);
            if ($l === null) {
                return $c->fail('in .harness/memory/lessons.md non trovo «## L-003 — <titolo>»', 'copia la struttura di L-002 e riscrivila');
            }
            // "Ultima volta che è scattato" resta "—" finché la regola non scatta: la compila il trace.
            $vuoti = array_values(array_filter($campi, fn ($k) => $k !== 'Ultima volta che è scattato'
                ? trim($l[$k] ?? '', " \t—-") === ''
                : ! array_key_exists($k, $l)));

            return $vuoti === [] ?: $c->fail('mancano o sono vuoti: '.implode(', ', $vuoti).' (- **Campo:** testo)');
        },
        '"Quando rimuoverlo" è una condizione, non "mai"' => function (Ctx $c) use ($lesson) {
            $q = trim((string) (($lesson($c) ?? [])['Quando rimuoverlo'] ?? ''), " \t.—-");
            if ($q === '') {
                return $c->fail('scrivi quando questo pezzo di harness non servirà più');
            }

            // Harness decay: un pezzo senza condizione di rimozione resta per sempre, anche quando non previene più niente.
            return mb_strlen($q) >= 20 && ! preg_match('/^(mai|never|n\/?a|nessuna|da decidere)$/iu', $q)
                ?: $c->fail("«{$q}» non dice quando togliere il pezzo: scrivi l'evento che lo rende inutile (es. un controllo equivalente in CI)");
        },
        'una regola del guard con lesson: L-003, citata dalla Lesson' => function (Ctx $c) use ($idL003, $idRegole, $lesson) {
            $id = $idL003($c);
            if ($id === null) {
                return $c->fail('in rules/guard.php nessuna regola ha il commento «// lesson: L-003» sopra la chiave', 'scrivilo sulla riga prima di \'<id>\' => function …');
            }
            if (! in_array($id, $idRegole($c), true)) {
                return $c->fail("«{$id}» non è la chiave di una regola (fn o function) del guard");
            }
            $pezzo = (string) (($lesson($c) ?? [])['Pezzo di harness'] ?? '');

            // Il legame va nei due sensi: dalla regola si trova la Lesson, dalla Lesson la regola (e il trace, con lo stesso id).
            return str_contains($pezzo, "`{$id}`") ?: $c->fail("il campo «Pezzo di harness» di L-003 non nomina `{$id}` (fra backtick, come in L-002)");
        },
        'composer require senza approvazione: lo nega la regola di L-003' => function (Ctx $c) use ($idL003, $casi, $payload) {
            $id = $idL003($c);
            if ($id === null) {
                return $c->skip('prima la regola con lesson: L-003');
            }
            $dir = sys_get_temp_dir().'/verify-e8-'.getmypid().'-'.bin2hex(random_bytes(3));
            mkdir($dir, 0777, true);
            try {
                $files = [];
                foreach ($casi as $nome => [$expect, $agent, $cmd]) {
                    $fx = ['_expect' => $expect] + ($expect === 'deny' ? ['_rule' => $id] : []) + $payload($agent, $cmd);
                    file_put_contents($files[] = "{$dir}/{$nome}.json", json_encode($fx, JSON_UNESCAPED_SLASHES));
                }
                $r = $c->run(['php', '.harness/bin/replay', '--json', ...$files], 120);
            } finally {
                exec('rm -rf '.escapeshellarg($dir));
            }
            $righe = W\jsonl($r['out']);
            if (count($righe) !== count($casi)) {
                return $c->fail('replay non ha prodotto risultati: '.W\one_line(trim($r['err'])), 'php .harness/bin/replay --all');
            }
            $fail = array_values(array_filter($righe, fn ($x) => ($x['pass'] ?? false) !== true));

            return $fail === []
                ? $c->ok(count($righe).'/'.count($righe).' PASS ('.count(array_filter($casi, fn ($x) => $x[0] === 'deny')).' deny, '.count(array_filter($casi, fn ($x) => $x[0] === 'allow')).' allow)')
                : $c->fail(count($fail).'/'.count($righe).' FAIL'.implode('', array_map(
                    fn ($x) => "\n        · {$casi[$x['name']][2]}: atteso {$x['expect']}".($x['rule'] ? " [{$x['rule']}]" : '')
                        .', ottenuto '.$x['got'].($x['got_rule'] ? " [{$x['got_rule']}]" : '')
                        .($x['expect'] === 'allow' ? ' (questo comando non aggiunge dipendenze: la regola è troppo larga)' : ''),
                    $fail,
                )), 'php .harness/bin/replay --verbose .harness/fixtures/pre-tool/<il tuo payload>.json');
        },
        'il tuo payload campione: una fixture del pre-tool per la regola di L-003' => function (Ctx $c) use ($idL003) {
            $id = $idL003($c);
            if ($id === null) {
                return $c->skip('prima la regola con lesson: L-003');
            }
            // Una fixture della Lesson dichiara la regola ("_rule") oppure la Lesson ("_lesson": "L-003"). La seconda forma
            // è quella del docente: chi ha una regola sua con un altro id può prenderla senza che il replay diventi rosso.
            $mie = [];
            foreach (glob($c->path('.harness/fixtures/pre-tool').'/*.json') ?: [] as $f) {
                $fx = json_decode((string) file_get_contents($f), true);
                if (is_array($fx) && ($fx['_expect'] ?? null) === 'deny' && (($fx['_rule'] ?? null) === $id || ($fx['_lesson'] ?? null) === 'L-003')) {
                    $mie[] = $f;
                }
            }
            if ($mie === []) {
                return $c->fail("nessuna fixture in .harness/fixtures/pre-tool con \"_expect\": \"deny\" e \"_rule\": \"{$id}\"", 'cp .harness/fixtures/pre-tool/claude-bash-cat-env.json .harness/fixtures/pre-tool/l003-<nome>.json, poi cambia comando, _rule e _trap');
            }
            // Comunque sia dichiarata, a negarla deve essere la regola di L-003: è il suo id che finisce nel trace.
            $righe = W\jsonl($c->run(['php', '.harness/bin/replay', '--json', ...$mie], 120)['out']);
            $altre = array_values(array_filter($righe, fn ($x) => ($x['got'] ?? null) !== 'deny' || ($x['got_rule'] ?? null) !== $id));
            $nomi = implode(', ', array_map(fn ($f) => basename($f), $mie));

            return count($righe) === count($mie) && $altre === []
                ? $c->ok($nomi)
                : $c->fail(
                    "non le nega {$id}: ".($altre === [] ? 'replay senza risultati' : implode(', ', array_map(fn ($x) => "{$x['name']} ({$x['got']}".($x['got_rule'] ? " [{$x['got_rule']}]" : '').')', $altre))),
                    'php .harness/bin/replay --verbose .harness/fixtures/pre-tool/<nome>.json',
                );
        },
        'replay del pre-tool: tutte le fixture PASS (quelle di E5 e la tua)' => function (Ctx $c) {
            $r = $c->run(['php', '.harness/bin/replay', '--all', '--hook=pre-tool', '--json'], 120);
            $righe = W\jsonl($r['out']);
            if ($righe === []) {
                return $c->fail('replay non ha prodotto risultati: '.W\one_line(trim($r['err'])), 'php .harness/bin/replay --all');
            }
            $fail = array_values(array_filter($righe, fn ($x) => ($x['pass'] ?? false) !== true));

            // La regola nuova non deve rubare un deny a un'altra (la prima che nega vince) né negare una strada sicura.
            return $fail === [] ? $c->ok(count($righe).'/'.count($righe).' PASS') : $c->fail(
                count($fail).'/'.count($righe).' FAIL: '.implode(', ', array_map(
                    fn ($x) => "{$x['name']} (atteso {$x['expect']}".($x['rule'] ? " [{$x['rule']}]" : '').", ottenuto {$x['got']}".($x['got_rule'] ? " [{$x['got_rule']}]" : '').')',
                    $fail,
                )),
                'php .harness/bin/replay --all   (una regola che nega prima delle altre va spostata più in basso)',
            );
        },

        // Promozione Decision → ADR 0002: comprimibile (taglio 3), quindi solo WARN.
        'ADR 0002: MADR con le opzioni scartate e la Confirmation (comprimibile)' => function (Ctx $c) use ($adr, $sezione) {
            $file = $adr($c);
            if ($file === null) {
                return $c->warn('manca docs/adr/0002-*.md (se la Decision non passa i tre criteri, resta in memoria: va bene così)', 'cp docs/adr/0000-template.md docs/adr/0002-<titolo>.md');
            }
            $md = (string) $c->read($file);
            $problemi = [];
            if (! preg_match('/\A---\R(.*?)\R---/s', $md, $fm) || ! preg_match('/^status:\s*(proposed|accepted|rejected|deprecated|superseded)\b/m', $fm[1]) || ! preg_match('/^date:\s*["\']?\d{4}-\d{2}-\d{2}/m', $fm[1])) {
                $problemi[] = 'frontmatter con status e date';
            }
            $template = (string) $c->read('docs/adr/0000-template.md');
            $norm = fn (?string $s) => trim((string) preg_replace(['/^[-*]\s*$/m', '/\s+/'], ['', ' '], (string) $s));
            foreach (['Context and Problem Statement', 'Decision Drivers', 'Considered Options', 'Decision Outcome', 'Consequences', 'Confirmation'] as $t) {
                $corpo = $sezione($md, $t);
                if ($corpo === null || $norm($corpo) === '' || $norm($corpo) === $norm($sezione($template, $t))) {
                    $problemi[] = $t;
                }
            }
            if (preg_match_all('/^[ \t]*[-*][ \t]+\S/m', (string) $sezione($md, 'Considered Options')) < 2) {
                $problemi[] = 'almeno 2 Considered Options';
            }

            return $problemi === [] ? $c->ok($file) : $c->warn("{$file}: da compilare ".implode(', ', $problemi));
        },
        'decisions.md: la Decision promossa rimanda all\'ADR 0002 (comprimibile)' => function (Ctx $c) use ($adr) {
            if ($adr($c) === null) {
                return $c->skip('nessun ADR 0002');
            }
            $righe = array_filter(explode("\n", (string) $c->read('.harness/memory/decisions.md')), fn ($l) => str_starts_with(trim($l), '|') && preg_match('/\]\((?:\.\.\/\.\.\/|\/)?(docs\/adr\/0002-[^)\s#]+\.md)\)/', $l));
            if ($righe === []) {
                return $c->warn('nessuna riga di .harness/memory/decisions.md ha un link [ADR 0002](../../docs/adr/0002-….md)', 'nella riga della Decision: «promossa → [ADR 0002](../../docs/adr/0002-<titolo>.md)»');
            }
            preg_match('/\]\((?:\.\.\/\.\.\/|\/)?(docs\/adr\/0002-[^)\s#]+\.md)\)/', (string) array_values($righe)[0], $m);

            return $c->exists($m[1]) ?: $c->warn("il link punta a {$m[1]}, che non esiste");
        },
    ],
];
