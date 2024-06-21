<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Compras extends Model
{
    protected $table = 'ingresos';
    protected $primaryKey = 'idIngreso';
    protected  $fillable = [
        'idEmpleado','idStatus','observaciones'
    ];
}
