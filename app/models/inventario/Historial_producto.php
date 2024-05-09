<?php

namespace App\models\inventario;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Historial_producto extends Model
{
    //
    protected $table = 'historial_producto';
    protected $primaryKey = 'idHistorialProducto';
    protected  $fillable = [
        'idEmpleado',
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
        'existenciaG',
    ];

    public static function insertHistorial_producto($producto_with_relations,$idEmpleado){
        
        $historial = new self([
            'idEmpleado' => $idEmpleado,
            'idProducto' => $producto_with_relations['idProducto'],
            'idMarca' => $producto_with_relations['idMarca'],
            'nombreMarca' => $producto_with_relations['marca']['nombre'],
            'idDep' => $producto_with_relations['idDep'],
            'nombreDep' => $producto_with_relations['departamento']['nombre'],
            'idCat' => $producto_with_relations['idCat'],
            'nombreCat' => $producto_with_relations['categoria']['nombre'],
            'claveEx' => $producto_with_relations['claveEx'],
            'cbarras' => $producto_with_relations['cbarras'],
            'descripcion' => $producto_with_relations['descripcion'],
            'stockMin' => $producto_with_relations['stockMin'],
            'stockMax' => $producto_with_relations['stockMax'],
            'imagen' => $producto_with_relations['imagen'],
            'idStatus' => $producto_with_relations['statuss'],
            'nombreStatus' => $producto_with_relations['status']['nombre'],
            'ubicacion' => $producto_with_relations['ubicacion'],
            'claveSat' => $producto_with_relations['claveSat'],
            'tEntrega' => $producto_with_relations['tEntrega'],
            'idAlmacen' => $producto_with_relations['idAlmacen'],
            'nombreAlmacen' => $producto_with_relations['almacen']['nombre'],
            'existenciaG' => $producto_with_relations['existenciaG'],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $historial->save();
    }
}
