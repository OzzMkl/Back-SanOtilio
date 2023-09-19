<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_traspasoE extends Model
{
    protected $table = 'productos_traspasoe';
    protected $fillable = [
        'idTraspasoE','idProducto','descripcion',
        'claveEx','idProdMedida','cantidad','precio',
        'subtotal','igualMedidaMenor'
    ];
}
