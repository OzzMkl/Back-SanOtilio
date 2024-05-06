<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursal';
    protected $primaryKey = 'idSuc';
    protected  $fillable = [
        'nombre',
        'direccion',
        'horario',
        'connection',
    ];
}
