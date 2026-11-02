<?php

use App\Models\Order;
use App\Models\User;

it('lascia vedere un ordine al suo cliente', function () {
    $this->fakePayments();
    $customer = User::factory()->create();
    $order = Order::factory()->for($customer, 'customer')->create();

    $this->actingAs($customer)->getJson("/api/orders/{$order->id}")->assertOk();
    expect($this->payments)->not->toBeNull();
})->group('E6-REPLAY');
