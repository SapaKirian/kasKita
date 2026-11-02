<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_recipient_id')
                ->constrained('bill_recipients')
                ->restrictOnDelete();

            $table->foreignId('payer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('amount', 12, 2);

            $table->enum('payment_method', [
                'tunai',
                'transfer',
            ]);

            $table->enum('status', [
                'menunggu_verifikasi',
                'disetujui',
                'ditolak',
            ])->default('menunggu_verifikasi');

            $table->dateTime('payment_date');

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('verified_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};