<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_facturables extends Model
{
    protected $table = 'productos_facturables';
    protected $primaryKey = 'idProducto';
    protected $fillable = [
       'idProducto', 'existenciaG'
    ];
}
