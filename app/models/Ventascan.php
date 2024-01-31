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
        // 'idTipoPago', ->se cambia por idStatus
        // 'autorizaV', ->se elimina
        // 'autorizaC', ->se elimina
        'observaciones',
        // 'fecha', ->se elimina
        'idEmpleadoG',
        'idEmpleadoC',
        'subtotal',
        'descuento',
        'total' 
    ];
}
