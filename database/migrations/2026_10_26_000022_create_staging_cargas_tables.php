<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-016 — Staging por carga (RF-003 vs RF-005):
     * el job del motor escribe VALIDADOS aquí; el consolidador (ConsolidadorService,
     * FASE D T-046) hace DELETE+INSERT transaccional hacia `datos`/`datos_control`
     * (RN-021/RN-022) y PURGA estas tablas por carga_id.
     * Sin timestamps: son tablas transitorias de resultados.
     * FK con cascade ∫: reemplazo de carga (RN-022) borra staging con la carga.
     */
    public function up(): void
    {
        Schema::create('datos_carga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained('cargas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('servicio_id');
            $table->string('serie', 2);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('codigo_prestacion', 50)->nullable();
            $table->string('hoja', 5)->nullable();
            $table->unsignedInteger('linea')->nullable();
            $table->dateTime('fecha_ingreso')->nullable();
            for ($i = 1; $i <= 50; $i++) {
                $table->decimal('col' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 15, 4)->nullable();
            }
            $table->foreign('servicio_id')->references('id')->on('servicios')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            $table->index('carga_id', 'idx_staging_carga');
            $table->index(['serie', 'anio', 'establecimiento_id', 'mes'], 'idx_staging_periodo');
        });

        Schema::create('datos_control_carga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained('cargas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('servicio_id');
            $table->string('serie', 2);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete();
            for ($i = 1; $i <= 5; $i++) {
                $table->decimal('col' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 15, 4)->nullable();
            }
            $table->foreign('servicio_id')->references('id')->on('servicios')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            $table->index('carga_id', 'idx_scarga_carga');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_control_carga');
        Schema::dropIfExists('datos_carga');
    }
};
