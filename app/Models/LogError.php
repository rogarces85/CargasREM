<?php

namespace App\Models;

use App\Models\Concerns\AislamientoEstablecimiento;
use Illuminate\Database\Eloquent\Model;

/** DAT-16 log_errores — errores de validación (RF-003/RF-004/RF-009). */
class LogError extends Model
{
    use AislamientoEstablecimiento;

    protected $table = 'log_errores';

    protected $fillable = ['carga_id', 'establecimiento_id', 'serie', 'mes', 'anio', 'hoja',
        'linea', 'dato', 'mensaje_id'];

    protected $casts = ['carga_id' => 'int', 'mes' => 'int', 'anio' => 'int', 'linea' => 'int'];

    public function carga()
    {
        return $this->belongsTo(Carga::class);
    }

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    /** Mensaje completo vía catálogo LIM (lang/es/rem.php). */
    public function getMensajeAttribute(): string
    {
        return $this->mensaje_id ? (string) __($this->mensaje_id) : '';
    }
}
