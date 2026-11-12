<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revision', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('correlation_id', 64)->index();
            $table->uuid('entrega_id')->index();
            $table->integer('intento')->nullable();
            $table->uuid('solicitante_id')->nullable();
            $table->string('origen', 12);   // asincrona | sincrona
            $table->string('estado', 12);   // pendiente | procesando | completada | fallida
            $table->integer('puntaje')->nullable();
            $table->text('retroalimentacion')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('solicitada_en')->useCurrent();
            $table->timestampTz('iniciada_en')->nullable();
            $table->timestampTz('finalizada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision');
    }
};
