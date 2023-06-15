<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_requisiciones extends Model
{
    protected $table = 'productos_requisiciones';
    protected $fillable = [
        'idReq','idProducto','idProdMedida','cantidad','igualMedidaMenor'
    ];
}
