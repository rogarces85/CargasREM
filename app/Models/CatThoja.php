<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** CAT-18 cat_thojas — hojas por serie/año/mes y rangos de lectura. */
class CatThoja extends Model
{
    public $timestamps = false;

    protected $table = 'cat_thojas';

    protected $fillable = ['serie', 'hoja', 'anio', 'mes', 'rango'];

    protected $casts = ['anio' => 'int', 'mes' => 'int'];
}
