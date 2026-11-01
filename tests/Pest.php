<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->in('Feature/Payments')->beforeEach(function () {
    $this->payments = $this->fakePayments();
});
