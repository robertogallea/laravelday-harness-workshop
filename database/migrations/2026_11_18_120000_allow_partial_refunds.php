<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            // An order can now have several (partial) refunds.
            $table->dropUnique(['order_id']);
            $table->index('order_id');
            $table->string('reason', 500)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn('reason');
            $table->dropIndex(['order_id']);
            $table->unique('order_id');
        });
    }
};
