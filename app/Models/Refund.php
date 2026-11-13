<?php

namespace App\Models;

use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'amount', 'gateway_refund_id', 'refunded_by', 'reason', 'idempotency_key'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    /**
     * `refunds.amount` is stored in euros (AGENTS.md → Conflicts C3): convert integer cents
     * to the decimal string the column expects, without going through floats.
     */
    public static function centsToEuros(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /**
     * The refunded amount in integer cents (C3).
     */
    public function amountInCents(): int
    {
        [$euros, $decimals] = array_pad(explode('.', (string) $this->amount, 2), 2, '0');

        return (int) $euros * 100 + (int) str_pad(substr($decimals, 0, 2), 2, '0');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
