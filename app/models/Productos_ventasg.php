<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_ventasg extends Model
{
    protected $table = 'productos_ventasg';
    protected $fillable = [
        'idVenta','idProducto','descripcion',
        'idProdMedida','cantidad','precio',
        'descuento','total','igualMedidaMenor'
    ];
}
