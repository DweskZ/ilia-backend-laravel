<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_ruta', function (Blueprint $table) {
            $table->id();
            $table->uuid('evento_id')->nullable()->unique();   // null when the message was unreadable
            $table->string('correlation_id', 64)->nullable()->index();
            $table->string('tipo', 100)->nullable();
            $table->string('prioridad', 10)->nullable();
            $table->string('estado_entrega', 20)->nullable();
            $table->string('decision', 14);                     // enrutado | filtrado | no_enrutable | invalido
            $table->string('destino', 60)->nullable();          // queue name
            $table->string('routing_key', 40)->nullable();
            $table->text('motivo')->nullable();
            $table->string('consumido_por', 20)->nullable();    // revision | notificaciones | intentos
            $table->timestampTz('recibido_en')->useCurrent();
            $table->timestampTz('consumido_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_ruta');
    }
};
