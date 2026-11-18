<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RefundService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderPartialRefundController extends Controller
{
    public function store(Order $order, Request $request, RefundService $refunds): RedirectResponse
    {
        abort_unless($request->user()->isStaff(), 403);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.$order->refundableAmount()],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $refunds->refundPartially($order, $request->user(), (int) $validated['amount'], $validated['reason']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Rimborso parziale registrato.');
    }
}
