<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->decimal('amount', 12, 2);

            $table->enum('period_type', [
                'mingguan',
                'bulanan',
                'tahunan',
                'sekali',
            ]);

            $table->enum('management_type', [
                'angkatan',
                'kelas_mandiri',
            ]);

            $table->foreignId('cohort_id')
                ->nullable()
                ->constrained('cohorts')
                ->cascadeOnDelete();

            $table->foreignId('class_id')
                ->nullable()
                ->constrained('classes')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dues');
    }
};