<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lesson', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->index()->constrained('course_module')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('kind', 20);
            $table->integer('position');
            $table->integer('duration_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson');
    }
};
