<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\Proveedores;
use App\Contacto;
use App\ncp;
use App\models\Monitoreo;

class ProveedoresController extends Controller
{
    public function register(Request $request){//registrar proveedor

        //Recoger datos
        $json = $request->input('json', null);
        $params = json_decode($json); //objeto
        $params_array = json_decode($json, true);//array

        if(!empty($params_array) && !empty($params_array)){ //comparamos si nos envian una cadena vacia

            //limpiardatos
            $params_array = array_map('trim', $params_array);

            //Validamos los datos recibidos 
            $validate = Validator::make($params_array, [
                'rfc'           =>  'required',
                'nombre'        =>  'required',
                'pais'          =>  'required',
                'estado'        =>  'required',
                'ciudad'        =>  'required',
                'cpostal'       =>  'required',
                'colonia'       =>  'required',
                'calle'         =>  'required',
                'numero'        =>  'required',
                'telefono'      =>  'required',
                'creditoDias'   =>  'required',
                'ncuenta'       =>  'required',
                'idBanco'       =>  'required',
                'titular'       =>  'required',
                'clabe'         =>  'required'
            ]);

            if($validate->fails()){//si no estan mandamos json de error
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message'   =>  'El proveedor no se ha creado',
                    'errors'    =>  $validate->errors()
                );
            }else{

                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //crear proveedor y asignamos valores
                $proveedor = new Proveedores();
                $proveedor -> rfc = $params_array['rfc'];
                $proveedor -> nombre = $params_array['nombre'];
                $proveedor -> pais = $params_array['pais'];
                $proveedor -> estado = $params_array['estado'];
                $proveedor -> ciudad = $params_array['ciudad'];
                $proveedor -> cpostal = $params_array['cpostal'];
                $proveedor -> colonia = $params_array['colonia'];
                $proveedor -> calle = $params_array['calle'];
                $proveedor -> numero = $params_array['numero'];
                $proveedor -> telefono = $params_array['telefono'];
                $proveedor -> creditoDias = $params_array['creditoDias'];
                $proveedor -> creditoCantidad = $params_array['creditoCantidad'];
                $proveedor -> idStatus = $params_array['idStatus'];

                //guardamos el usuario
                $proveedor->save();

                //consultamos el proveedor ingresado
                $idProv = DB::table('Proveedores')->where('idStatus',29)->orderBy('idProveedor','desc')->first();

                //insertamos su Numero de cuentra del proveedor
                $ncp = new ncp();//creamos el objeto y asignamos
                $ncp -> idProveedor =$idProv->idProveedor;
                $ncp -> ncuenta = $params_array['ncuenta'];
                $ncp -> idBanco = $params_array['idBanco'];
                $ncp -> titular = $params_array['titular'];
                $ncp -> clabe  = $params_array['clabe'];
                $ncp -> save();//guardamos

                //insertamos su contacto
                $contacto = new Contacto();
                $contacto -> idProveedor = $idProv->idProveedor;
                $contacto -> nombre = $params_array['nombreCon'];
                $contacto -> email  = $params_array['emailCon'];
                $contacto -> telefono = $params_array['telefonoCon'];
                $contacto -> puesto = $params_array['puestoCon'];
                $contacto->save();

                //Insertamos movimiento en monitoreo
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario = $params_array['sub'] ;
                $monitoreo -> accion =  "Alta de proveedor";
                $monitoreo -> folioNuevo =  $idProv->idProveedor;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                $data = array(//una vez guardado mandamos mensaje de OK
                    'status'    =>  'success',
                    'code'      =>  '200',
                    'message'   =>  'El proveedor se ha creado correctamente',
                    'proveedor' =>  $proveedor,
                    'ncp' =>  $ncp,
                    'contacto' =>  $contacto

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
    /**
     * Listamos los proveedores habilitados con informacion de su contacto
     * datos paginados
     */
    public function index(){//FUNCION QUE DEVULEVE LOS PROVEEDORES ACTIVOS CON SU CONTACTO
        $proveedores = DB::table('Proveedores')
        ->join('contactos', 'proveedores.idProveedor', '=', 'contactos.idProveedor')
        ->select('proveedores.*', 
                 DB::raw('MAX(contactos.nombre) as nombreCon'),
                 DB::raw('MAX(contactos.telefono) as telefonoCon'),
                 DB::raw('MAX(contactos.email) as emailCon') )
        ->where('idStatus',29)
        ->groupBy('proveedores.idProveedor')
        ->paginate(10);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'proveedores'   =>  $proveedores
        ]);
    }

    /**
     * Esta funcion se utiliza para listar proveedores dentro de un select en el front
     */
    public function ObtenerLista(){
        $provedores = DB::table('proveedores')->where('idStatus','=','29')->get();
        return response()->json([
            'code'  => 200,
            'status'    => 'success',
            'provedores' => $provedores
        ]);
    }

    /**
     * Lista los proveedores Deshabilitados con datos de su contacto
     * datps paginados
     */
    public function proveedoresDes(){
        $proveedores = DB::table('Proveedores')
        ->join('contactos', 'proveedores.idProveedor', '=', 'contactos.idProveedor')
        ->select('proveedores.*', 
                 DB::raw('MAX(contactos.nombre) as nombreCon'),
                 DB::raw('MAX(contactos.telefono) as telefonoCon'),
                 DB::raw('MAX(contactos.email) as emailCon') )
        ->where('idStatus',30)
        ->groupBy('proveedores.idProveedor')
        ->paginate(10);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'proveedores'   =>  $proveedores
        ]);
    }

    public function show($idProveedor){
       config()->set('database.connections.mysql.strict', false);//se agrega este codigo para deshabilitar el forzado de mysql
        //FUNCION QUE DEVULEVE LOS PROVEEDOr DE ACUERDO A SU ID
        $proveedores = DB::table('Proveedores')
        ->join('contactos', 'proveedores.idProveedor', '=', 'contactos.idProveedor')
        ->join('ncp', 'proveedores.idProveedor', '=', 'ncp.idProveedor')
        ->join('bancos', 'ncp.idBanco','=', 'bancos.idBanco')
        ->select('proveedores.*',
                    'contactos.nombre as nombreCon','contactos.telefono as telefonoCon','contactos.email as emailCon','contactos.puesto as puestoCon',
                    'ncp.ncuenta as ncuenta', 'bancos.banco as idBanco', 'ncp.titular as titular', 'ncp.clabe as clabe')
        ->where('Proveedores.idProveedor',$idProveedor)
        ->groupBy('proveedores.idProveedor')
        ->get();
        if(is_object($proveedores)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'proveedores'   =>  $proveedores
            ];
        }else{
            $data = [
                'code'          => 400,
                'status'        => 'error',
                'message'       => 'El proveedor no existe'
            ];
        }
        return response()->json($data, $data['code']);
    }

    public function provContactos($idProveedor){
        $contactos = DB::table('Contactos')
        ->where('idProveedor',$idProveedor)
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'contactos'   =>  $contactos
        ]);
    }

    public function getNCP($idProveedor){
        $ncp = DB::table('ncp')
        ->select('ncp.*','bancos.banco')
        ->join('bancos', 'ncp.idBanco','=', 'bancos.idBanco')
        ->where('idProveedor',$idProveedor)
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'ncp'   =>  $ncp
        ]);
    }

    /**
     * Actualiza unicamente el status del proveedor
     * de HABILITADO -> DESHABILITADO  y viceversa
     */
    public function updatestatus($idProveedor, Request $request){
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);
        try{
            DB::beginTransaction();

            //Traemos el status del proveedor a actualizar
            $statusProv = Proveedores::find($idProveedor)->idStatus;
            //obtenemos direccion ip
            $ip = $_SERVER['REMOTE_ADDR'];
            
            switch ($statusProv) {
                case 29:
                        //Si esta habilitado lo deshabilitamos
                        $proveedor = Proveedores::where('idProveedor', $idProveedor)
                                                ->update([
                                                    'idStatus' => 30
                                                        ]);

                        //insertamos el movimiento que se hizo
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario = $params_array['sub'] ;
                        $monitoreo -> accion =  "Actualizacion de status a deshabilitado al proveedor";
                        $monitoreo -> folioNuevo =  $idProveedor;
                        $monitoreo -> pc =  $ip;
                        $monitoreo ->save();

                        //generamos respuesta del movimiento que se hizo
                        $data = array(
                            'code'      => 200,
                            'status'    => 'success',
                            'message'   =>  'Proveedor con id: '.$idProveedor.' actualizado a idStatus: 30'
                        );
                    break;
                case 30:
                        //Si esta deshabilitado lo habilitamos
                        $proveedor = Proveedores::where('idProveedor', $idProveedor)
                                                ->update([
                                                    'idStatus' => 29
                                                        ]);

                        //insertamos el movimiento que se hizo
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario = $params_array['sub'] ;
                        $monitoreo -> accion =  "Actualizacion de status a habilitado al proveedor";
                        $monitoreo -> folioNuevo =  $idProveedor;
                        $monitoreo -> pc =  $ip;
                        $monitoreo ->save();

                        //generamos respuesta del movimiento que se hizo
                        $data = array(
                            'code'      => 200,
                            'status'    => 'success',
                            'message'   =>  'Proveedor con id: '.$idProveedor.' actualizado a idStatus: 29'
                        );
                    break;
                default:
                        //Si recibimos otra cosa generamos mensaje de error
                        $data = array(
                            'code'      => 400,
                            'status'    => 'error',
                            'message'   =>  'Opcion no valida'
                        );
                    break;
            }
            DB::commit();
        } catch(\Exception $e){
            DB::rollBack();
                $data = array(
                    'code'      => 400,
                    'status'    => 'Error',
                    'message'   =>  $e->getMessage(),
                    'error' => $e
                );
        }
        return response()->json($data, $data['code']);
    }

    /**
     * Busca a los proveedores por su NOMBRE
     * Solo busca a los proveedores HABILITADOS
     */
    public function searchNombreProveedor($nombreProveedor){
        $proveedores = DB::table('Proveedores')
        ->join('contactos', 'proveedores.idProveedor', '=', 'contactos.idProveedor')
        ->select('proveedores.*', 
                 DB::raw('MAX(contactos.nombre) as nombreCon'),
                 DB::raw('MAX(contactos.telefono) as telefonoCon'),
                 DB::raw('MAX(contactos.email) as emailCon') )
        ->where([
            ['Proveedores.idStatus','=','29'],
            ['Proveedores.nombre','like','%'.$nombreProveedor.'%']
                ])
        ->groupBy('proveedores.idProveedor')
        ->paginate(1);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'proveedores'   =>  $proveedores
        ]);
    }

    /**
     * Busca a los proveedores por su RFC
     * Solo busca a los proveedores HABILITADOS
     */
    public function searchRFCProveedor($rfc){
        $proveedores = DB::table('Proveedores')
        ->join('contactos', 'proveedores.idProveedor', '=', 'contactos.idProveedor')
        ->select('proveedores.*', 
                 DB::raw('MAX(contactos.nombre) as nombreCon'),
                 DB::raw('MAX(contactos.telefono) as telefonoCon'),
                 DB::raw('MAX(contactos.email) as emailCon') )
        ->where([
            ['Proveedores.idStatus','=','29'],
            ['Proveedores.rfc','like','%'.$rfc.'%']
                ])
        ->groupBy('proveedores.idProveedor')
        ->paginate(1);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'proveedores'   =>  $proveedores
        ]);
    }

    /**
     * Busca a los proveedores por su NOMBRE
     * Solo busca a los proveedores INHABILITADOS
     */
    public function searchNombreProveedorI($nombreProveedor){
        $proveedores = DB::table('Proveedores')
        ->join('contactos', 'proveedores.idProveedor', '=', 'contactos.idProveedor')
        ->select('proveedores.*', 
                 DB::raw('MAX(contactos.nombre) as nombreCon'),
                 DB::raw('MAX(contactos.telefono) as telefonoCon'),
                 DB::raw('MAX(contactos.email) as emailCon') )
        ->where([
            ['Proveedores.idStatus','=','30'],
            ['Proveedores.nombre','like','%'.$nombreProveedor.'%']
                ])
        ->groupBy('proveedores.idProveedor')
        ->paginate(1);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'proveedores'   =>  $proveedores
        ]);
    }

    /**
     * Busca a los proveedores por su RFC
     * Solo busca a los proveedores INHABILITADOS
     */
    public function searchRFCProveedorI($rfc){
        $proveedores = DB::table('Proveedores')
        ->join('contactos', 'proveedores.idProveedor', '=', 'contactos.idProveedor')
        ->select('proveedores.*', 
                 DB::raw('MAX(contactos.nombre) as nombreCon'),
                 DB::raw('MAX(contactos.telefono) as telefonoCon'),
                 DB::raw('MAX(contactos.email) as emailCon') )
        ->where([
            ['Proveedores.idStatus','=','30'],
            ['Proveedores.rfc','like','%'.$rfc.'%']
                ])
        ->groupBy('proveedores.idProveedor')
        ->paginate(1);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'proveedores'   =>  $proveedores
        ]);
    }
}