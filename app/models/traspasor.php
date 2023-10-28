<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class traspasor extends Model
{
    protected $table = 'traspasor';
    protected $primaryKey = 'idTraspasoR';
    protected  $fillable = [
        'folio',
        'sucursalE',
        'sucursalR',
        'idEnvio',
        'idEmpleado',
        'idStatus',
        'observaciones'
    ];
    // public function sucursalE()
    // {
    //     return $this->belongsTo(Sucursal::class, 'sucursalE', 'idSuc');
    // }

    // public function sucursalR()
    // {
    //     return $this->belongsTo(Sucursal::class, 'sucursalR', 'idSuc');
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
