<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Refunds\RefundOrderPartially;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartialRefundRequest;
use App\Models\Order;
use App\Payments\Exceptions\PaymentGatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class OrderPartialRefundController extends Controller
{
    public function store(Order $order, StorePartialRefundRequest $request, RefundOrderPartially $refundPartially): RedirectResponse
    {
        try {
            $refundPartially->handle(
                $order,
                $request->user(),
                (int) $request->validated('amount'),
                $request->validated('reason'),
                $request->header('Idempotency-Key'),
            );
        } catch (PaymentGatewayException $e) {
            // The gateway refused (IdempotencyKeyReused included): nothing was written.
            throw ValidationException::withMessages(['refund' => $e->getMessage()]);
        }

        return to_route('admin.orders.show', $order)->with('status', 'Rimborso registrato.');
    }
}
