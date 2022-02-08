<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Almacenes;

class AlmacenesController extends Controller
{
    public function index(){
        //GENERAMOS CONSULTA
        $almacenes = DB::table('almacenes')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'almacenes'   =>  $almacenes
        ]);
    }
}
