<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Monitoreo extends Model
{
    protected $table = 'monitoreo';
    protected $primaryKey = 'idMonitoreo';
    protected $fillable = [
        'idUsuario','accion','folioAnterior','folioNuevo',
        'pc'
    ];

    public static function insertMonitoreo($idUsuario,$accion,$folioAnterior,$folioNuevo,$motivo){
        $ip = $_SERVER['REMOTE_ADDR'];

        $monitoreo = new self([
            'idUsuario' => $idUsuario,
            'accion' => $accion,
            'folioAnterior' => $folioAnterior,
            'folioNuevo' => $folioNuevo,
            'pc' => $ip,
            'motivo' => $motivo
        ]);

        $monitoreo ->save();

    }

}
