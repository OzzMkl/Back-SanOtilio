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
                        
                        //Evaluamos si se trata de una cantidad nevgativa
                        if ($paramdata['cantidad'] < 0) {
                            $cantitdad = $paramdata['cantidad'] * -1;
                            $neg = true;
                        } else {
                            $cantitdad = $paramdata['cantidad'];
                        }

                        //calculamos medida menor
                        $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$cantitdad);
    
                        //Consultamos la existencia antes de actualizar
                        $Producto = Producto::find($paramdata['idProducto']);
                        $stockAnterior = $Producto -> existenciaG;
    
                        //Actualizamos existenciaG
                        if ($neg = true) {
                            $Producto -> existenciaG = $Producto -> existenciaG - $medidaMenor;
                        }else{
                            $Producto -> existenciaG = $Producto -> existenciaG + $medidaMenor;
                        }
                        
    
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

    public function updateIngreso(Request $request){
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
                try {
                    //Si no falla se regristra el ingreso
                    DB::beginTransaction();
                    DB::enableQueryLog();

                    //Consulta de ingreso a modificar
                    $anIngreso = ingresos::find($params_array['ingreso']['idIngreso']);

                    //Actualizamos
                    $ingreso = ingresos::where('idIngreso',$params_array['ingreso']['idIngreso'])->update([
                        'idStatus' => 55,
                        'observaciones' => $params_array['ingreso']['observaciones']
                    ]);

                    //Consultamos el ingreso que se actualizó
                    $ingreso = ingreso::find($params_array['ingreso']['idIngreso']);

                    //Obtenemos direción IP
                    $ip = $_SERVER['REMOTE_ADDR'];

                    //Recorremos el ingreso para ver que atributo cambio y asi guardar la modificacion
                    foreach($anIngreso->getAttributes() as $clave => $valor){
                        //foreach($ingreso[0]['attributes'] as $clave2 => $valor2){
                           //verificamos que la clave sea igua ejem: claveEx == claveEx
                           // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                           if(array_key_exists($clave,$traspaso->getAttributes()) && $valor != $ingreso->clave){
                               //insertamos el movimiento realizado
                               $monitoreo = new Monitoreo();
                               $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                               $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$ingreso->$clave." del ingreso";
                               $monitoreo -> folioNuevo =  $params_array['ignreso']['idIngreso'];
                               $monitoreo -> pc =  $ip;
                               $monitoreo ->save();
                           }
                        //}
                    }

                    //Inserción en monitoreo de lo que se hizo
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario = $params_array['identity']['sub'];
                    $monitoreo -> accion =  "Modificacion de ingreso";
                    $monitoreo -> folioNuevo =  $params_array['ingreso']['idIngreso'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    //Actualizamos productos
                    $producto_traspaso = $this->updateProductosIngreso($params_array);

                    DB::commit();
                    $data = array(
                        'code' => 200,
                        'status' => 'success',
                        'message' => 'Ingreso actualizado correctamente',
                        'ingreso' => $ingreso,
                        'dataProductos' => $dataProductos
                    );
                }catch (\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   => $e->getMessage(),
                        'error'     => $e
                    );
                }
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

    public function updateProductosIngreso($params_array){
        //Asignamos lista de nuevos productos
        $productosIngreso = $params_array['lista_producto_ingreso'];
        //Obtener idIngreso a actualizar
        $idIngreso = $params_array['ingreso']['idIngreso'];
        

        if(count($productosTraspaso) >= 1 && !empty($productosTraspaso)){
            try{

                //Creamos instancia para poder ocupar las funciones
                $clsMedMen = new clsProducto();
                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];
                //Obtener productos anteriores del ingreso
                $productosAnt = Productos_ingresos::where('idIngreso','=',$idIngreso)->get();
                //Eliminar productos del ingreso
                Productos_ingresos::where('idIngreso',$idIngreso)->delete();
                //Restar o agregar existencia antes de una modificacion
                foreach($productosAnt as $param => $paramdata){
                    
                    //Obtener producto
                        $Producto = Producto::find($paramdata['idProducto']);
                    //Obtener su existencia antes de actualizar
                        $stockAnterior = $Producto -> existenciaG;
                    //Actualizar existencia de acuerdo al tipo de ingreso
                    //Se suma si es una cantidad negativa y se resta si es una cantidad positiva
                        if($paramdata['cantidad']<0){
                            $Producto -> existenciaG = $Producto -> existenciaG + $paramdata['igualMedidaMenor'];
                            $accion = "Modificación de ingreso, ".$idIngreso.", se suma al inventario";
                        }elseif($paramdata['cantidad']>0){
                            $Producto -> existenciaG = $Producto -> existenciaG - $paramdata['igualMedidaMenor'];
                            $accion = "Modificación de ingreso, ".$idIngreso.", se resta al inventario";
                        }
                    //Guardar modelo
                        $Producto->save();
                    //Obtenemos la existencia del producto actualizado
                        $stockActualizado = Producto::find($paramdata['idProducto'])->existenciaG;
                    //insertamos el movimiento de existencia que se le realizo al producto
                        moviproduc::insertMoviproduc($paramdata,$accion,$idIngreso,$paramdata['igualMedidaMenor'],
                        $stockAnterior,$stockActualizado,$params_array['identity']['sub'],
                        $_SERVER['REMOTE_ADDR']);
                }

                //Insercion de nuevos productos de un ingreso
                foreach($productosTraspaso as $param => $paramdata){

                    //Evaluamos si se trata de una cantidad nevgativa
                    if ($paramdata['cantidad'] < 0) {
                        $cantitdad = $paramdata['cantidad'] * -1;
                        $neg = true;
                    } else {
                        $cantitdad = $paramdata['cantidad'];
                    }

                    //Obtener producto 
                        $Producto = Producto::find($paramdata['idProducto']);
                    //Calcular su medida menor
                        $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$cantitdad);
                    //Obtener su existencia antes de actualizar
                        $stockAnterior = $Producto -> existenciaG;
                    //Actualizamos existenciaG
                    if ($neg = true) {
                        $Producto -> existenciaG = $Producto -> existenciaG - $medidaMenor;
                        $accion = "Insercion despues de la modificacion del ingreso, ".$idIngreso.", se resta al inventario";
                    }else{
                        $Producto -> existenciaG = $Producto -> existenciaG + $medidaMenor;
                        $accion = "Insercion despues de la modificacion del ingreso, ".$idIngreso.", se suma al inventario";
                    }
                    //Guardar modelo
                        $Producto->save();
                    //Obtenemos la existencia del producto actualizado
                        $stockActualizado = Producto::find($paramdata['idProducto'])->existenciaG;
                    //insertamos el movimiento de existencia que se le realizo al producto
                        moviproduc::insertMoviproduc($paramdata,$accion,$idTraspaso,$medidaMenor,$stockAnterior,
                        $stockActualizado,$params_array['identity']['sub'],
                        $_SERVER['REMOTE_ADDR']);


                    //Agregamos los productos del ingreso
                    $productos_ingreso = new Productos_ingresos();
                        $productos_ingreso -> idIngreso = $idIngreso;
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
                    'message' => 'Productos modificados correctamente'
                );

                DB::commit();             
            } catch(\Exception $e){
                //Si falla realizamos rollback de la transaccion
                DB::rollback(); 
                //Propagamos el error ocurrido
                throw $e;
            }

        } else{
            $data =  array(
                'code'          =>  400,
                'status'        => 'error',
                'message'       =>  'Los datos enviados son incorrectos'
            );
        }
        return $data;
    }

    public function cancelIngreso($params_array){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            //Validacion de los datos del ingreso
            $validate = Validator::make($params_array['ingreso'],[
                
                'idEmpleado' =>  'required',
                'motivo' => 'required'
            ]);
 

            //Si falla
            if($validate->fails()){
                $data = array(
                    'code'      =>  '404',
                    'status'    =>  'error',
                    'message'   =>  'Fallo la validación de los datos de la cancelacion del ingreso',
                    'errors'    =>  $validate->errors()
                );
            }else{
                try {
                    //Si no falla se regristra el ingreso
                    DB::beginTransaction();
                    DB::enableQueryLog();

                    //Consulta de ingreso a modificar
                    $anIngreso = ingresos::find($params_array['ingreso']['idIngreso']);

                    //Actualizamos
                    $ingreso = ingresos::where('idIngreso',$params_array['ingreso']['idIngreso'])->update([
                        'idStatus' => 54,
                        'observaciones' => $params_array['ingreso']['observaciones']
                    ]);

                    //Consultamos el ingreso que se actualizó
                    $ingreso = ingreso::find($params_array['ingreso']['idIngreso']);

                    //Obtenemos direción IP
                    $ip = $_SERVER['REMOTE_ADDR'];

                    //Recorremos el ingreso para ver que atributo cambio y asi guardar la modificacion
                    foreach($anIngreso->getAttributes() as $clave => $valor){
                        //foreach($ingreso[0]['attributes'] as $clave2 => $valor2){
                           //verificamos que la clave sea igua ejem: claveEx == claveEx
                           // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                           if(array_key_exists($clave,$traspaso->getAttributes()) && $valor != $ingreso->clave){
                               //insertamos el movimiento realizado
                               $monitoreo = new Monitoreo();
                               $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                               $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$ingreso->$clave." del ingreso";
                               $monitoreo -> folioNuevo =  $params_array['ignreso']['idIngreso'];
                               $monitoreo -> pc =  $ip;
                               $monitoreo ->save();
                           }
                        //}
                    }

                    //Inserción en monitoreo de lo que se hizo
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario = $params_array['identity']['sub'];
                    $monitoreo -> accion =  "Modificacion de ingreso";
                    $monitoreo -> folioNuevo =  $params_array['ingreso']['idIngreso'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    //Actualizamos productos
                    $producto_traspaso = $this->updateProductosIngreso($params_array);

                    DB::commit();
                    $data = array(
                        'code' => 200,
                        'status' => 'success',
                        'message' => 'Ingreso actualizado correctamente',
                        'ingreso' => $ingreso,
                        'dataProductos' => $dataProductos
                    );
                }catch (\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   => $e->getMessage(),
                        'error'     => $e
                    );
                }
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
