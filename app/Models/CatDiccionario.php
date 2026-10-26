<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** CAT-17 cat_diccionario — prestaciones por serie/hoja/posición. */
class CatDiccionario extends Model
{
    public $timestamps = false;

    protected $table = 'cat_diccionario';

    protected $fillable = ['codigo_prestacion', 'mes', 'anio', 'texto_prestacion', 'hoja', 'serie',
        'posicion', 'tipo_dato', 'linea', 'inicio', 'fin'];

    protected $casts = ['mes' => 'int', 'anio' => 'int', 'posicion' => 'int', 'linea' => 'int',
        'inicio' => 'int', 'fin' => 'int'];
}
