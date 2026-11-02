<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cohort_id')
                ->nullable()
                ->constrained('cohorts')
                ->nullOnDelete();

            $table->foreignId('class_id')
                ->nullable()
                ->constrained('classes')
                ->nullOnDelete();

            $table->enum('transaction_type', [
                'pemasukan',
                'pengeluaran',
            ]);

            $table->string('category', 100);

            $table->decimal('amount', 12, 2);

            $table->text('description')->nullable();

            $table->dateTime('transaction_date');

            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};