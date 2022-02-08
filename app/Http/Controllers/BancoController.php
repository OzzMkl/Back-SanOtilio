<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Banco;

class BancoController extends Controller
{
    public function index(){
        //GENERAMOS CONSULTA
        $bancos = DB::table('Bancos')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'proveedores'   =>  $bancos
        ]);
    }
}
