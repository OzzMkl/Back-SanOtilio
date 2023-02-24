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

                //guardamos el usuario
                $proveedor->save();

                $data = array(//una vez guardado mandamos mensaje de OK
                    'status'    =>  'success',
                    'code'      =>  '200',
                    'message'   =>  'El proveedor se ha creado correctamente',
                    'proveedor' =>  $proveedor
                );
                if($data['status']== 'success'){//Comprobamos si esta OK y asignamos los datos de NCP
                    //Consultamos al ultimo proveedor insertado y tomamos su id para asignarlo al ncp
                    $proveedores = DB::table('Proveedores')->where('idStatus',1)->orderBy('idProveedor','desc')->first();
                    $ncp = new ncp();//creamos el objeto y asignamos
                    $ncp -> idProveedor =$proveedor->idProveedor;
                    $ncp -> ncuenta = $params_array['ncuenta'];
                    $ncp -> idBanco = $params_array['idBanco'];
                    $ncp -> titular = $params_array['titular'];
                    $ncp -> clabe  = $params_array['clabe'];
                    $ncp -> save();//guardamos
                    $data = array(//mandamos mensaje de OK
                        'status'    =>  'success',
                        'code'      =>  '200',
                        'message'   =>  'El proveedor y su NCP se ha creado correctamente'
                    );
                    //verificamos que este OK y vemos si vienen con datos de contacto si no trae rellenamos por default en XXXX
                    if($data['status']== 'success' && $params_array['nombreCon']=='' && $params_array['emailCon']=='' && $params_array['telefonoCon']=='' && $params_array['puestoCon']=='' ){
                        $proveedores = DB::table('Proveedores')->where('idStatus',1)->orderBy('idProveedor','des')->first();
                        $contacto = new Contacto();
                        $contacto -> idProveedor = $proveedores->idProveedor;
                        $contacto -> nombre = 'XXXXX';
                        $contacto -> email  = 'XXXXX';
                        $contacto -> telefono = 'XXXXX';
                        $contacto -> puesto = 'XXXXX';
    
                        $contacto->save();
                        $data = array(
                            'status'    =>  'success',
                            'code'      =>  '200',
                            'message'   =>  'El proveedor y contacto se ha creado correctamente'
                        );
                    }else{//SI SI TRAE DATOS ASIGNAMOS LOS VALORES  REGISTRADOS
                        $proveedores = DB::table('Proveedores')->where('idStatus',1)->orderBy('idProveedor','des')->first();
                        $contacto = new Contacto();
                        $contacto -> idProveedor = $proveedores->idProveedor;
                        $contacto -> nombre = $params_array['nombreCon'];
                        $contacto -> email  = $params_array['emailCon'];
                        $contacto -> telefono = $params_array['telefonoCon'];
                        $contacto -> puesto = $params_array['puestoCon'];

                        $contacto->save();//GUARDAMOS Y TERMINAMOS
                        $data = array(
                            'status'    =>  'success',
                            'code'      =>  '200',
                            'message'   =>  'El proveedor y contacto se ha creado correctamente'
                        );

                    }
                }else{
                    $data = array(
                        'status'    =>  'error',
                        'code'      =>  '404',
                        'message'   =>  'El Numero de Cuenta del proveedor no se ha registrado correctamente'
                    ); 
                }

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
        ->paginate(1);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'proveedores'   =>  $proveedores
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

    public function updatestatus($idProveedor,Request $request){
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);
        if(!empty($params_array)){
        //quitamos valores que no queremos actualizar
             unset($params_array['idProveedor']);
             unset($params_array['rfc']);
             unset($params_array['nombre']);
             unset($params_array['pais']);
             unset($params_array['estado']);
             unset($params_array['ciudad']);
             unset($params_array['cpostal']);
             unset($params_array['colonia']);
             unset($params_array['calle']);
             unset($params_array['numero']);
             unset($params_array['telefono']);
             unset($params_array['creditoDias']);
             unset($params_array['creditoCantidad']);
             unset($params_array['created_at']);
             unset($params_array['nombreCon']);
             unset($params_array['emailCon']);
             unset($params_array['telefonoCon']);
             unset($params_array['puestoCon']);
             unset($params_array['ncuenta']);
             unset($params_array['idBanco']);
             unset($params_array['titular']);
             unset($params_array['clabe']);
             unset($params_array['updated_at']);

             //actualizamos
             $proveedor = Proveedores::where('idProveedor', $idProveedor)->update($params_array);
             
             $data = array(
                 'code'         =>  200,
                 'status'       =>  'success',
                 'proveedor'    =>  $params_array
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