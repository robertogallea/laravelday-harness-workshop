<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderRefundController extends Controller
{
    public function store(Order $order, Request $request, RefundService $refunds): RedirectResponse
    {
        $refunds->refundInFull($order, $request->user());

        return back()->with('status', 'Ordine rimborsato.');
    }

    public function storePartial(Order $order, Request $request, RefundService $refunds): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $refunds->refundPartially($order, $request->user(), $validated['amount'], $validated['reason']);

        return back()->with('status', 'Ordine rimborsato parzialmente.');
    }
}
