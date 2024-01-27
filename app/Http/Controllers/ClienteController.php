<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Cliente;
use App\Cdireccion;
use Validator;
use TCPDF;
use App\models\Monitoreo;


class ClienteController extends Controller
{
    /**
     * Litado de clientes
     */
    public function index(){
        $clientes = DB::table('cliente')
                        ->select('cliente.*','tipocliente.Nombre as nombreTipoC',
                            DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),
                            'cdireccion.telefono')
                        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
                        ->leftjoin('cdireccion', function ($join){
                            $join->on('cdireccion.idCliente','=','cliente.idCliente')
                                    ->where('cdireccion.created_at', '=', DB::raw('(SELECT MAX(created_at) FROM cdireccion WHERE cdireccion.idCliente = cliente.idCliente)'));
                        })
                        ->orderBy('cliente.idCliente')
                        ->paginate(10);

        // $clientes = DB::table('cliente')
        //                 ->select(
        //                     'cliente.*',
        //                     'tipocliente.Nombre as nombreTipoC',
        //                     DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),
        //                     DB::raw("MAX(cdireccion.telefono) as telefono")
        //                 )
        //                 ->join('tipocliente', 'tipocliente.idTipo', '=', 'cliente.idTipo')
        //                 ->leftjoin('cdireccion', function ($join){
        //                     $join->on('cdireccion.idCliente','=','cliente.idCliente');
        //                 })
        //                 ->groupBy('cliente.idCliente')
        //                 ->orderBy('cliente.idCliente')
        //                 ->paginate(10);

