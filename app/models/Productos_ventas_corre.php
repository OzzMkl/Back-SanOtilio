<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_ventas_corre extends Model
{
    protected $table = 'productos_ventas_corre';
    protected $fillable = [
        'idVentaCorre',
        'idVentag',
        'idProducto',
        'descripcion',
        'idProdMedida',
        'cantidad',
        'precio',
        'descuento',
        'total',
        'igualMedidaMenor'
    ];
}
