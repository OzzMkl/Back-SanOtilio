<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Empleado extends Model
{
    
    //
    protected $table = 'empleado';
    protected $primaryKey = 'idEmpleado';//Se agrega para especificar el id de la tabla
    protected $fillable = [
       'nombre', 'apaterno','amaterno','estado','ciudad','colonia','calle','numExt','numInt','cp','celular','telefono','email', 'contrasena',
    ];
}
