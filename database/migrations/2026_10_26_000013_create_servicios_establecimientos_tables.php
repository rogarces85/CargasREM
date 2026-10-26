<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-01 servicios — PK = idservicio Minsal (23 = S.S. Osorno), RF-021
        Schema::create('servicios', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->autoIncrement();
            $table->string('nombre', 120);
            $table->unsignedTinyInteger('region')->nullable()->index('idx_servicios_region');
            $table->unsignedTinyInteger('orden')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // DAT-02 establecimientos — RN-010, RF-018
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('servicio_id');
            $table->string('codigo_minsal', 7);
            $table->string('nombre', 255);
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->unsignedInteger('idcomuna')->nullable();
            $table->unsignedInteger('iddependencia')->nullable();
            $table->unsignedInteger('idtipo')->nullable();
            $table->string('propiedad', 60)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('servicio_id')->references('id')->on('servicios')->onUpdate('cascade')->onDelete('restrict');
            $table->unique(['servicio_id', 'codigo_minsal'], 'unq_estab_servicio_codigo');
            $table->index('nombre', 'idx_estab_nombre');
            $table->index(['servicio_id', 'activo'], 'idx_estab_servicio_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimientos');
        Schema::dropIfExists('servicios');
    }
};
