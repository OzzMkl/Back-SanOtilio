<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\Lote;

class LoteController extends Controller
{
    public function register(Request $request){

        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if(!empty($params_array) && !empty($params_array)){

            $params_array = array_map('trim',$params_array);

            $validate = Validator::make($params_array, [
                'codigo'           =>  'required'
            ]);

            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message'   =>  'El lote no se ha creado',
                    'errors'    =>  $validate->errors()
                );
            }else{
                $lote = new Lote();
                $lote -> codigo = $params_array['codigo'];

                if($params_array['caducidad']==null){
                $lote -> caducidad = null;
                }else{
                    $lote -> caducidad = $params_array['caducidad'];
                }

                $lote->save();

                $data = array(//una vez guardado mandamos mensaje de OK
                    'status'    =>  'success',
                    'code'      =>  '200',
                    'message'   =>  'El lote se ha creado correctamente',
                    'lote' =>  $lote
                );
            }

        }else{
            $data = array(
                'status'    =>  'error',
                'code'      =>  '404',
                'message'   =>  'Los datos enviados no son correctos'
            );
        }
        return response()->json($data, $data['code']);//RETORMANOS EL JSON 
    }
    public function index(){
        $loteC = DB::table('lote')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'loteC'   =>  $loteC
        ]);
    }
}
