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
        $departamentos = DB::table('Departamentos')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'departamentos'   =>  $departamentos
        ]);
    }
 
}
