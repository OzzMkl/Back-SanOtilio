<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class moviproduc extends Model
{
    protected $table = 'moviproduc';
    protected $primaryKey = 'idMovimiento';
    protected $fillable = [
        'idProducto',
        'claveEx',
        'accion',
        'folioAccion',
        'cantidad',
        'stockanterior',
        'stockactualizado',
        'idUsuario','pc'
    ];

    public static function insertMoviproduc($producto,$accion,$folioAccion,$medidaMenor,$stockAnterior,$stockActualizado,$idEmpleado){
        $ip = $_SERVER['REMOTE_ADDR'];

        $moviproduc = new self([
            'idProducto' => $producto['idProducto'],
            'claveEx' => $producto['claveEx'],
            'accion' => $accion,
            'folioAccion' => $folioAccion,
            'cantidad' => $medidaMenor,
            'stockanterior' => $stockAnterior,
            'stockactualizado' => $stockActualizado,
            'idUsuario' => $idEmpleado,
            'pc' => $ip,
        ]);
        
        $moviproduc ->save();
    }
}
