<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-17 cat_diccionario — RN-015 (posiciona prestación en hoja/columnas)
        Schema::create('cat_diccionario', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_prestacion', 50);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->string('texto_prestacion', 255);
            $table->string('hoja', 5);
            $table->string('serie', 2);
            $table->unsignedSmallInteger('posicion');
            $table->string('tipo_dato', 15);
            $table->unsignedSmallInteger('linea')->nullable();
            $table->unsignedTinyInteger('inicio')->nullable();
            $table->unsignedTinyInteger('fin')->nullable();
            $table->index(['serie', 'anio', 'hoja', 'mes'], 'idx_dict_serie_anio_hoja_mes');
            $table->index(['serie', 'anio', 'mes', 'codigo_prestacion'], 'idx_dict_busqueda');
        });

        // DAT-18 cat_thojas — hojas/rangos por serie/año (RN-014)
        Schema::create('cat_thojas', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 2);
            $table->string('hoja', 5);
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes')->nullable();
            $table->string('rango', 30);
            $table->index(['serie', 'anio'], 'idx_thojas_serie_anio');
        });

        // DAT-19 cat_excep — celdas ignoradas por prestación/hoja/serie (RN-016)
        Schema::create('cat_excep', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_prestacion', 50);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->string('hoja', 5);
            $table->string('serie', 2);
            $table->unsignedSmallInteger('posicion')->nullable();
            $table->string('tipo_dato', 15)->nullable();
            $table->unsignedTinyInteger('col_excep')->nullable();
            $table->index(['serie', 'anio'], 'idx_excep_serie_anio');
        });

        // DAT-20 cat_colexcep — columnas exceptuadas por prestación (RN-017)
        Schema::create('cat_colexcep', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_pres', 50);
            $table->unsignedTinyInteger('col');
            $table->index('codigo_pres', 'idx_colexcep_prestacion');
        });

        // DAT-21 cat_versiones — mapeo SerieExcel ↔ serie interna (RN-013 / LIM-005)
        Schema::create('cat_versiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->string('version', 60);
            $table->string('serie', 10);
            $table->string('serie_excel', 10);
            $table->unique(['serie_excel', 'anio', 'mes'], 'unq_versiones_serieexcel');
        });

        // DAT-22 cat_software — versión de la BD (RF-015 / RN-030)
        Schema::create('cat_software', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 30)->default('local');
            $table->string('version', 60);
            $table->unique(['nombre', 'version'], 'unq_software');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_software');
        Schema::dropIfExists('cat_versiones');
        Schema::dropIfExists('cat_colexcep');
        Schema::dropIfExists('cat_excep');
        Schema::dropIfExists('cat_thojas');
        Schema::dropIfExists('cat_diccionario');
    }
};
