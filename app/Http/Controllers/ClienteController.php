<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Cliente;

class ClienteController extends Controller
{
    public function index(){
        $clientes = DB::table('cliente')
        ->get();
        return response()->json([
            'code'      =>  200,
            'status'    =>  'success',
            'clientes'  =>  $clientes
        ]);
    }
}
