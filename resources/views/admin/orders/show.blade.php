<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ordine #{{ $order->id }} · {{ config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>Ordine #{{ $order->id }}</h1>

        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif

        <dl>
            <dt>Cliente</dt>
            <dd>{{ $order->user->name }} ({{ $order->user->email }})</dd>

            <dt>Importo</dt>
            <dd>{{ Number::currency($order->amount / 100, $order->currency, 'it') }}</dd>

            <dt>Stato</dt>
            <dd>{{ $order->status->value }}</dd>

            @if ($order->refunds->isNotEmpty())
                <dt>Rimborsato</dt>
                <dd>{{ Number::currency($order->refundedAmount() / 100, $order->currency, 'it') }}</dd>

                <dt>Residuo rimborsabile</dt>
                <dd>{{ Number::currency($order->refundableAmount() / 100, $order->currency, 'it') }}</dd>
            @endif

            <dt>Pagato il</dt>
            <dd>{{ $order->paid_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        </dl>

        @if ($order->refunds->isNotEmpty())
            <h2>Rimborsi</h2>
            <table>
                <thead>
                    <tr><th>Data</th><th>Importo</th><th>Operatore</th><th>Motivo</th><th>Riferimento</th></tr>
                </thead>
                <tbody>
                    @foreach ($order->refunds as $refund)
                        <tr>
                            <td>{{ $refund->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ Number::currency((float) $refund->amount, $order->currency, 'it') }}</td>
                            <td>{{ $refund->refundedBy->name }}</td>
                            <td>{{ $refund->reason ?? '—' }}</td>
                            <td>{{ $refund->gateway_refund_id }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('refund', $order)
            <form method="POST" action="{{ route('admin.orders.refund', $order) }}">
                @csrf
                <button type="submit">Rimborsa l'ordine</button>
            </form>

            <h2>Rimborso parziale</h2>
            <form method="POST" action="{{ route('admin.orders.refunds.store', $order) }}">
                @csrf
                <label>
                    Importo (centesimi)
                    <input type="number" name="amount" min="1" max="{{ $order->refundableAmount() }}" step="1" value="{{ old('amount') }}" required>
                </label>
                @error('amount') <p role="alert">{{ $message }}</p> @enderror
                <label>
                    Motivo
                    <input type="text" name="reason" maxlength="500" value="{{ old('reason') }}" required>
                </label>
                @error('reason') <p role="alert">{{ $message }}</p> @enderror
                <button type="submit">Rimborsa l'importo</button>
            </form>
        @endcan
    </main>
</body>
</html>
