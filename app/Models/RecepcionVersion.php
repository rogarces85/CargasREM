<?php

namespace App\Models;

use App\Enums\EstadoRecepcionVersion;
use Illuminate\Database\Eloquent\Model;

/** DAT-10 recepcion_versiones — RF-027..RF-031, RN-011/RN-012. */
class RecepcionVersion extends Model
{
    protected $table = 'recepcion_versiones';
    protected $fillable = ['recepcion_id', 'version', 'archivo', 'hash_sha256', 'fecha_carga',
        'en_plazo', 'es_segunda_subida', 'estado', 'carga_id', 'usuario_id'];

    protected $casts = [
        'version' => 'int',
        'fecha_carga' => 'datetime',
        'en_plazo' => 'boolean',
        'es_segunda_subida' => 'boolean',
        'estado' => EstadoRecepcionVersion::class,
        'carga_id' => 'int',
        'usuario_id' => 'int',
    ];

    public function recepcion()
    {
        return $this->belongsTo(Recepcion::class);
    }

    public function carga()
    {
        return $this->belongsTo(Carga::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    /** Evolución de estados desde una versión (CA-SEG-06). */
    public function eventos()
    {
        return $this->hasMany(RecepcionEvento::class, 'version_id');
    }

    public function observaciones()
    {
        return $this->hasMany(RecepcionObservacion::class, 'recepcion_id', 'recepcion_id')
            ->where('version_cerrada', $this->version);
    }
}
