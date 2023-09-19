<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class traspasoe extends Model
{
    protected $table = 'traspasoe';
    protected $primaryKey = 'idTraspasoE';
    protected  $fillable = [
        'folioD',
        'sucursalE',
        'sucursalR',
        'idEnvio',
        'idEmpleado',
        'idStatus',
        'observaciones'
    ];
    // public function sucursalE()
    // {
    //     return $this->belongsTo(Sucursal::class, 'sucursalE', 'idSuc')->select('nombre as sucursalEN');
    // }

    // public function sucursalR()
    // {
    //     return $this->belongsTo(Sucursal::class, 'sucursalR', 'idSuc')->select('nombre as sucursalRN');
    // }

    // public function empleado()
    // {
    //     return $this->belongsTo(\App\Empleado::class, 'idEmpleado', 'idEmpleado');
    // }

    // public function status()
    // {
    //     return $this->belongsTo(Statuss::class, 'idStatus', 'idStatus');
    // }
}
