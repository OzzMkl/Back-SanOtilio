<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Monitoreo extends Model
{
    protected $table = 'monitoreo';
    protected $primaryKey = 'idMonitoreo';
    protected $fillable = [
        'idUsuario','accion','folioAnterior','folioNuevo',
        'pc'
    ];
}
