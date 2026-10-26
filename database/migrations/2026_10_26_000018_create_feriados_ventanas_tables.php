<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-06 feriados — RN-042 (ventana), RN-050, RNF-020
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique('unq_feriados_fecha');
            $table->enum('tipo', ['nacional', 'regional_los_lagos', 'otro'])->default('nacional');
            $table->string('descripcion', 120);
            $table->unsignedSmallInteger('anio');
            $table->timestamps();
        });

        // DAT-07 ventanas_carga — RF-019, RN-042..RN-044
        Schema::create('ventanas_carga', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('servicio_id');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->dateTime('apertura');
            $table->dateTime('cierre');
            $table->boolean('habilitada')->default(true);
            $table->text('notas')->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->foreign('servicio_id')->references('id')->on('servicios')->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['servicio_id', 'anio', 'mes'], 'unq_ventanas_periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanas_carga');
        Schema::dropIfExists('feriados');
    }
};
