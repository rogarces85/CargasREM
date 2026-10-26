<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** CAT-20 cat_colexcep — detalle de columnas de excepción. */
class CatColexcep extends Model
{
    public $timestamps = false;

    protected $table = 'cat_colexcep';

    protected $fillable = ['codigo_pres', 'col'];

    protected $casts = ['col' => 'int'];
}
