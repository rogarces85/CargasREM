<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** CAT-22 cat_software — versiones de formato REM por año (RN-004). */
class CatSoftware extends Model
{
    public $timestamps = false;

    protected $table = 'cat_software';

    protected $fillable = ['nombre', 'version'];
}
