<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class impresoras extends Model
{
    protected $table = 'impresoras';
    protected $primaryKey = 'idImpresora';
    protected $fillable = [
        'pcVentas',
        'ipVentas',
        'nombreMaquina',
        'nombreImpresora',
        'usuario',
        'contrasena'
    ];
}
