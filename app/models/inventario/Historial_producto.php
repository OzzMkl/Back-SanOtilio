<?php

namespace App\models\inventario;

use Illuminate\Database\Eloquent\Model;

class Historial_producto extends Model
{
    //
    protected $table = 'historial_producto';
    protected $primaryKey = 'idHistorialProducto';
    protected  $fillable = [
        'idProducto',
        'idMarca',
        'nombreMarca',
        'idDep',
        'nombreDep',
        'idCat',
        'nombreCat',
        'claveEx',
        'cbarras',
        'descripcion',
        'stockMin',
        'stockMax',
        'imagen',
        'idStatus',
        'nombreStatus',
        'ubicacion',
        'claveSat',
        'tEntrega',
        'idAlmacen',
        'nombreAlmacen',
        'existenciaG'
    ];
}
