<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Productos_ordenes extends Model
{
    protected $table = 'productos_ordenes';
    protected $fillable = [
        'idOrd','idProducto','cantidad'
    ];
}
