<?php

namespace App\Models;

use App\Enums\EstadoCarga;
use App\Enums\OrigenCarga;
use App\Models\Concerns\AislamientoEstablecimiento;
use App\Models\Concerns\ScopeServicioActivo;
use Illuminate\Database\Eloquent\Model;

/** DAT-08 cargas — RF-001..RF-005, RF-027. */
class Carga extends Model
{
    use AislamientoEstablecimiento, ScopeServicioActivo;

    protected $fillable = ['servicio_id', 'serie', 'anio', 'mes', 'establecimiento_id', 'archivo',
        'hash_sha256', 'origen', 'estado', 'usuario_id', 'recepcion_version_id'];

    protected $casts = [
        'servicio_id' => 'int',
        'anio' => 'int',
        'mes' => 'int',
        'origen' => OrigenCarga::class,
        'estado' => EstadoCarga::class,
        'usuario_id' => 'int',
        'recepcion_version_id' => 'int',
    ];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function serieRegistro()
    {
        return $this->belongsTo(Serie::class, 'serie', 'codigo');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function recepcionVersion()
    {
        return $this->belongsTo(RecepcionVersion::class);
    }

    public function versionesRecepcion()
    {
        return $this->hasMany(RecepcionVersion::class);
    }

    public function datosCarga()
    {
        return $this->hasMany(DatosCarga::class);
    }

    public function datosControlCarga()
    {
        return $this->hasMany(DatosControlCarga::class);
    }

    public function logErrores()
    {
        return $this->hasMany(LogError::class);
    }

    /** Etiqueta legible estándar de la carga (estab/serie/mes/año). */
    public function getEtiquetaAttribute(): string
    {
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
            'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return sprintf('%s (%s %s/%d vía %s)',
            $this->serie, $meses[$this->mes - 1], $this->anio, $this->establecimiento?->codigo_minsal ?? '-', $this->origen->value);
    }
}
