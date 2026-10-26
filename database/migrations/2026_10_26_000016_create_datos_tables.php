<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-13 datos — tabla única por serie (RF-005, RF-014, RF-011; PD-PLAN-05: DECIMAL(15,4))
        Schema::create('datos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('servicio_id');
            $table->string('serie', 2);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('codigo_prestacion', 50)->nullable();
            $table->dateTime('fecha_ingreso')->nullable();
            for ($i = 1; $i <= 50; $i++) {
                $table->decimal('col' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 15, 4)->nullable();
            }
            $table->foreignId('carga_id')->nullable()->constrained('cargas')->cascadeOnUpdate()->nullOnDelete(); // null = histórico migrado/sync
            $table->timestamps();

            $table->foreign('servicio_id')->references('id')->on('servicios')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            // informes RF-007/RF-011 y generación Minsal (serie/año/estab/mes)
            $table->index(['servicio_id', 'serie', 'anio', 'establecimiento_id', 'mes'], 'idx_datos_periodo_estab');
            $table->index(['serie', 'anio', 'codigo_prestacion'], 'idx_datos_prestacion');
        });

        // DAT-14 datos_control — hojas de control por serie (RF-003 RN-019, RF-014)
        Schema::create('datos_control', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('servicio_id');
            $table->string('serie', 2);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete();
            for ($i = 1; $i <= 5; $i++) {
                $table->decimal('col' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 15, 4)->nullable();
            }
            $table->foreignId('carga_id')->nullable()->constrained('cargas')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->foreign('servicio_id')->references('id')->on('servicios')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['servicio_id', 'serie', 'anio', 'establecimiento_id', 'mes'], 'idx_dcontrol_periodo_estab');
            $table->index(['serie', 'anio'], 'idx_dcontrol_serie_anio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_control');
        Schema::dropIfExists('datos');
    }
};
