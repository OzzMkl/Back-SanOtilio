<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_traspasoR extends Model
{
    protected $table = 'productos_traspasor';
    protected $fillable = [
        'idTraspasoR','idProducto','descripcion',
        'claveEx','idProdMedida','cantidad','precio',
        'subtotal','igualMedidaMenor'
    ];
}
