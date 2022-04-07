<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
