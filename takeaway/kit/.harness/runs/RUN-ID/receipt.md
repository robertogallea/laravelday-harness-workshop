<!-- Schema dell'output di: php .harness/bin/receipt T-NNN (nel workshop lo scrive anche lo stop hook)
     [D] lo dimostra lo script (deterministico, ripetibile) · [A] lo dichiara l'agente in progress.md, senza verifica.
     Un receipt vero da guardare: nel repo del workshop, php .harness/bin/receipt T-001 --of=run-1 -->

# Change Receipt — T-NNN · <!-- ✅ VERDE | ❌ ROSSO | ⏸️ IN ATTESA | NON VERIFICATO -->

Run `<run-id>` · agente `<claude|codex>` · <!-- data --> · trace: `.harness/runs/<run-id>/trace.jsonl`

## Esito [D]
<!-- "Tutti i controlli sono verdi", oppure una riga per classe di problema, ognuna con cosa fare:
     protected_file, scope_violation, missing_evidence, test_failure, static_analysis, … -->

## Task [D]
<!-- id, titolo, status, file del Task Contract, commit base -->

## Diff [D]
<!-- File | +/− | Nello scope | must_not_change -->

## Stile e tipi [D]
<!-- Pint, PHPStan: ok / errori / non eseguito -->

## Test mirati [D]
<!-- come sono stati scelti (affected-tests, convenzione di path, suite completa), quali file, quanti verdi e rossi -->

## Evidenze [D]
<!-- Comando | Esito | Test | Durata — uno per riga di `evidence`; 0 test = ❌ nessun test -->

## Invarianti → test [D]
<!-- Invariante | Test | Esito — per ogni ID dei contract toccati; 0 test = ❌ non coperta -->

## Progress [D]
<!-- progress.md: task giusto? aggiornato in questa sessione? -->

## Conflicts [D] + [A]
<!-- [D] le righe di AGENTS.md → Conflicts toccate dal diff · [A] "Conflicts incontrati" da progress.md -->

## Rischi aperti [A]
<!-- da progress.md → "Rischi aperti" -->

## Metriche [D]
<!-- richieste al modello, token, costo; token per invariante verde (metrics.json) -->
