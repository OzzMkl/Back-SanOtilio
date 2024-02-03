<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_ventasf extends Model
{
    protected $table = 'productos_ventasf';
    protected $fillable = [
        'idVenta',
        'idProducto',
        'descripcion',
        'claveEx',
        'idProdMedida',
        'cantidad',
        'precio',
        'descuento',
        'total',
        'igualMedidaMenor'
    ];
}
