<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** CAT-21 cat_versiones — mapeo serie Excel ↔ serie interna por mes/año (RN-013). */
class CatVersion extends Model
{
    public $timestamps = false;

    protected $table = 'cat_versiones';

    protected $fillable = ['mes', 'anio', 'version', 'serie', 'serie_excel'];

    protected $casts = ['mes' => 'int', 'anio' => 'int'];
}
