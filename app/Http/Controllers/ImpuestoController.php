<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\impuesto;

class ImpuestoController extends Controller
{
    public function index(){
        //generamos consulta
        $impuestos = DB::table('impuesto')
        ->get();
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'impuestos'  => $impuestos
        ]);
    }

    public function show($idImpuesto){
        config()->set('database.connections.mysql.strict', false);//se agrega este codigo para deshabilitar el forzado de mysql
         //FUNCION QUE DEVULEVE LOS IMPUESTOS DE ACUERDO A SU ID
         $impuestos = DB::table('impuesto')
         ->select('impuesto.*')
         ->where('impuesto.idImpuesto',$idImpuesto)
         ->get();
         if(is_object($impuestos)){
             $data = [
                 'code'          => 200,
                 'status'        => 'success',
                 'impuesto'   =>  $impuestos
             ];
         }else{
             $data = [
                 'code'          => 400,
                 'status'        => 'error',
                 'message'       => 'El impuesto no existe'
             ];
         }
         return response()->json($data, $data['code']);
     }

}
