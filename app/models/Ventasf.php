<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Ventasf extends Model
{
    protected $table = 'ventasf';
    protected $primaryKey = 'idVenta';
    protected $fillable = [
        'idCliente',
        'cdireccion',
        'idTipoVenta',
        'autorizaV',
        'autorizaC',
        'observaciones',
        'idStatusCaja',
        'idStatusEntregas',
        'fecha',
        'idEmpleado',
        'idEmpleadoC',
        'idEmpleadoF',
        'subtotal',
        'descuento',
        'total' 
    ];
}