        return response()->json([
            'code'      =>  200,
            'status'    =>  'success',
            'clientes'  =>  $clientes
        ]);
    }

    /**
     * Listado de los tipos de clientes
     */
    public function indexTipocliente(){
        $tipocliente = DB::table('tipocliente')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'tipocliente'   =>  $tipocliente
        ]);
    }

    /**
     * Regitro de cliente nuevo
     */
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
                    if( isset($params_array['aPaterno'])){
                        $Cliente->aPaterno = $params_array['aPaterno'];
                    }
                    if(isset($params_array['aMaterno'])){
                        $Cliente->aMaterno = $params_array['aMaterno'];
                    }
                    $Cliente->rfc = $params_array['rfc'];
                    $Cliente->correo = $params_array['correo'];
                    $Cliente->credito = $params_array['credito'];
                    $Cliente->idStatus = $params_array['idStatus'];
                    $Cliente->idTipo = $params_array['idTipo'];
                    $Cliente->save();

                    //Obtenemos el ultimo cliente registrado
                    $idCliente = Cliente::latest('idCliente')->first()->idCliente;
                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];
                    //insertamos movimiento en monitoreo
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['sub'];
                    $monitoreo -> accion =  "Alta de cliente";
                    $monitoreo -> folioNuevo =  $idCliente;
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();
                    
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
                        'message'   =>  $e->getMessage(),
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

    /**
     * Registro de direccion de cliente nuevo
     */
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
                     'message'   => 'Datos incorrectos en la direccion',
                     'errors'    => $validate->errors()
                 );
             }else{
                  //consuktamos el ultimo insertado
                    $idCliente = Cliente::latest('idCliente')->first()->idCliente;
                    $cdireccion = new Cdireccion();
                    $cdireccion->idCliente = $idCliente;
                    $cdireccion->pais = $params_array['pais'];
                    $cdireccion->estado = $params_array['estado'];
                    $cdireccion->ciudad = $params_array['ciudad'];
                    $cdireccion->colonia = $params_array['colonia'];
                    $cdireccion->calle = $params_array['calle'];
                    $cdireccion->entreCalles = $params_array['entreCalles'];
                    $cdireccion->numExt = $params_array['numExt'];
                    $cdireccion->numInt = $params_array['numInt'];
                    $cdireccion->cp = $params_array['cp'];
                    $cdireccion->referencia = $params_array['referencia'];
                    $cdireccion->telefono = $params_array['telefono'];
                    $cdireccion->idZona = $params_array['idZona'];
                    $cdireccion->save();

                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];
                    //insertamos movimiento en monitoreo
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['sub'];
                    $monitoreo -> accion =  "Alta de direcion de cliente";
                    $monitoreo -> folioNuevo =  $idCliente;
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    $data = array(
                        'code'      =>  200,
                        'status'    => 'success',
                        'message'   =>  'Direccion registrada correctamente'
                    );
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

    public function registrarNuevaDireccion(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json,true);
        if(!empty($params_array)){
            $params_array = array_map('trim',$params_array);

            $validate = Validator::make($params_array, [
                'idCliente'       => 'required',
                'pais'       => 'required',
                'estado'    => 'required',
                'ciudad'      => 'required',
                'colonia'   => 'required',
                'calle'   => 'required',
                'numExt'   => 'required',
                'referencia'   => 'required',
                'cp'   => 'required',
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
                $Ndireccion = new Cdireccion();
                    $Ndireccion->idCliente = $params_array['idCliente'];
                    if( isset($params_array['numInt'])){
                        $Ndireccion->numInt = $params_array['numInt'];
                    }
                    if(isset($params_array['entreCalles'])){
                        $Ndireccion->entreCalles = $params_array['entreCalles'];
                    }
                    $Ndireccion->pais = $params_array['pais'];
                    $Ndireccion->estado = $params_array['estado'];
                    $Ndireccion->ciudad = $params_array['ciudad'];
                    $Ndireccion->colonia = $params_array['colonia'];
                    $Ndireccion->calle = $params_array['calle'];
                    $Ndireccion->numExt = $params_array['numExt'];
                    $Ndireccion->referencia = $params_array['referencia'];
                    $Ndireccion->cp = $params_array['cp'];
                    $Ndireccion->telefono = $params_array['telefono'];
                    $Ndireccion->idZona = $params_array['idZona'];
                    $Ndireccion->save();

                    $data = array(
                        'code'      =>  200,
                        'status'    => 'success',
                        'message'   =>  'Direccion registrada'
                    );
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

    public function getDetallesCliente($idCliente){
        $cliente = DB::table('cliente')
        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
        ->join('statuss','statuss.idStatus','=','cliente.idStatus')
        ->select('cliente.*','tipocliente.Nombre as nombreTipoC','statuss.nombre as nombreStatus')
        ->where('cliente.idCliente',$idCliente)
        ->get();
        $cdireccion = DB::table('cdireccion')
        ->join('zona','zona.idZona','=','cdireccion.idZona')
        ->select('cdireccion.*','zona.nombre as nombreZona')
        ->where('cdireccion.idCliente',$idCliente)
        ->get();
        return response()->json([
            'code'          => 200,
            'status'        => 'success',
            'cliente'       => $cliente,
            'cdireccion'    => $cdireccion
        ]);
    }

    public function getDireccionCliente($idCliente){
        $direccion = DB::table('cdireccion')
        ->where('idCliente','=',$idCliente)
        ->get();
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'direccion' => $direccion
        ]);
    }

    public function updateCliente($idCliente, Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        
        if(!empty($params_array)){
             //eliminar espacios vacios
             $params_array = array_map('trim', $params_array);

            //obtenemos la informacion antes de actualizar
            $antCliente = Cliente::where('idCliente',$idCliente)->get();
            //actualizamos
            $cliente = Cliente::where('idCliente',$idCliente)->update([
                'nombre' => $params_array['nombre'],
                'aPaterno' => $params_array['aPaterno'],
                'aMaterno' => $params_array['aMaterno'],
                'rfc' => $params_array['rfc'],
                'correo' => $params_array['correo'],
                'credito' => $params_array['credito'],
                //'idStatus' => $params_array['nombre'],
                'idTipo' => $params_array['idTipo']
            ]);
            //consultamos el cliente ya actualizado
            $newCliente = Cliente::where('idCliente',$idCliente)->get();

            //obtenemos direccion ip
            $ip = $_SERVER['REMOTE_ADDR'];
            /******* agregar codigo que verifica que se modifico */
            foreach($antCliente[0]['attributes'] as $clave => $valor){
                foreach($newCliente[0]['attributes'] as $clave2 => $valor2){

                    if($clave == $clave2 && $valor != $valor2){

                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario = $params_array['sub'];
                        $monitoreo -> accion = "Modificacion de ".$clave." anterior: ".$valor." nuevo: ".$valor2." del cliente";
                        $monitoreo -> folioNuevo = $idCliente;
                        $monitoreo -> pc = $ip;
                        $monitoreo ->save();
                    }
                }

            }
            /******* */
            //insertamos movimiento en monitoreo
            $monitoreo = new Monitoreo();
            $monitoreo -> idUsuario =  $params_array['sub'];
            $monitoreo -> accion =  "Modificacion de cliente";
            $monitoreo -> folioNuevo =  $idCliente;
            $monitoreo -> pc =  $ip;
            $monitoreo ->save();

             $data = array(
                'code'         =>  200,
                'status'       =>  'success',
                'cliente'    =>  $params_array
            );

        }else{
            $data = array(
                'code'         =>  200,
                'status'       =>  'error',
                'message'      =>  'Error al procesar'
            );
        }
        return response()->json($data,$data['code']);
    }

    public function updateCdireccion($idCliente, Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){//verificamos que no este vacio

            //eliminamos los registros que tenga el cliente
            Cdireccion::where('idCliente',$idCliente)->delete();
            
            //realimos la alta de las direcciones
            foreach($params_array AS $param => $paramdata){
                $cdireccion = new Cdireccion();//creamos el modelo
                $cdireccion-> idCliente = $idCliente;//asignamos el id desde el parametro que recibimos
                $cdireccion-> pais = $paramdata['pais'];//asginamos segun el recorrido
                $cdireccion-> estado = $paramdata['estado'];
                $cdireccion-> ciudad = $paramdata['ciudad'];
                $cdireccion-> colonia = $paramdata['colonia'];
                $cdireccion-> calle = $paramdata['calle'];
                $cdireccion-> entreCalles = $paramdata['entreCalles'];
                $cdireccion-> numExt = $paramdata['numExt'];
                $cdireccion-> numInt = $paramdata['numInt'];
                $cdireccion-> cp = $paramdata['cp'];
                $cdireccion-> referencia = $paramdata['referencia'];
                $cdireccion-> telefono = $paramdata['telefono'];
                $cdireccion-> idZona = $paramdata['idZona'];
                
                $cdireccion->save();//guardamos el modelo
                //Si todo es correcto mandamos el ultimo producto insertado
                }
                
            //insertamos movimiento
            $monitoreo = new Monitoreo();
            $monitoreo -> idUsuario = $params_array['sub'];
            $monitoreo -> accion = "Modificacion de direcion del cliente";
            $monitoreo -> folioNuevo = $idCliente;
            $monitoreo -> pc = $ip;
            $monitoreo ->save();

            $data =  array(
                'status'            => 'success',
                'code'              =>  200,
                'message'           =>  'Eliminacion e insercion correcta!',
                'cdireccion'   =>  $params_array
            );
            
        }else{
            //Si el array esta vacio o mal echo mandamos mensaje de error
            $data =  array(
                'status'        => 'error',
                'code'          =>  404,
                'message'       =>  'Los datos enviados no son correctos'
            );
        }
        return response()->json($data, $data['code']);
    }

    /**
     * Busca en la tabla clientes por su nombre concatenado
     */
    public function searchNombreCliente($nombreCliente){
        $clientes = DB::table('cliente')
        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
        ->select('cliente.*','tipocliente.Nombre as nombreTipoC')
        ->where(DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno)"),'Like','%'.$nombreCliente.'%')
        ->orderBy('cliente.idCliente')
        ->paginate(10);
        return response()->json([
            'code'      =>  200,
            'status'    =>  'success',
            'clientes'  =>  $clientes
        ]);
    }

}