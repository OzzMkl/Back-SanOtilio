<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Medidas;

class MedidaController extends Controller
{
    //
    public function index(){
        //GENERAMOS CONSULTA
        $medidas = DB::table('medidas')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'medidas'   =>  $medidas
        ]);
    }
}
