<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Tipo_movimiento extends Model
{
    protected $table = 'tipo_movimiento';
    protected $primaryKey = 'idTipo';
    protected $fillable = [
        'nombre',
    ];
}
