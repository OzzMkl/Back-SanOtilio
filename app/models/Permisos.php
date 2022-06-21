<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Permisos extends Model
{
    protected $table = 'permisos';
    protected $fillable =[
        'idRol','idModulo','idSubModulo','ver',
        'editar','agregar','cancelar','pdf'
    ];
}
