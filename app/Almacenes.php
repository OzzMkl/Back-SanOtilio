<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Almacenes extends Model
{
    protected $table = 'almacenes';
    protected $primaryKey = 'idAlmacen';
    protected $fillable = [
        'idEmpleado','nombre','direccion'
    ];
}
