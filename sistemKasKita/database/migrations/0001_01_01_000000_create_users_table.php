<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('nama', 100);
            $table->string('username', 50)->unique();
            $table->string('email', 100)->nullable()->unique();
            $table->string('password');

            $table->enum('role', [
                'bendahara_angkatan',
                'bendahara_kelas',
                'anggota',
            ]);

            $table->enum('management_type', [
                'angkatan',
                'kelas_mandiri',
            ]);

            // Relasi ditambahkan setelah tabel cohorts dan classes dibuat.
            $table->unsignedBigInteger('cohort_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();

            // Akun yang membuat akun ini.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};