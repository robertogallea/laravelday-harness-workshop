<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partial refunds (T-001): an order can have many refunds, each with its reason
 * and the client's Idempotency-Key. Additive only: no existing column changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            // Keep order_id indexed for the foreign key before dropping the unique one.
            $table->index('order_id');
            $table->dropUnique(['order_id']);

            $table->string('reason')->nullable();
            $table->string('idempotency_key')->nullable();

            // Same key on the same order = one refund (REFUND-INV-5). Legacy rows have NULL keys.
            $table->unique(['order_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'idempotency_key']);
            $table->dropColumn(['reason', 'idempotency_key']);
            $table->unique('order_id');
            $table->dropIndex(['order_id']);
        });
    }
};
