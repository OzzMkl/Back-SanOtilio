<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\models\Empresa;

class EmpresaController extends Controller
{
    public function index(){
        $empresa = DB::table('empresa')
        ->get();
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'empresa'   => $empresa
        ]);
    }
}
