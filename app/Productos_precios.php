<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Productos_precios extends Model
{
    protected $table = 'productos_precios';
    protected $fillable = [
        'idProducto','preciocompra','porcentaje1','precio1',
        'porcentaje2','precio2','porcentaje3','precio3',
        'porcentaje4','precio4','porcentaje5','precio5',
    ];
}
