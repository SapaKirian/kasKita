<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cohort_id')
                ->nullable()
                ->constrained('cohorts')
                ->cascadeOnDelete();

            $table->string('name', 100);

            $table->enum('management_type', [
                'angkatan',
                'kelas_mandiri',
            ]);

            $table->foreignId('treasurer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};