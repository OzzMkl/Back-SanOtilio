<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class moviproduc extends Model
{
    protected $table = 'moviproduc';
    protected $primaryKey = 'idMovimiento';
    protected $fillable = [
        'idProducto','claveEx','accion','folioAccion',
        'cantidad','stockanterior','stockactualizado',
        'idUsuario','pc'
    ];
}
