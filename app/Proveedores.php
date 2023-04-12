<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Proveedores extends Model
{
    //
    protected $table = 'proveedores';
    protected $primaryKey = 'idProveedor';
    protected $fillable = [
        'rfc','nombre','pais',
        'estado','ciudad','cpostal',
        'colonia','calle','numero',
        'telefono','creditoDias','creditoCantidad',
        'idStatus'
    ];
}
