<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_traspasoE extends Model
{
    protected $table = 'productos_ingresos';
    protected $fillable = [
        'idIngreso','idProducto','descripcion',
        'claveEx','idProdMedida','cantidad','igualMedidaMenor'
    ];
}
