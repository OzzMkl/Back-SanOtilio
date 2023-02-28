<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    //
    protected $table = 'producto';
    protected $primaryKey = 'idProducto';
    protected  $fillable = [
        'idMarca',
        'idDep',
        'idCat',
        'claveEx',
        'cbarras',
        'descripcion',
        'stockMin',
        'stockMax',
        'imagen',
        'statuss',
        'ubicacion',
        'claveSat',
        'tEntrega',
        'idAlmacen',
        'existenciaG'
    ];
}
