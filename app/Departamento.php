<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    //
    protected $table = 'departamentos';
    protected $primaryKey = 'idDep';
    protected $fillable = [
        'nombre'
    ];
}
