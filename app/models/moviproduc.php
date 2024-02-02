<?php

namespace App\models;
use Carbon\Carbon;

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
        'idUsuario',
        'pc'
    ];

    public static function insertMoviproduc($producto,$accion,$folioAccion,$medidaMenor,$stockAnterior,$stockActualizado,$idEmpleado,$ip){

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
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        
        $moviproduc ->save();
    }
}
