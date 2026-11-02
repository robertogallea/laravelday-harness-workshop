# Glossario di dominio

Le parole del dominio e dove stanno nel codice. Se un termine qui e uno nel codice non coincidono, vale il codice e si corregge il glossario.

| Termine | Significato | Nel codice |
|---|---|---|
| Ordine | Acquisto di un cliente. Stati: `pending` (creato), `paid` (addebitato), `cancelled` (annullato prima del pagamento), `refunded` (rimborsato per intero). | `App\Models\Order`, `App\Enums\OrderStatus` |
| Importo | Sempre in centesimi interi, con una sola eccezione legacy: `refunds.amount` è in euro (AGENTS.md → Conflicts C3). | `orders.amount`, `refunds.amount` |
| Staff | Chi lavora nel back office: ruoli `support`, `finance`, `admin`. Il ruolo `customer` è il cliente. Essere staff non basta per rimborsare: decide `OrderPolicy`. | `App\Enums\Role`, `User::isStaff()` |
| Addebito | Il pagamento dell'ordine presso il gateway; ne resta l'identificativo sull'ordine. | `orders.gateway_charge_id`, `PaymentGateway::charge()` |
| Rimborso totale | Restituisce l'intero importo di un ordine `paid`. Esiste solo nel codice legacy. | `RefundService::refundInFull()`, `POST /admin/orders/{order}/refund` |
| Rimborso parziale | Restituisce una parte dell'importo; si può ripetere finché resta qualcosa da rimborsare. Non esiste ancora. | — (il contract arriva con E3) |
| Annullamento | Chiude un ordine `pending` senza movimenti di denaro. | `App\Actions\Orders\CancelOrder` |
| Audit | Traccia di chi ha fatto cosa su quale oggetto, con un payload. Le Actions la scrivono nella stessa transazione dell'operazione. | `audit_entries`, `App\Actions\Audit\RecordAuditEntry` |
