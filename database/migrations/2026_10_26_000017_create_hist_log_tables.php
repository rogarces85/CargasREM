<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-15 hist_carga — histórico de cargas (busca_estable RF-016 / RN-038)
        Schema::create('hist_carga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->string('serie', 2);
            $table->unsignedTinyInteger('servicio_id');
            $table->enum('fuente', ['upload', 'servidor'])->default('upload');
            $table->timestamps();

            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('servicio_id')->references('id')->on('servicios')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['establecimiento_id', 'serie', 'anio', 'mes'], 'idx_hist_estab_periodo');
            $table->index(['servicio_id', 'anio', 'serie'], 'idx_hist_servicio_anio');
        });

        // DAT-16 log_errores — errores de validación (RF-003/RF-004/RF-009)
        Schema::create('log_errores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained('cargas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->cascadeOnUpdate()->nullOnDelete();
            $table->string('serie', 2)->nullable();
            $table->unsignedTinyInteger('mes')->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('hoja', 5)->nullable();
            $table->unsignedInteger('linea')->nullable();
            $table->text('dato')->nullable();                    // valor original que violó la regla
            $table->string('mensaje_id', 20)->nullable();        // referencia LIM-### (lang/es/rem.php)
            $table->timestamps();

            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->nullOnDelete();
            $table->index(['carga_id'], 'idx_logcarga');
            $table->index(['establecimiento_id', 'anio', 'serie', 'mes'], 'idx_logcarga_estab_periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_errores');
        Schema::dropIfExists('hist_carga');
    }
};
