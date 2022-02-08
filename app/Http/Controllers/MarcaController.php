<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Marca;

class MarcaController extends Controller
{
    //
    public function index(){
        //GENERAMOS CONSULTA
        $marca = DB::table('marca')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'marca'   =>  $marca
        ]);
    }
}
