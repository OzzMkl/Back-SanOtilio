<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cdireccion extends Model
{
    protected $table = 'cdireccion';
    protected $fillable = [
        'idCliente', 'pais', 'estado', 'ciudad',
        'colonia', 'calle', 'entreCalles', 'numExt',
        'numInt','cp','referencia','telefono','idZona'
    ];
}
