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
        Schema::create('submission', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->index()->constrained('activity')->cascadeOnDelete();
            $table->foreignUuid('user_id')->index()->constrained('app_user')->cascadeOnDelete();
            $table->text('prompt');
            $table->integer('attempt');
            $table->string('observation', 500)->nullable();
            $table->string('status', 20);
            $table->timestampTz('sent_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['activity_id', 'user_id', 'attempt']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission');
    }
};
