<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_id')
                ->constrained('bills')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('class_id')
                ->nullable()
                ->constrained('classes')
                ->nullOnDelete();

            $table->decimal('bill_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);

            $table->enum('status', [
                'belum_dibayar',
                'menunggu_verifikasi',
                'lunas',
                'ditolak',
                'terlambat',
            ])->default('belum_dibayar');

            $table->timestamps();

            $table->unique(['bill_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_recipients');
    }
};