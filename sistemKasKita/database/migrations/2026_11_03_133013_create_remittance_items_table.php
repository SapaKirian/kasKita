<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('remittance_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('remittance_id')
                ->constrained('remittances')
                ->cascadeOnDelete();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->restrictOnDelete();

            // Menyimpan nominal pembayaran saat dimasukkan ke setoran.
            $table->decimal('amount', 12, 2);

            $table->timestamps();

            // Satu pembayaran tidak boleh tercatat dua kali
            // dalam setoran yang sama.
            $table->unique(
                ['remittance_id', 'payment_id'],
                'remittance_payment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_items');
    }
};
