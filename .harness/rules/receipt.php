<?php

declare(strict_types=1);

/*
 * .harness/rules/receipt.php — la parte del Change Receipt che dimostra gli invarianti (E7).
 */

return [
    /*
     * fn (array $contractIds, array $groupResults): list<array{id: string, tests: int, outcome: string}>
     *   $contractIds   gli ID degli invarianti del contract, es. ['REFUND-INV-1', …]
     *   $groupResults  per group Pest: ['REFUND-INV-1' => ['tests' => 2, 'failed' => 0], …]
     * Una riga per invariante: quanti test la provano e con che esito.
     */
    'invariantRows' => function (array $contractIds, array $groupResults): array {
        // E7 — TODO: una riga per ogni ID del contract, nell'ordine del contract.
        //   almeno un rosso → "rossa" (anche con 0 test: un errore prima dei test, come un
        //     ParseError, dà tests 0 e failed 1, e non è un test mancante);
        //   0 test, o l'ID assente da $groupResults → "non coperta" (l'agente non la può dimostrare);
        //   altrimenti "verde".
        // Fixture in .harness/fixtures/receipt/ (arrivano con checkpoint inizia E7) · php .harness/bin/verify E7
        return [];
    },
];
