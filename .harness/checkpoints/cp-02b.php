<?php

// E2b — ADR 0001 scritto a mano. Arriva con cp-02 (ingresso), la soluzione è in cp-02b.
// Niente costanti né funzioni con nome: il file si può caricare più volte.

use Workshop\Ctx;

/** Il corpo di una sezione `## Titolo` (o `###`) senza commenti HTML; null se manca. */
$sezione = function (string $md, string $titolo): ?string {
    $md = preg_replace('/<!--.*?-->/s', '', $md);
    if (! preg_match('/^#{2,3}\s+'.preg_quote($titolo, '/').'\s*$(.*?)(?=^#{2,3}\s|\z)/ms', $md, $m)) {
        return null;
    }

    return $m[1];
};

/** L'ADR 0001 del partecipante (il template 0000 non conta). */
$adr = function (Ctx $c): ?string {
    $files = glob($c->path('docs/adr/0001-*.md')) ?: [];

    return count($files) === 1 ? 'docs/adr/'.basename($files[0]) : null;
};

$richieste = ['Context and Problem Statement', 'Decision Drivers', 'Considered Options', 'Decision Outcome', 'Consequences', 'Confirmation'];

return [
    'esercizio' => 'E2b',
    'ingresso' => [
        '.harness/checkpoints/cp-02b.php',
        'workshop/esercizi/E2b.md',
        'docs/adr/0000-template.md',
        'docs/glossary.md',
    ],
    'uscita' => ['docs/adr/0001-actions-over-services.md', 'AGENTS.md'],

    'controlli' => [
        'docs/adr/0001-*.md esiste' => function (Ctx $c) use ($adr) {
            $n = count(glob($c->path('docs/adr/0001-*.md')) ?: []);

            return $adr($c) !== null ?: $c->fail(
                $n === 0 ? 'manca' : "ne trovo {$n}: ne serve uno",
                'cp docs/adr/0000-template.md docs/adr/0001-actions-over-services.md',
            );
        },
        'frontmatter con status e una date vera' => function (Ctx $c) use ($adr) {
            $file = $adr($c);
            if ($file === null || ! preg_match('/\A---\R(.*?)\R---/s', (string) $c->read($file), $fm)) {
                return $c->fail('manca il frontmatter fra due righe ---');
            }
            $status = preg_match('/^status:\s*([a-z]+)/m', $fm[1], $s) ? $s[1] : null;
            $date = preg_match('/^date:\s*(["\']?)(\d{4}-\d{2}-\d{2})\1\s*(#.*)?$/m', $fm[1], $d) ? $d[2] : null;
            $problemi = array_filter([
                in_array($status, ['proposed', 'accepted', 'rejected', 'deprecated', 'superseded'], true) ? null : 'status (proposed | accepted | …)',
                $date !== null && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)) ? null : 'date (AAAA-MM-GG)',
            ]);

            return $problemi === [] ?: $c->fail('da compilare: '.implode(', ', $problemi));
        },
        'sezioni MADR compilate, Confirmation compresa' => function (Ctx $c) use ($adr, $sezione, $richieste) {
            $md = (string) ($adr($c) ? $c->read($adr($c)) : '');
            $template = (string) $c->read('docs/adr/0000-template.md');
            $norm = fn (?string $s) => trim(preg_replace(['/^[-*]\s*$/m', '/\s+/'], ['', ' '], (string) $s));
            // Vuota = assente, senza testo, oppure uguale a quella del template (es. "Scelta: ****, perché .").
            $vuote = array_values(array_filter($richieste, function ($t) use ($md, $template, $sezione, $norm) {
                $corpo = $sezione($md, $t);

                return $corpo === null || $norm($corpo) === '' || $norm($corpo) === $norm($sezione($template, $t));
            }));

            return $vuote === [] ?: $c->fail('mancano o sono vuote: '.implode(', ', $vuote));
        },
        'Considered Options: almeno 2' => function (Ctx $c) use ($adr, $sezione) {
            $corpo = (string) $sezione((string) ($adr($c) ? $c->read($adr($c)) : ''), 'Considered Options');
            $n = preg_match_all('/^[ \t]*[-*][ \t]+\S/m', $corpo);

            return $n >= 2 ?: $c->fail("ne trovo {$n}: scrivi anche quella scartata");
        },
        'C1 in Conflicts rimanda all\'ADR 0001' => function (Ctx $c) use ($sezione) {
            $conflicts = (string) $sezione((string) $c->read('AGENTS.md'), 'Conflicts');
            $riga = array_values(array_filter(explode("\n", $conflicts), fn ($l) => str_starts_with(trim($l), '|') && str_contains($l, 'RefundService')))[0] ?? null;
            if ($riga === null) {
                return $c->fail('in Conflicts non trovo la riga che nomina RefundService');
            }
            if (! preg_match('/\]\((?:\.\/)?(docs\/adr\/0001-[^)\s#]+\.md)\)/', $riga, $m)) {
                return $c->fail('la riga C1 non ha un link [ADR 0001](docs/adr/0001-….md)');
            }

            return $c->exists($m[1]) ?: $c->fail("il link punta a {$m[1]}, che non esiste");
        },
        'Topology cita docs/adr/' => fn (Ctx $c) => str_contains((string) $sezione((string) $c->read('AGENTS.md'), 'Topology'), 'docs/adr/')
            ?: $c->fail('aggiungi in ## Topology una riga `docs/adr/` — quando aprirlo'),
    ],
];
