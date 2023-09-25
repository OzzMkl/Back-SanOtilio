<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\models\Sucursal;
use App\models\Empresa;

class SucursalController extends Controller
{
    public function index(){
        $sucursales = Sucursal::all();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'sucursales' => $sucursales,
        ]);
    }
}
