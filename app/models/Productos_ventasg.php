<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Productos_ventasg extends Model
{
    protected $table = 'productos_ventasg';
    protected $fillable = [
        'idVenta',
        'idProducto',
        'descripcion',
        'idProdMedida',
        'cantidad',
        'precio',
        'descuento',
        'total',
        'igualMedidaMenor'
    ];

    public static function insertProductoVentasg($idVenta,$producto,$medidaMenor){
        $productos_ventasg = new self([
            'idVenta' => $idVenta,
            'idProducto' => $producto['idProducto'],
            'descripcion' => $producto['descripcion'],
            'idProdMedida' => $producto['idProdMedida'],
            'cantidad' => $producto['cantidad'],
            'precio' => $producto['precio'],
            'total' => $producto['subtotal'],
            'igualMedidaMenor' => $medidaMenor,
            'descuento' => $producto['descuento'],
        ]);
        
        //guardamos el producto
        $productos_ventasg->save();
    }
}
