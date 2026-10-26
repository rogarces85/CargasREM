<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** CAT-19 cat_excep — excepciones de columnas por prestación. */
class CatExcep extends Model
{
    public $timestamps = false;

    protected $table = 'cat_excep';

    protected $fillable = ['codigo_prestacion', 'mes', 'anio', 'hoja', 'serie', 'posicion', 'tipo_dato', 'col_excep'];

    protected $casts = ['mes' => 'int', 'anio' => 'int', 'posicion' => 'int', 'col_excep' => 'int'];
}
