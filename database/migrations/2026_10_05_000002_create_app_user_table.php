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
        Schema::create('app_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 320)->unique();
            $table->string('full_name', 120);
            $table->string('password_hash', 255);
            $table->string('role', 20);
            $table->boolean('is_active');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreignUuid('cohort_id')->nullable()->constrained('cohort')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_user');
    }
};
