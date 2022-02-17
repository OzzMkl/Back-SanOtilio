<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\SubCategoria;

class SubCategoriaController extends Controller
{
    //
    public function index(){
        //GENERAMOS CONSULTA
        $subcategoria = DB::table('subcategoria')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'subcategoria'   =>  $subcategoria
        ]);
    }
    public function getIdSuca($value){
        $gisc = DB::table('subcategoria')
        ->join('categoria', 'subcategoria.idCat','=','categoria.idCat')
        ->select('subcategoria.*','categoria.nombre as nombreCat')
        ->where('subcategoria.idCat',$value)
        //->orWhere('categoria.nombre', $value)
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'gisc'   =>  $gisc
        ]);
        }
}
