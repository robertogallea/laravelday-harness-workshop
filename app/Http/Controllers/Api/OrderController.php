<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\PlaceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request, PlaceOrder $placeOrder): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:50', 'max:99999999'],
            'payment_token' => ['required', 'string', 'max:255'],
        ]);

        $order = $placeOrder->handle(
            $request->user(),
            $validated['amount'],
            $validated['payment_token'],
            $request->header('Idempotency-Key'),
        );

        return response()->json([
            'data' => [
                'id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'status' => $order->status,
            ],
        ], $order->wasRecentlyCreated ? 201 : 200);
    }
}
