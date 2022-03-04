<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Departamento;
class DepartamentoController extends Controller
{
    //
    public function index(){
        //GENERAMOS CONSULTA
        // $departamentos = DB::table('departamentos')
        // ->get();
        // return response()->json([
        //     'code'          =>  200,
        //     'status'        => 'success',
        //     'departamentos'   =>  $departamentos
        // ]);
        $departamentos = DB::table('departamentos')
        ->selectRaw('departamentos.*, count(categoria.idDep) as longitud')
        ->join('categoria','departamentos.idDep','=','categoria.idDep')
        ->groupBy('categoria.idDep')
        ->get();
            return response()->json([
                 'code'          =>  200,
                 'status'        => 'success',
                 'departamentos'   =>  $departamentos
             ]);
    }
 
}
