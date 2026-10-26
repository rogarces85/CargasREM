<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-08 cargas — RF-001..RF-005, RF-027 (origen interno/portal)
        Schema::create('cargas', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('servicio_id');
            $table->string('serie', 2);
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('archivo', 255);
            $table->string('hash_sha256', 64)->nullable();
            $table->enum('origen', ['interno', 'portal'])->default('portal');
            $table->enum('estado', ['pendiente', 'validando', 'validada', 'con_errores', 'guardado', 'rechazado'])->default('pendiente');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedBigInteger('recepcion_version_id')->nullable()->index('idx_cargas_rversion'); // FK creada en migración de recepciones (evita circular)
            $table->timestamps();

            // consultas tipo HistCarga (estab+mes+año+serie — RF-016) y por periodo de servicio
            $table->foreign('servicio_id')->references('id')->on('servicios')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['servicio_id', 'serie', 'anio', 'mes'], 'idx_cargas_periodo_servicio');
            $table->index(['establecimiento_id', 'serie', 'anio', 'mes'], 'idx_cargas_estab_periodo');
            $table->index('estado', 'idx_cargas_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas');
    }
};
