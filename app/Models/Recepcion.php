<?php

namespace App\Models;

use App\Models\Concerns\AislamientoEstablecimiento;
use App\Models\Concerns\ScopeServicioActivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** DAT-09 recepciones — grupo (estab, serie, mes, año, servicio): UNIQUE. */
class Recepcion extends Model
{
    use AislamientoEstablecimiento, ScopeServicioActivo;

    protected $table = 'recepciones';

    protected $fillable = ['establecimiento_id', 'serie', 'mes', 'anio', 'servicio_id'];

    protected $casts = ['mes' => 'int', 'anio' => 'int', 'servicio_id' => 'int'];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function serieRegistro()
    {
        return $this->belongsTo(Serie::class, 'serie', 'codigo');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(RecepcionVersion::class);
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(RecepcionObservacion::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(RecepcionEvento::class);
    }
}
