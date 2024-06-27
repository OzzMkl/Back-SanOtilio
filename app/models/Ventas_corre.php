<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Ventas_corre extends Model
{
    protected $table = 'ventas_corre';
    protected $primaryKey = 'idVentaCorre';
    protected $fillable = [
        'idVenta',
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
