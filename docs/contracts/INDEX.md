# Behavioral Contract — indice

Un contract per operazione di business. La classe lo cita nel docblock con `@contract`; ogni invariante è un group Pest con lo stesso ID (`vendor/bin/pest --group=CANCEL-INV-1`).

| Operazione | Contract | Classe | Invarianti |
|---|---|---|---|
| Annulla ordine | [orders.cancel.md](orders.cancel.md) | `App\Actions\Orders\CancelOrder` | CANCEL-INV-1..3 |
