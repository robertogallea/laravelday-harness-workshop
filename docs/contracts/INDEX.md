# Behavioral Contract — indice

Un contract per operazione di business. La classe lo cita nel docblock con `@contract`; ogni invariante è un group Pest con lo stesso ID (`vendor/bin/pest --group=CANCEL-INV-1`).

| Operazione | Contract | Classe | Invarianti |
|---|---|---|---|
| Annulla ordine | [orders.cancel.md](orders.cancel.md) | `App\Actions\Orders\CancelOrder` | CANCEL-INV-1..3 |
| Stato di rimborso dell'ordine | [orders.status.md](orders.status.md) | `App\Models\Order` (`status`) | ORDER-INV-1 |
| Rimborso totale (legacy) | [refunds.full.md](refunds.full.md) | `App\Services\RefundService::refundInFull()` | REFUND-FULL-INV-1..2 |
| Rimborso parziale | [refunds.partial.md](refunds.partial.md) | da creare in `app/Actions/Refunds/` | REFUND-INV-1..6 |
