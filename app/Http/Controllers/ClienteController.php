<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Cliente;
use App\Cdireccion;
use Validator;

class ClienteController extends Controller
{
    public function index(){
        config()->set('database.connections.mysql.strict', false);//se agrega este codigo para deshabilitar el forzado de mysql
        $clientes = DB::table('cliente')
        ->join('cdireccion','cdireccion.idCliente','=','cliente.idCliente')
        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
        ->select('cliente.*','cdireccion.*','tipocliente.Nombre as nombreTipoC')
        ->groupBy('cliente.idCliente')
        ->get();
        return response()->json([
            'code'      =>  200,
            'status'    =>  'success',
            'clientes'  =>  $clientes
        ]);
    }
    public function indexTipocliente(){
        $tipocliente = DB::table('tipocliente')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'tipocliente'   =>  $tipocliente
        ]);
    }
    public function registerCliente(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);
        if(!empty($params) && !empty($params_array)){
            $params_array = array_map('trim',$params_array);

             $validate = Validator::make($params_array, [
                 'nombre'       => 'required',
                 'rfc'    => 'required',
                 'correo'      => 'required',
                 'credito'   => 'required',
                 'idStatus'   => 'required',
                 'idTipo'   => 'required'
             ]);

             if($validate->fails()){
                 $data = array(
                     'status'    => 'error',
                     'code'      => 404,
                     'message'   => 'Fallo la validacion de los datos del cliente',
                     'errors'    => $validate->errors()
                 );
             }else{
                try{
                    DB::beginTransaction();
                    $Cliente = new Cliente();

                    $Cliente->nombre = $params_array['nombre'];
                    if( isset($params_array['aPaterno']) && isset($params_array['aMaterno'])){
                        $Cliente->aPaterno = $params_array['aPaterno'];
                        $Cliente->aMaterno = $params_array['aMaterno'];
                    }
                    $Cliente->rfc = $params_array['rfc'];
                    $Cliente->correo = $params_array['correo'];
                    $Cliente->credito = $params_array['credito'];
                    $Cliente->idStatus = $params_array['idStatus'];
                    $Cliente->idTipo = $params_array['idTipo'];
                    $Cliente->save();
                    
                    DB::commit();

                    $data = array(
                        'code'      =>  200,
                        'status'    => 'success',
                        'message'   =>  'Cliente registrado'
                    );
                }catch(\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   =>  'Algo salio mal rollbak',
                        'error' => $e
                    );
                }

            }

        }else{
            $data = array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);
    }
    public function registerCdireccion(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json,true);
        if(!empty($params_array)){
            $params_array = array_map('trim',$params_array);

             $validate = Validator::make($params_array, [
                 'pais'       => 'required',
                 'estado'    => 'required',
                 'ciudad'      => 'required',
                 'colonia'   => 'required',
                 'calle'   => 'required',
                 'numExt'   => 'required',
                 'cp'   => 'required',
                 'referencia'   => 'required',
                 'telefono'   => 'required',
                 'idZona'   => 'required'
             ]);

             if($validate->fails()){
                 $data = array(
                     'status'    => 'error',
                     'code'      => 404,
                     'message'   => 'Fallo la validacion de los datos del cliente',
                     'errors'    => $validate->errors()
                 );
             }else{
                  //consuktamos el ultimo insertado
                    $Cliente = Cliente::latest('idCliente')->first();
                    $cdireccion = new Cdireccion();
                    $cdireccion->idCliente = $Cliente->idCliente;
                    $cdireccion->pais = $params_array[''];
                    $cdireccion->estado = $params_array[''];
                    $cdireccion->ciudad = $params_array[''];
                    $cdireccion->colonia = $params_array[''];
                    $cdireccion->calle = $params_array[''];
                    $cdireccion->numExt = $params_array[''];
                    $cdireccion->numInt = $params_array[''];
                    $cdireccion->cp = $params_array[''];
                    $cdireccion->referencia = $params_array[''];
                    $cdireccion->telefono = $params_array[''];
                    $cdireccion->idZona = $params_array[''];
                    $cdireccion->save();

             }
        }else{
            $data = array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);
    }
}

/** */