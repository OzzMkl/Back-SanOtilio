<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Ventascre extends Model
{
    protected $table = 'ventascre';
    protected $primaryKey = 'idVenta';
    protected $fillable = [
        'idCliente',
        'cdireccion',
        'idTipoVenta',
        'autorizaV',//usuario que autorizo la venta algun jefe
        'autorizaC',//usuario que autorizo la venta A CREDITO algun jefe
        'observaciones',
        'idStatusCaja',
        'idStatusEntregas',
        'fecha',
        'idEmpleadoG',//Empleado que genero la venta
        'idEmpleadoC',//Empleado que movio a credito la venta
        'idEmpleadoF',//Usuarui que finalizo la venta
        'subtotal',
        'descuento',
        'total' 
    ];
}
