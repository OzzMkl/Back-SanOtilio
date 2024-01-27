<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Ventasg extends Model
{
    protected $table = 'ventasg';
    protected $primaryKey = 'idVenta';
    protected $fillable = [
        'idCliente',
        'cdireccion',
        'idTipoVenta',
        'observaciones',
        'idStatus',
        'idEmpleado',
        'subtotal',
        'descuento',
        'total' 
    ];
}
