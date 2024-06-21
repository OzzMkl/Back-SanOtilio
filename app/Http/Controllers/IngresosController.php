<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\models\Ingresos;
use App\models\Productos_ingresos;
use App\models\moviproduc;
use App\models\Monitoreo;
use App\models\Sucursal;
use App\models\Empresa;
use TCPDF;
use App\Clases\clsProducto;
use App\Producto;
use Carbon\Carbon;

class IngresosController extends Controller
{
    //

    public function index(){
        $ingresos = DB::table('ingresos')
        ->join('empleado','empleado.idEmpleado','=','ingreso.idEmpleado')
        ->join('statuss','empleado.idStatus','=','ingreso.idStatus')
        ->select('ingreso.*','statuss.nombre as nombreStatus',
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(ingreso.created_at, "%d/%m/%Y") as fecha_format')
                )
        ->get();
        return response()->json([
           'code'         =>  200,
           'status'       => 'success',
           'ingresos'   => $ingresos
       ]);
    }

    public function registerIngreso(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            //Validacion de los datos del ingreso
            $validate = Validator::make($params_array['ingreso'],[
                'idEmpleado' =>  'required'
            ]);

            //Si falla
            if($validate->fails()){
                $data = array(
                    'code'      =>  '404',
                    'status'    =>  'error',
                    'message'   =>  'Fallo la validación de los datos del ingreso',
                    'errors'    =>  $validate->errors()
                );
            }else{
                //Si no falla se regristra el ingreso
                DB::beginTransaction();

                //Insercion de los datos del ingreso nuevo
                $ingresoNuevo->idEmpleado = $params_array['identity']['sub'];;
                $ingresoNuevo->idStatus = 52;
                
                if(isset($params_array['ingreso']['observaciones'])){
                    $ingresoNuevo->observaciones = $params_array['ingreso']['observaciones'];
                }
                $ingresoNuevo->save(); 

                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //Obtenemos el ultimo ingreso registrado
                $ingreso = ingresos::latest('idIngreso')->value('idIngreso');

                //Monitoreo
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                $monitoreo -> accion =  "Alta de ingreso directo";
                $monitoreo -> folioNuevo =  $ingreso;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                //Insersion de productos
                $dataProductos = $this->registerProductosIngreso($ingreso,$params_array['lista_producto_traspaso'],$params_array['identity']['sub']);

                DB::commit();
                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Ingreso registrado correctamente',
                    'ingreso' => $ingreso,
                    'dataProductos' => $dataProductos
                );
            }
        }else{
            $data= array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);
    }

    public function registerProductosIngreso($ingreso,$productosIngreso,$idEmpleado){
        if(count($productosIngreso) >= 1 && !empty($productosIngreso)){
            try{
                    DB::beginTransaction();

                    //Creamos instancia para poder ocupar las funciones
                    $clsMedMen = new clsProd|ucto();
                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];

                    //Ingresar la cantidad en positivo o negativo como el kyubi
                    foreach($productosIngreso as $param => $paramdata){
                        //calculamos medida menor
                        $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$paramdata['cantidad']);
    
                        //Consultamos la existencia antes de actualizar
                        $Producto = Producto::find($paramdata['idProducto']);
                        $stockAnterior = $Producto -> existenciaG;
    
                        //Actualizamos existenciaG
                        $Producto -> existenciaG = $Producto -> existenciaG + $medidaMenor;
    
                        //Consultamos la existencia despues de actualizar
                        $stockActualizado = $Producto->existenciaG;
    
                        //insertamos el movimiento de existencia que se le realizo al producto
                        moviproduc::insertMoviproduc($paramdata,$accion = "Alta de ingreso directo",
                                                    $ingreso,$medidaMenor,$stockAnterior,$stockActualizado,$idEmpleado,
                                                    $_SERVER['REMOTE_ADDR']);

                        $productos_ingreso = new Productos_ingresos();
                        $productos_ingreso -> idIngreso = $ingreso;
                        $productos_ingreso -> idProducto = $paramdata['idProducto'];
                        $productos_ingreso -> descripcion = $Producto -> descripcion;
                        $productos_ingreso -> claveEx = $Producto -> claveEx;
                        $productos_ingreso -> idProdMedida = $paramdata['idProdMedida'];
                        $productos_ingreso -> cantidad = $paramdata['cantidad'];
                        $productos_ingreso -> igualMedidaMenor = $medidaMenor;
                        $productos_ingreso -> save();
                        
                    }

                    $data = array(
                        'code' => 200,
                        'status' => 'success',
                        'message' => 'Productos agregados correctamente'
                    );

                }catch(\Exception $e){
                //Si falla realizamos rollback de la transaccion
                DB::rollback();
                //Propagamos el error ocurrido
                throw $e;
            }
        }else{
            $data =  array(
                'code'          =>  400,
                'status'        => 'error',
                'message'       =>  'Los datos enviados son incorrectos'
            );
        }
        return $data;
    }

    public function modificarIngreso(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            //Validacion de los datos del ingreso
            $validate = Validator::make($params_array['ingreso'],[
                
                'idEmpleado' =>  'required'
            ]);

            //Si falla
            if($validate->fails()){
                $data = array(
                    'code'      =>  '404',
                    'status'    =>  'error',
                    'message'   =>  'Fallo la validación de los datos de la actualización del ingreso',
                    'errors'    =>  $validate->errors()
                );
            }else{
                //Si no falla se regristra el ingreso
                DB::beginTransaction();
                    

                DB::commit();
                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Ingreso actualizado correctamente',
                    'ingreso' => $ingreso,
                    'dataProductos' => $dataProductos
                );
            }
        }else{
            $data= array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);


    }









}
