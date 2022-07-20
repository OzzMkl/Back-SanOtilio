<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Caja;
use App\Caja_movimientos;
use Validator;

class CajasController extends Controller
{
    public function aperturaCaja(Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            $params_array = array_map('trim', $params_array);

            $validate = Validator::make($params_array, [
                'horaI'        => 'required',
                //'horaF'           => 'required',
                'fondo'        => 'required',
                //'pc'       => 'required',
                'idEmpleado'      => 'required'
            ]);

            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo la validacion de los datos del cliente',
                    'errors'    => $validate->errors()
                );
            }else{
                $nombre_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                $Caja = new Caja();

                $Caja->horaI = $params_array['horaI'];
                //$Caja->horaF = $params_array['horaF'];
                $Caja->pc = $nombre_host;
                $Caja->fondo = $params_array['fondo'];
                $Caja->idEmpleado = $params_array['idEmpleado'];

                $Caja->save();

                $data = array(
                    'code'      =>  200,
                    'status'    =>  'success',
                    'message'   =>  'Registro correcto'
                );
            }

        } else{
            $data = array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);
    }
    public function cierreCaja($idCaja, Request $request){
        $json = $request -> input('json', null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            //eliminamos espacios vacios
            $params_array = array_map('trim', $params_array);
            //actualizamos
            $Caja = Caja::where('idCaja',$idCaja)->update($params_array);

            $data = array(
                'code'      =>  200,
                'status'    => 'success',
                'caja'   => $params_array
            );

        } else{
            $data = array(
                'code'      =>  400,
                'status'    => 'error',
                'caja'   => 'Algo salio mal'
            );
        }
        return response()->json($data, $data['code']);
    }
    public function verificarCaja($idEmpleado){
        $Caja = Caja::latest('idCaja')->where('idEmpleado',$idEmpleado)->first();
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'caja'      => $Caja
        ]);
    }
    public function cobroNota($idVenta){

    }
}
/********************* */
