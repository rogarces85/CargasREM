<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-09 recepciones — grupo lógico por (estab, serie, mes, año, servicio): UNIQUE (RF-027/RF-032)
        Schema::create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('serie', 2);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('servicio_id');
            $table->timestamps();

            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('servicio_id')->references('id')->on('servicios')->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['establecimiento_id', 'serie', 'mes', 'anio', 'servicio_id'], 'unq_recepcion_grupo');
        });

        // DAT-10 recepcion_versiones — cada reenvío (RF-027..RF-031, RN-011/RN-012)
        Schema::create('recepcion_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('recepciones')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('version')->default(1);
            $table->string('archivo', 255);
            $table->string('hash_sha256', 64)->nullable();
            $table->dateTime('fecha_carga');
            $table->boolean('en_plazo')->default(true);          // LIM-013
            $table->boolean('es_segunda_subida')->default(false); // LIM-014
            $table->enum('estado', ['recibida', 'validando', 'validada', 'con_errores', 'en_revision', 'aceptada', 'observada', 'rechazada'])->default('recibida');
            $table->foreignId('carga_id')->nullable()->constrained('cargas')->cascadeOnUpdate()->nullOnDelete(); // cuando consolidada
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->unique(['recepcion_id', 'version'], 'unq_version_recepcion');
            $table->index(['estado'], 'idx_rv_estado');
        });

        // FK circular cargas.recepcion_version_id → recepcion_versiones (ya existen ambas tablas)
        Schema::table('cargas', function (Blueprint $table) {
            $table->foreign('recepcion_version_id')->references('id')->on('recepcion_versiones')
                ->cascadeOnUpdate()->nullOnDelete();
        });

        // DAT-11 recepcion_observaciones — RF-031 (observación/rechazo con texto)
        Schema::create('recepcion_observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('recepciones')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('version_cerrada');      // a qué versión responde
            $table->foreignId('autor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('autor_rol', 20);
            $table->enum('tipo', ['observacion', 'rechazo']);
            $table->text('texto');
            $table->timestamps();

            $table->index(['recepcion_id', 'version_cerrada'], 'idx_obs_recepcion');
        });

        // DAT-12 recepcion_eventos — trazabilidad de estados (RN-056, CA-SEG-06)
        Schema::create('recepcion_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('recepciones')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('recepcion_versiones')->cascadeOnUpdate()->nullOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_eventos');
        Schema::dropIfExists('recepcion_observaciones');
        Schema::table('cargas', function (Blueprint $table) {
            $table->dropForeign(['recepcion_version_id']);
        });
        Schema::dropIfExists('recepcion_versiones');
        Schema::dropIfExists('recepciones');
    }
};
