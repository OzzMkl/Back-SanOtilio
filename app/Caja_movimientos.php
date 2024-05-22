<?php

namespace App;

use App\models\Tipo_movimiento;
use App\models\tipo_pago;
use Illuminate\Database\Eloquent\Model;

class Caja_movimientos extends Model
{
    protected $table = 'caja_movimientos';
    protected $primaryKey = 'idMovCaja';
    protected $fillable = [
        'idCaja',
        'totalNota',
        'idTipoMov',
        'idTipoPago',
        'pagoCliente',
        'cambioCliente',
        'idOrigen',
        'autoriza',
        'observaciones'
    ];   

    public function tipo_movimiento(){
        return $this->belongsTo(Tipo_movimiento::class, 'idTipoMov','idTipo');
    }

    public function tipo_pago(){
        return $this->belongsTo(tipo_pago::class, 'idTipoPago', 'idt');
    }
}
