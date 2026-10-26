<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'establecimiento_id',
        'tipo_cuenta',
        'mfa_habilitado',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
            'mfa_habilitado' => 'boolean',
            'activo' => 'boolean',
            'intentos_fallidos' => 'int',
            'bloqueado_hasta' => 'datetime',
        ];
    }

    /** DAT-03: MFA obligatorio en producción para admin/supervisor (TEC-15/RN-014). */
    public function requiereMfa(): bool
    {
        return $this->rol->esInterno();
    }

    public function isEstablecimiento(): bool
    {
        return $this->rol === RolUsuario::Establecimiento;
    }

    public function esInterno(): bool
    {
        return $this->rol->esInterno();
    }

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }
}
