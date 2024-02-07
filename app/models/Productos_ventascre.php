<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_ventascre extends Model
{
    protected $table = 'productos_ventascre';
    protected $fillable = [
        'idVenta',
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
