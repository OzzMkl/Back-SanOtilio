<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;

class Producto_precioController extends Controller
{
    public function registraPrecio(Request $request){
        
        $json = $request -> input('json', null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            echo "todo ok";
        } else {
            echo "algo malo";
        }
    }
}
