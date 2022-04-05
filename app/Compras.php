<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Compras extends Model
{
    protected $table = 'compra';
    protected $primaryKey = 'idCompra';
    protected  $fillable = [
        'idOrd','idPedido','idProveedor',
        'folioProveedor','subtotal',
        'total','idEmpleadoR','idStatus',
        'fechaRecibo','observaciones'
    ];
}
