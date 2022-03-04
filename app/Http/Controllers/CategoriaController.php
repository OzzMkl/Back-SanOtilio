<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Categoria;

class CategoriaController extends Controller
{
    //
    public function index(){
        //GENERAMOS CONSULTA
         $categoria = DB::table('categoria')
         ->get();
         return response()->json([
             'code'          =>  200,
             'status'        => 'success',
             'categoria'   =>  $categoria
         ]);

    }
    public function getIdDepa($value){
        $gid = DB::table('categoria')
        ->join('departamentos', 'categoria.idDep','=','departamentos.idDep')
        ->select('categoria.*','departamentos.nombre as nombreDepa')
        ->where('categoria.idDep',$value)
        //->orWhere('departamentos.nombre', $value)
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'gid'   =>  $gid
        ]);
        }
        // public function getCountIdDep($value){
        //     $categoriaC = DB::table('categoria')->where('idDep','=',$value)->count();
        //     return response()->json([
        //      'code'          =>  200,
        //      'status'        => 'success',
        //      'categoriaC'   =>  $categoriaC
        //  ]);
        // }
   
}
