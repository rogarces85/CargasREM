<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-04 series — PK código Minsal (A, BS, BM, D, P, F)
        Schema::create('series', function (Blueprint $table) {
            $table->string('codigo', 2)->primary();
            $table->string('nombre', 60);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
