<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CancelOrder;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderCancellationController extends Controller
{
    public function store(Order $order, Request $request, CancelOrder $cancelOrder): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $cancelOrder->handle($order, $request->user(), $validated['reason']);

        return back()->with('status', 'Ordine annullato.');
    }
}
