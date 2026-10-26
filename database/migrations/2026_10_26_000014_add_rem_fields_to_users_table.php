<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DAT-03 users — 4 roles (ROL-OPERADOR/SUPERVISOR/ADMIN/ESTABLECIMIENTO), RF-017/RF-018
        // Conserva columnas del scaffold (name/email/password/remember_token) y agrega dominio REM.
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('establecimiento')->after('name');
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->cascadeOnUpdate()->restrictOnDelete()->after('rol');
            $table->enum('tipo_cuenta', ['titular', 'suplente'])->nullable()->after('establecimiento_id');
            $table->text('mfa_secret')->nullable();
            $table->boolean('mfa_habilitado')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();

            $table->index(['establecimiento_id', 'activo'], 'idx_users_estab_activo');
            $table->index('rol', 'idx_users_rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_estab_activo');
            $table->dropIndex('idx_users_rol');
            $table->dropColumn(['rol', 'establecimiento_id', 'tipo_cuenta', 'mfa_secret', 'mfa_habilitado', 'activo', 'intentos_fallidos', 'bloqueado_hasta']);
        });
    }
};
