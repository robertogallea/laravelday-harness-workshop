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
}
