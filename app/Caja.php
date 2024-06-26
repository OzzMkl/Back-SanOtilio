<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'caja';
    protected $primaryKey = 'idCaja';
    protected $fillable = [
        'horaI',
        'horaF',
        'fondo',
        'pc',
        'idEmpleado'
    ];

    public function empleado(){
        return $this->belongsTo(Empleado::class, 'idEmpleado', 'idEmpleado');
    }
}
