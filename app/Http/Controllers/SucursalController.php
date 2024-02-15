<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\models\Sucursal;
use App\models\Empresa;

class SucursalController extends Controller
{
    public function index(){
        $empresa = Empresa::select('idSuc','nombreCorto')->first();
        $sucursales = Sucursal::all()
                        ->map(function ($obj){
                            $obj->isSelected = false;
                            return $obj;
                        });

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'sucursales' => $sucursales,
            'empresa' => $empresa,
        ]);
    }
}
