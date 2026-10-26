<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DAT-05 establecimiento_series — RF-020 (series asignadas al establecimiento). */
class EstablecimientoSerie extends Model
{
    protected $fillable = ['establecimiento_id', 'serie', 'requerida'];

    protected $casts = ['requerida' => 'boolean'];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function serieRegistro()
    {
        return $this->belongsTo(Serie::class, 'serie', 'codigo');
    }
}
