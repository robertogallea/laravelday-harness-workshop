<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Refunds\RefundPartially;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartialRefundRequest;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderRefundController extends Controller
{
    public function store(Order $order, Request $request, RefundService $refunds): RedirectResponse
    {
        $refunds->refundInFull($order, $request->user());

        return back()->with('status', 'Ordine rimborsato.');
    }

    public function storePartial(Order $order, StorePartialRefundRequest $request, RefundPartially $refunds): RedirectResponse
    {
        Gate::authorize('refund', $order);

        $refunds->handle(
            $order,
            $request->user(),
            $request->integer('amount'),
            $request->string('reason')->toString(),
            $request->header('Idempotency-Key'),
        );

        return back()->with('status', 'Rimborso emesso.');
    }
}
