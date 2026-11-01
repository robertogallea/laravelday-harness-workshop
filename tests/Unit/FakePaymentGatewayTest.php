<?php

use App\Payments\Exceptions\IdempotencyKeyReused;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\FakePaymentGateway;

it('returns the same refund when a key is replayed with the same parameters', function () {
    $gateway = new FakePaymentGateway;

    $first = $gateway->refund('ch_123', 1500, 'key-1');
    $second = $gateway->refund('ch_123', 1500, 'key-1');

    expect($second->id)->toBe($first->id)
        ->and($gateway->refundsFor('ch_123'))->toHaveCount(1);
});

it('rejects a key reused with different parameters', function () {
    $gateway = new FakePaymentGateway;

    $gateway->refund('ch_123', 1500, 'key-1');
    $gateway->refund('ch_123', 900, 'key-1');
})->throws(IdempotencyKeyReused::class);

it('rejects a key reused for a different charge', function () {
    $gateway = new FakePaymentGateway;

    $gateway->refund('ch_123', 1500, 'key-1');
    $gateway->refund('ch_456', 1500, 'key-1');
})->throws(IdempotencyKeyReused::class);

it('creates distinct refunds for distinct keys', function () {
    $gateway = new FakePaymentGateway;

    $first = $gateway->refund('ch_123', 500, 'key-1');
    $second = $gateway->refund('ch_123', 500, 'key-2');

    expect($second->id)->not->toBe($first->id)
        ->and($gateway->refundsFor('ch_123'))->toHaveCount(2);
});

it('can be told to fail the next refund', function () {
    $gateway = new FakePaymentGateway;
    $gateway->failNextRefund();

    expect(fn () => $gateway->refund('ch_123', 500, 'key-1'))->toThrow(PaymentGatewayException::class);

    $refund = $gateway->refund('ch_123', 500, 'key-1');

    expect($refund->amount)->toBe(500);
});

it('returns the same charge when a checkout key is replayed', function () {
    $gateway = new FakePaymentGateway;

    $first = $gateway->charge(2500, 'tok_visa', 'checkout-1');
    $second = $gateway->charge(2500, 'tok_visa', 'checkout-1');

    expect($second->id)->toBe($first->id)
        ->and($gateway->charges())->toHaveCount(1);
});

it('rejects a checkout key reused with a different amount', function () {
    $gateway = new FakePaymentGateway;

    $gateway->charge(2500, 'tok_visa', 'checkout-1');
    $gateway->charge(3000, 'tok_visa', 'checkout-1');
})->throws(IdempotencyKeyReused::class);
