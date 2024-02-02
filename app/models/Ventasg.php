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
        'idStatusCaja',
        'idStatusEntregas',
        'idEmpleado',
        'subtotal',
        'descuento',
        'total' 
    ];
}
