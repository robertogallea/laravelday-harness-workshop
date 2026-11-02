# <!-- NomeOperazione -->

<!-- Una frase: cosa fa l'operazione. -->
Classe: `<!-- App\Actions\… -->` (docblock: `@contract docs/contracts/<nome>.md`)

## Input

<!-- Parametri con tipo e vincoli (unità, range, unicità). -->

## Output

<!-- Cosa restituisce e in che stato lascia i dati. -->

## Invarianti

<!-- Regole sempre vere, qualunque modifica si faccia. ID stabile: PREFISSO-INV-N.
     Ogni invariante ha almeno un test nel group con il suo ID:
     Pest ->group('<ID>') · PHPUnit 10+ #[Group('<ID>')] · PHPUnit 9 @group <ID> -->

| ID | Regola | Group |
|---|---|---|
| <!-- X-INV-1 --> | | |

## Side effects

<!-- Scritture su DB, chiamate esterne, eventi, code. -->

## Errori

<!-- Eccezione — quando succede (e quale invariante protegge). -->

## Non-goals

<!-- Cosa l'operazione NON fa, soprattutto ciò che un agente aggiungerebbe "per completezza". -->
