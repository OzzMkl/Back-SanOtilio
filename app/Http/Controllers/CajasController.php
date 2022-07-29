<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Caja;
use App\Caja_movimientos;
use App\models\Ventasg;
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
    public function cobroVenta($idVenta,Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);
        
        if(!empty($params) && !empty($params_array)){
            
                try{
                    DB::beginTransaction();

                    foreach($params_array as $param => $paramdata){
                        //creamos el modelo
                        $caja_movimientos = new Caja_movimientos;
                        //asginamos datos
                        $caja_movimientos->idCaja = $paramdata['idCaja'];
                        $caja_movimientos->totalNota = $paramdata['totalNota'];
                        $caja_movimientos->idTipoMov = $paramdata['idTipoMov'];
                        $caja_movimientos->pagoCliente = $paramdata['pagoCliente'];
                    
                        //si los siguientes datos existen los guardamos
                        if(isset($paramdata['idOrigen'])){
                            $caja_movimientos->idOrigen = $paramdata['idOrigen'];
                        }
                        if(isset($paramdata['idTipoPago'])){
                            $caja_movimientos->idTipoPago = $paramdata['idTipoPago'];
                        }
                        if(isset($paramdata['autoriza'])){
                            $caja_movimientos->autoriza = $paramdata['autoriza'];
                        }
                        if(isset($paramdata['observaciones'])){
                            $caja_movimientos->observaciones = $paramdata['observaciones'];
                        }
                        if(isset($paramdata['cambioCliente'])){
                            $caja_movimientos->cambioCliente = $paramdata['cambioCliente'];
                        }

                        //por ultimo guardamos
                        $caja_movimientos->save();
                    }

                    /*actualizamos venta*/
                    //primero la buscamos
                    $venta = Ventasg::find($idVenta);
                    //asignamos status a actualizar
                    $venta->idStatus = 4;
                    //guardamos o en su caso actualizamos
                    $venta->save();
                    

                    DB::commit();

                    //generamos array de que el proceso fue correcto
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
            
        } else{
            $data = array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'datos incorrectos'
            );
        }
        return response()->json($data, $data['code']);
    }
    
    // public function indexTipoMovimiento(){
    //     $tipo_movimiento = DB::table('tipo_movimiento')
    //     ->get();
    //     return response()->json([
    //         'code'  => 200,
    //         'status'    => 'success',
    //         'tipo_movimiento'   => $tipo_movimiento
    //     ]);
    // }
}
/********************* */