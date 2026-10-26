<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-24 generaciones_minsal — historial de generaciones de archivo (RF-014)
        Schema::create('generaciones_minsal', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->string('serie', 2);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('tamano_bytes')->default(0);
            $table->string('hash_sha256', 64)->nullable();
            $table->unsignedInteger('filas_datos')->default(0);
            $table->unsignedInteger('filas_control')->default(0);
            $table->enum('resultado', ['ok', 'fail']);
            $table->text('mensaje')->nullable();
            $table->timestamps();

            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['serie', 'anio'], 'idx_gen_serie_anio');
        });

        // DAT-25 sincronizaciones — agenda de sync de catálogos/histórico (RF-015/RF-016, LIM-017)
        Schema::create('sincronizaciones', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['catalogos', 'historico']);
            $table->enum('fuente', ['xml', 'soap', 'mdb']);
            $table->dateTime('comenzado_en');
            $table->dateTime('terminado_en')->nullable();
            $table->json('tablas_resumen')->nullable();          // {tabla: filas_insertadas_actualizadas}
            $table->enum('estado', ['en_curso', 'completada', 'fallada'])->default('en_curso');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // DAT-26 notificaciones_enviadas — trazabilidad de envíos (RF-033)
        Schema::create('notificaciones_enviadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('tipo', ['recordatorio', 'confirmacion']);
            $table->enum('canal', ['smtp', 'ftp']);
            $table->dateTime('fecha');
            $table->string('referencia', 255)->nullable();
            $table->string('resultado', 60);
            $table->timestamps();

            $table->index(['tipo', 'fecha'], 'idx_notif_tipo_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_enviadas');
        Schema::dropIfExists('sincronizaciones');
        Schema::dropIfExists('generaciones_minsal');
    }
};
