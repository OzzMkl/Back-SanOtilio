<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Caja_movimientos extends Model
{
    protected $table = 'caja_movimientos';
    protected $primaryKey = 'idMovCaja';
    protected $fillable = [
        'idCaja','totalNota','idTipoMov','idTipoPago','pagoCliente',
        'cambioCliente','idOrigen','autoriza','observaciones'
    ];   
}
