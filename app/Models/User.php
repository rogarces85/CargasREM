<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolUsuario;
use Filament\Panel;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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

    /**
     * RN-001/MOD-06/MOD-14 — Acceso a paneles por rol:
     * ROL-ESTABLECIMIENTO → Portal (/portal); roles internos → Backoffice (/backoffice).
     * Cuentas inactivas no acceden a ningún panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->activo) {
            return false;
        }

        return match ($panel->getId()) {
            'portal' => $this->isEstablecimiento(),
            'backoffice' => $this->esInterno(),
            default => false,
        };
    }

    /**
     * T-019 — El rol del dominio (users.rol, SPECS ROL-*) se refleja como
     * rol spatie/Shield con el mismo nombre (modo modular 1:1).
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            if ($user->rol) {
                $spatie = \Spatie\Permission\Models\Role::firstOrCreate([
                    'name' => $user->rol->value,
                    'guard_name' => 'web',
                ]);
                $user->assignRole($spatie);
            }
        });
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
