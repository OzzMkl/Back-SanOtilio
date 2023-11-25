<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Abono_venta extends Model
{
    protected $table = 'abonoventas';
    protected $primaryKey = 'idAbonoVentas';
    protected $fillable = [
        'idVenta',
        'abono',
        'totalAnterior',
        'totalActualizado',
        'idEmpleado',
        'pc'
    ];
}
