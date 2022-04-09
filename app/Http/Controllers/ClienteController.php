<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Cliente;
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
        $json = $request -> input('json',null);
        //$params = json_decode($json);
        $params_array = json_decode($json, true);
        if( !empty($params_array)){
            $params_array = array_map('trim',$params_array);

            $validate = Validator::make($params_array, [
                'nombre'       => 'required',
                'rfc'    => 'required',
                'correo'      => 'required',//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
                'credito'   => 'required',
                'idStatus'   => 'required',
                'idTipo'   => 'required',
                'fechaAlta'   => 'required'
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
                    $Cliente->aPaterno = $params_array['aPaterno'];
                    $Cliente->aMaterno = $params_array['aMaterno'];
                    $Cliente->rfc = $params_array['rfc'];
                    $Cliente->correo = $params_array['correo'];
                    $Cliente->credito = $params_array['credito'];
                    $Cliente->idStatus = $params_array['idStatus'];
                    $Cliente->idTipo = $params_array['idTipo'];
                    $Cliente->fechaAlta = $params_array['fechaAlta'];
                    DB::commit();
                }catch(\Exception $e){
                    DB::rollBack();
                    return response()->json([
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   =>  'Fallo al crear la orden compra Rollback!',
                        'error' => $e
                    ]);
                }

            }

        }
        return response()->json([
            'code'      =>  400,
            'status'    => 'Error!',
            'message'   =>  'json vacio'
        ]);
    }
}
