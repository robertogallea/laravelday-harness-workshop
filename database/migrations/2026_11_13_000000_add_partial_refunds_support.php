<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->string('reason')->nullable()->after('amount');
            $table->string('idempotency_key')->nullable()->after('reason');
            $table->unique(['order_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'idempotency_key']);
            $table->dropColumn(['reason', 'idempotency_key']);
            $table->unique('order_id');
        });
    }
};
