<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DAT-12 recepcion_eventos — trazabilidad de estados (CA-SEG-06, RN-056). */
class RecepcionEvento extends Model
{
    protected $table = 'recepcion_eventos';
    protected $fillable = ['recepcion_id', 'version_id', 'estado_anterior', 'estado_nuevo', 'usuario_id'];

    public function recepcion()
    {
        return $this->belongsTo(Recepcion::class);
    }

    public function version()
    {
        return $this->belongsTo(RecepcionVersion::class, 'version_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
