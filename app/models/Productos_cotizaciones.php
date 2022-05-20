<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_cotizaciones extends Model
{
    protected $table = 'productos_cotizaciones';
    protected $fillable = [
        'idCotiza','idProducto','precio','cantidad',
        'descripcion','descuento','subtotal'
    ];
}
