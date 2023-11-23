<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Caja;
use App\Caja_movimientos;
use App\models\Ventasg;
use App\models\Abono_venta;
use Validator;

class CajasController extends Controller
{
    //genera insert en la tabla de caja / generamos sesion de caja
    public function aperturaCaja(Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            //eliminamos espacios
            $params_array = array_map('trim', $params_array);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'horaI'        => 'required',
                //'horaF'           => 'required',
                'fondo'        => 'required',
                //'pc'       => 'required',
                'idEmpleado'      => 'required'
            ]);
            //revisamos la validacion
            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo la validacion de los datos del cliente',
                    'errors'    => $validate->errors()
                );
            }else{
                //si no hay errores en la validacion
                //obtenemos el nombre de la maquina
                $nombre_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                //creamos modelo
                $Caja = new Caja();
                //insertamos los datos
                $Caja->horaI = $params_array['horaI'];
                //$Caja->horaF = $params_array['horaF'];
                $Caja->pc = $nombre_host;
                $Caja->fondo = $params_array['fondo'];
                $Caja->idEmpleado = $params_array['idEmpleado'];
                //guardamos
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
    //finalizamos sesion de caja actualizando el campo horaF con la hora de cierre
    public function cierreCaja(Request $request){
        $json = $request -> input('json', null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            $params_array = array_map('trim',$params_array);

            $validate = Validator::make($params_array,[
                'idCaja'    => 'required'
            ]);

            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo la validacion de los datos del cliente',
                    'errors'    => $validate->errors()
                );
            } else{
                //buscamos
                $caja = Caja::find($params_array['idCaja']);
                //actualizamos el valor
                $caja->horaF = date("Y-m-d H:i:s");
                //guardamos
                $caja->save();

                $data= array(
                    'code'  => 200,
                    'status'    =>'success',
                    'caja' => $caja
                );

            }

        } else{
            $data = array(
                'code'      =>  400,
                'status'    => 'error',
                'caja'   => 'Algo salio mal'
            );
        }
        return response()->json($data, $data['code']);
    }
    //traemos la inforamcion de caja de acuerdo al empleado y la ultima que creo
    public function verificarCaja($idEmpleado){
        $Caja = Caja::latest('idCaja')->where('idEmpleado',$idEmpleado)->first();
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'caja'      => $Caja
        ]);
    }
    //generamos cobro de venta / se genera insert en la tabla movimientos_caja
    //registramos que se genero un cobro
    public function cobroVenta($idVenta,Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            
                try{
                    //comenzamos transaccion
                    DB::beginTransaction();

                    //creamos el modelo
                    $caja_movimientos = new Caja_movimientos;
                    //asginamos datos
                    $caja_movimientos->idCaja = $params_array['idCaja'];
                    $caja_movimientos->totalNota = $params_array['totalNota'];
                    $caja_movimientos->idTipoMov = $params_array['idTipoMov'];
                    $caja_movimientos->pagoCliente = $params_array['pagoCliente'];
                
                    //si los siguientes datos existen los guardamos
                    if(isset($paramdata['idOrigen'])){
                        $caja_movimientos->idOrigen = $params_array['idOrigen'];
                    }
                    if(isset($paramdata['idTipoPago'])){
                        $caja_movimientos->idTipoPago = $params_array['idTipoPago'];
                    }
                    if(isset($paramdata['autoriza'])){
                        $caja_movimientos->autoriza = $params_array['autoriza'];
                    }
                    if(isset($paramdata['observaciones'])){
                        $caja_movimientos->observaciones = $params_array['observaciones'];
                    }
                    if(isset($paramdata['cambioCliente'])){
                        $caja_movimientos->cambioCliente = $params_array['cambioCliente'];
                    }

                    //por ultimo guardamos
                    $caja_movimientos->save();

                    //primero la buscamos
                    $venta = Ventasg::find($idVenta);

                    if($params_array['isSaldo'] == true){
                        $abono_venta = new Abono_venta();
                        $abono_venta->idVenta = $params_array['idOrigen'];
                        $abono_venta->abono = $params_array['pagoCliente'];
                        $abono_venta->totalAnterior = $params_array['totalNota'];
                        $abono_venta->totalActualizado = $params_array['saldo_restante'];
                        $abono_venta->idEmpleado = $params_array['idEmpleado'];
                        $abono_venta->pc = gethostbyaddr($_SERVER['REMOTE_ADDR']);

                        $abono_venta->save();

                        $venta->idStatus = 21; // Cobro parcial, no se envia
                    } else{
                        //asignamos status a actualizar
                        $venta->idStatus = 4; // cobrada, no se envia
                    }

                    //guardamos/actualizamos
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
    //trae los id de las cajas que no tienen horafinal registrada
    //dando a entender que la sesion de la caja sigue activa
    public function verificaSesionesCaja(){
        $caja = DB::table('caja')
            ->join('empleado','empleado.idEmpleado','caja.idEmpleado')
            ->select('caja.*',
            DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
            ->where('horaF',null)
            ->get();

        return response()->json([
            'code'  => 200,
            'status'    => 'success',
            'caja'  => $caja
        ]);

    }
    /**Trae los movimientos de caja (cobros,pagos, etc)
     * que se realizaron de acuerdo al idCaja
     */
    public function movimientosSesionCaja($idCaja){
        $caja = DB::table('caja_movimientos')
            ->join('tipo_movimiento','tipo_movimiento.idTipo','caja_movimientos.idTipoMov')
            ->join('tipo_pago','tipo_pago.idt','caja_movimientos.idTipoPago')
            ->select('caja_movimientos.*','tipo_movimiento.nombre as nombreTipoMov','tipo_pago.tipo as nombreTipoPago')
            ->where('idCaja',$idCaja)
            ->get();

        return response()->json([
            'code'  => 200,
            'status'    => 'success',
            'caja'  => $caja
        ]);
    }

    public function abonos_ventas($idVenta){
        $abono_venta = Abono_venta::where('idVenta',$idVenta)
                                    ->select('abonoventas.*',
                                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                                    ->join('empleado','empleado.idEmpleado','=','abonoventas.idEmpleado')
                                    ->get();
                                    // ->map( function($abono_venta){
                                    //         $abono_venta->totalAbono = $abono_venta->abono;
                                    //         return $abono_venta;
                                    //     });
        $totalAbono = $abono_venta->sum('abono');
        $totalActualizado = Abono_venta::where('idVenta',$idVenta)
                                    ->orderBy('idAbonoVentas','desc')
                                    ->value('totalActualizado');

        return response()->json([
                'code' => 200,
                'status' => 'success',
                'abonos' => $abono_venta,
                'total_abono' => $totalAbono,
                'total_actualizado' => $totalActualizado
        ]);    
    }
}
/********************* */