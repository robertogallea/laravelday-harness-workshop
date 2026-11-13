<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Shape of a partial refund request. The refundable balance is checked by the Action
 * (RefundOrderPartially).
 */
class StorePartialRefundRequest extends FormRequest
{
    /**
     * OrderPolicy::refund, before validation (REFUND-INV-1): role finance/admin,
     * order paid or partially_refunded.
     */
    public function authorize(): bool
    {
        Gate::authorize('refund', $this->route('order'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
