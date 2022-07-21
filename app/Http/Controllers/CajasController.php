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
    public function cobroVenta(Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            
            $params_array = array_map('trim',$params_array);
            $validate = Validator::make($params_array,[
                'idCaja'        =>  'required',
                'dinero'        =>  'required',
                'idTipoMov'     =>  'required',
                //'idTipoPago'    =>  'required',
                'idOrigen'      =>  'required',
                //'autoriza'      =>  'required',
                //'observaciones' =>  'required'
            ]);

            if($validate -> fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo la validacion de los datos del cliente',
                    'errors'    => $validate->errors()
                );
            } else{
                try{
                    DB::beginTransaction();
                    $caja_movimientos = new Caja_movimientos;

                    $caja_movimientos->idCaja = $params_array['idCaja'];
                    $caja_movimientos->dinero = $params_array['dinero'];
                    $caja_movimientos->idTipoMov = $params_array['idTipoMov'];
                    $caja_movimientos->idOrigen = $params_array['idOrigen'];
                
                    if(isset($params_array['idTipoPago'])){
                        $caja_movimientos->idTipoPago = $params_array['idTipoPago'];
                    }
                    if(isset($params_array['autoriza'])){
                        $caja_movimientos->autoriza = $params_array['autoriza'];
                    }
                    if(isset($params_array['observaciones'])){
                        $caja_movimientos->observaciones = $params_array['observaciones'];
                    }

                    $caja_movimientos->save();

                    DB::commit();

                    $data = array(
                        'code'      =>  200,
                        'status'    =>  'success',
                        'message'   =>  'Registro correcto'
                    );
                } catch(\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   =>  'Algo salio mal rollback',
                        'error' => $e
                    );
                }
            }
        } else{
            $data = array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'datos incorrectos'
            );
        }
        return response()->json($data, $data['code']);
    }
}
/********************* */
