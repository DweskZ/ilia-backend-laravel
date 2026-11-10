<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('course', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 160)->unique();
            $table->string('title', 200);
            $table->string('summary', 400);
            $table->text('description');
            $table->string('level', 20);
            $table->string('status', 20);
            $table->foreignUuid('instructor_id')->constrained('app_user')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('published_at')->nullable();
        });

        // Partial index for the published catalog (keyset pagination), PostgreSQL only.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE INDEX ix_course_catalog_keyset ON course "
                ."(coalesce(published_at, created_at) DESC, id DESC) WHERE status = 'published'"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course');
    }
};
