<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';
    protected $primaryKey = 'idCotiza';
    protected $fillable = [
        'idCliente',
        'cdireccion',
        'idEmpleado',
        'idStatus',
        'observaciones',
        'subtotal',
        'descuento',
        'total'
    ];
}
