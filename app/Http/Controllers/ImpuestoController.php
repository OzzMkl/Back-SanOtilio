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
}
