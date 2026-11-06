<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('remittance_id')
                ->nullable()
                ->unique()
                ->after('payment_id')
                ->constrained('remittances')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['remittance_id']);
            $table->dropUnique(['remittance_id']);
            $table->dropColumn('remittance_id');
        });
    }
};