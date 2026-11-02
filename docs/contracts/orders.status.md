# orders.status — Stato di rimborso dell'ordine

La regola che lega `orders.status` ai rimborsi registrati. Vale per ogni operazione che rimborsa, totale o parziale.
Classe: `App\Models\Order` (`status`, cast `App\Enums\OrderStatus`)

## Input

Un ordine `paid` o `partially_refunded` e i rimborsi registrati per lui.

## Output

Lo `status` dell'ordine dopo ogni rimborso.

## Invarianti

| ID | Regola | Group Pest |
|---|---|---|
| ORDER-INV-1 | `refunded` se e solo se la somma dei rimborsi è uguale a `orders.amount`; `partially_refunded` se e solo se la somma è maggiore di 0 e minore di `orders.amount`. La somma si confronta in centesimi: `refunds.amount` è in euro (C3). | `ORDER-INV-1` |

Oggi il group copre solo il ramo `refunded` (rimborso totale legacy). Il ramo `partially_refunded` arriva con il rimborso parziale.

## Side effects

Nessuno: è una regola sullo stato. Lo scrive l'operazione che rimborsa, nella sua transazione.

## Errori

Nessuno proprio: un'operazione che lascerebbe lo stato incoerente fallisce con i suoi errori.

## Non-goals

- Gli stati di pagamento (`pending` → `paid`) e l'annullamento (`orders.cancel.md`).
- Aggiungere `partially_refunded` a `OrderStatus`: lo fa il task del rimborso parziale. `orders.status` è una stringa, quindi non serve una migrazione.
