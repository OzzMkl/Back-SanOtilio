<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class historialproductos_medidas extends Model
{
    protected $table = 'historialproductos_medidas';
    protected $fillable = [
        'idProductoMedida',
        'idProducto',
        'idMedida',
        'nombreMedida',
        'unidad',
        'precioCompra',
        'porcentaje1','precio1',
        'porcentaje2','precio2',
        'porcentaje3','precio3',
        'porcentaje4','precio4',
        'porcentaje5','precio5',
        'idStatus'
    ];
}
