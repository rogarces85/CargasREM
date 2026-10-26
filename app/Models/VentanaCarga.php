<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DAT-07 ventanas_carga — RF-019, RN-042..RN-044 / LIM-013. */
class VentanaCarga extends Model
{
    protected $table = 'ventanas_carga';

    protected $fillable = ['servicio_id', 'anio', 'mes', 'apertura', 'cierre', 'habilitada', 'notas', 'creada_por'];

    protected $casts = [
        'servicio_id' => 'int',
        'anio' => 'int',
        'mes' => 'int',
        'apertura' => 'datetime',
        'cierre' => 'datetime',
        'habilitada' => 'boolean',
        'creada_por' => 'int',
    ];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'id');
    }
}
