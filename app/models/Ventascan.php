<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Ventascan extends Model
{
    protected $table = 'ventascan';
    protected $primaryKey = 'idVenta';
    protected $fillable = [
        'idCliente',
        'cdireccion',
        'idTipoVenta',
        'idTipoPago',
        'autorizaV',
        'autorizaC',
        'observaciones',
        'fecha',
        'idEmpleadoG',
        'idEmpleadoC',
        'subtotal',
        'descuento',
        'total' 
    ];
}
