<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('class_members');
    }

    public function down(): void
    {
        Schema::create('class_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('class_id')
                ->constrained('classes')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('joined_at')->useCurrent();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['class_id', 'user_id']);
        });
    }
};
