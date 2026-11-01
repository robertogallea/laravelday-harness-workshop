<?php

namespace Tests;

use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function fakePayments(): FakePaymentGateway
    {
        $this->app->instance(PaymentGateway::class, $fake = new FakePaymentGateway);

        return $fake;
    }
}
