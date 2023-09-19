<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_ventascan extends Model
{
    protected $table = 'productos_ventascan';
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
