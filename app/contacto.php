<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class contacto extends Model
{
    //
    protected $table = 'contactos';
    protected $primaryKey = 'idContacto';
    protected $fillable = [
        'idProveedor','nombre','email','telefono','puesto'
    ];
}
