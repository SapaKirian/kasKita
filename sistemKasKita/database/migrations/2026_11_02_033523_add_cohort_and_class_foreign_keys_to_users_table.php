
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Relasi ke tabel cohorts
            $table->foreign('cohort_id')
                ->references('id')
                ->on('cohorts')
                ->nullOnDelete();

            // Relasi ke tabel classes
            $table->foreign('class_id')
                ->references('id')
                ->on('classes')
                ->nullOnDelete();

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign(['cohort_id']);
            $table->dropForeign(['class_id']);

        });
    }
};
