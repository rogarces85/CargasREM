<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DAT-02 establecimientos — RN-010, RF-018.
 */
class Establecimiento extends Model
{
    protected $fillable = ['servicio_id', 'codigo_minsal', 'nombre', 'direccion', 'telefono',
        'idcomuna', 'iddependencia', 'idtipo', 'propiedad', 'activo'];

    protected $casts = [
        'servicio_id' => 'int',
        'idcomuna' => 'int',
        'iddependencia' => 'int',
        'idtipo' => 'int',
        'activo' => 'boolean',
    ];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function cargas(): HasMany
    {
        return $this->hasMany(Carga::class);
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class);
    }

    public function seriesAsignadas()
    {
        return $this->hasMany(EstablecimientoSerie::class);
    }
}
