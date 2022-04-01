<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Productos_compra extends Model
{
    protected $table = 'productos_compra';
    protected $fillable = [
        'idCompra','idProducto','cantidad','precio','idImpuesto'
    ];
}
