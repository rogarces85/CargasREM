<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // establecimiento_series — DAT-05 (RF-020; siembra por PEND-008)
        Schema::create('establecimiento_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('serie', 2);
            $table->boolean('requerida')->default(true);
            $table->timestamps();

            $table->foreign('serie')->references('codigo')->on('series')->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['establecimiento_id', 'serie'], 'unq_estab_serie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimiento_series');
    }
};
