<?php

namespace App\Models;

use App\Enums\TipoFeriado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** DAT-06 feriados — ventanas (RN-042/RN-050, RNF-020). */
class Feriado extends Model
{
    protected $table = 'feriados';

    protected $fillable = ['fecha', 'tipo', 'descripcion', 'anio'];

    protected $casts = [
        'fecha' => 'date',
        'anio' => 'int',
        'tipo' => TipoFeriado::class,
    ];

    public function scopeDelAnio(Builder $q, int $anio): Builder
    {
        return $q->where('anio', $anio);
    }

    /** RNF-020: regional Los Lagos cuenta sobre las ventanas del servicio. */
    public function scopeAfectaLosLagos(Builder $q): Builder
    {
        return $q->whereIn('tipo', ['nacional', 'regional_los_lagos']);
    }
}
