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
        'idTipoVenta',//Paga se lo lleva, etc.
        'idTipoPago',//Por si tiene abonos?
        'autorizaV', // Autoriza venta, ventas especiales
        'autorizaC', //Autoriza cancelacion algun jefe encargado etc.
        'observaciones',
        'idStatusCaja',
        'idStatusEntregas',
        'fecha', //Fecha de alta de venta
        'idEmpleadoG',//Empleado que dio de alta la venta
        'idEmpleadoC',//Empleado que realizo la cancelacion
        'subtotal',
        'descuento',
        'total' 
    ];
}
