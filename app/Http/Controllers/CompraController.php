<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\OrdenDeCompra;
use App\Productos_compra;
use App\Productos_medidas;
use App\Compras;
use App\Producto;
use App\lote;
use App\Pelote;
use App\models\moviproduc;
use App\models\Moviproducfacturables;
use App\models\Monitoreo;
use App\models\Productos_facturables;
use App\models\Empresa;
use TCPDF;


class CompraController extends Controller
{
    //
    public function index(){
        $compra = DB::table('compra')
        ->join('ordendecompra','ordendecompra.idOrden','=','compra.idOrden')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->join('empleado','empleado.idEmpleadoR','=','empleado.idEmpleado')

        ->select('compra.*','proveedores.nombre as nombreProveedor','desc')
        ->get();
        return response()->json([
           'code'         =>  200,
           'status'       => 'success',
           'compra'   => $compra
       ]);
    }

    /**
     * Registra la compra
     * Actualiza el statuss de la orden de compra
     */
    public function registerCompra(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            //eliminar espacios vacios
            $params_array = array_map('trim', $params_array);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'idProveedor'       =>'required',
                'idEmpleadoR'      => 'required',//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
                'folioProveedor'   => 'required|unique:compra'
            ]);
            if($validate->fails()){//si el json esta mal mandamos esto (falta algun dato)
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo! Folio de proveedor duplicado',
                    'errors'    => $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();
                    $Compra = new Compras();
                    $Compra->idProveedor = $params_array['idProveedor'];
                    $Compra->folioProveedor = $params_array['folioProveedor'];
                    $Compra->subtotal = $params_array['subtotal'];
                    $Compra->total = $params_array['total'];
                    $Compra->idEmpleadoR = $params_array['idEmpleadoR'];
                    $Compra->idStatus = 28;
                    $Compra->fechaRecibo = $params_array['fechaRecibo'];                    
                    if(isset($params_array['observaciones'])){
                        $Compra->observaciones = $params_array['observaciones'];
                    }
                    if(isset($params_array['idOrd'])){
                        $Compra->idOrd = $params_array['idOrd'];
                    }
                    $Compra->facturable = $params_array['facturable'];                    


                    $Compra->save();
                    
                    //Obtenemos de la ultima compra el idCompra, idOrden y IdEmpleadoRecibe
                    $Foliocompra = Compras::latest('idCompra')->first()->idCompra; 
                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];

                    //insertamos el movimiento que se hizo en general
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['idEmpleadoR'];
                    $monitoreo -> accion =  "Alta de compra";
                    $monitoreo -> folioAnterior = $params_array['idOrd'];
                    $monitoreo -> folioNuevo =  $Foliocompra;
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    //Actualizar statuss de una orden de compra
                    $Ordencompra = OrdenDeCompra::find($params_array['idOrd']);
                    $Ordencompra -> idStatus = 27;
                    $Ordencompra->save();
                    
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Compra creada pero sin productos',
                        'compra' => $Compra
                    );

                    DB::commit();

                } catch(\Exception $e){
                    DB::rollBack();
                    $data= array(
                        'code'    => 400,
                        'status'  => 'Error',
                        'message' => 'Fallo al crear la compra Rollback!',
                        'error'   => $e
                    );
                }
            }
        } else {
            $data= array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);
    }

    public function registerProductosCompra(Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){
               //consultamos la ultima compra para poder asignarla
               $Compra = Compras::latest('idCompra')->first();//la guardamos en compra
               //recorremos el array para asignar todos los productos
               foreach($params_array AS $param => $paramdata){

                    $Productos_compra = new Productos_compra();//creamos el modelo
                    $Productos_compra->idCompra = $Compra -> idCompra;//asignamos el ultimo idCompra para todos los productos
                    $Productos_compra-> idProducto = $paramdata['idProducto'];
                    $Productos_compra-> idProdMedida = $paramdata['idProdMedida'];
                    $Productos_compra-> cantidad = $paramdata['cantidad'];
                    $Productos_compra-> precio = $paramdata['precio'];
                    $Productos_compra-> subtotal = $paramdata['subtotal'];
                    if( $paramdata['idImpuesto'] == 0 || $paramdata['idImpuesto'] == null){
                        $Productos_compra-> idImpuesto = 3;
                    }else{
                        $Productos_compra-> idImpuesto = $paramdata['idImpuesto'];
                    }

                    $stockanterior = 0;
                    $idProductoC = $paramdata['idProducto'];
                    $idProdMedidaC = $paramdata['idProdMedida'];
                    $cantidadC = $paramdata['cantidad'];
                    $igualMedidaMenor = 0;
                    $lugar = 0; 

                    //Consulta para saber cuantas medidas tiene un producto
                    $count = Productos_medidas::where([
                        ['productos_medidas.idProducto','=',$paramdata['idProducto']],
                        ['productos_medidas.idStatus','=','31']
                      ])->count();
                    //Consulta para obtener la lista de productos_medidas de un producto
                    $listaPM = Productos_medidas::where([
                            ['productos_medidas.idProducto','=',$paramdata['idProducto']],
                            ['productos_medidas.idStatus','=','31']
                        ])->get();

                    if($count == 1){//Si tiene una sola medida agrega directo la existencia ( count == 1 )
                        $Productos_compra-> igualMedidaMenor = $cantidadC;
                    }else{//Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                        //Se hace un cilo que recorre listaPM
                        while($idProdMedidaC != $listaPM[$lugar]['attributes']['idProdMedida']){
                            //echo $listaPM[$lugar]['attributes']['idProdMedida'];
                            //echo $lugar;
                            $lugar++;
                        }
                        if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                            $Productos_compra-> igualMedidaMenor = $cantidadC;
                        }elseif($lugar == 0){//Medida mas alta, multiplicar desde el principio ( lugar == 0)
                            $igualMedidaMenor = $cantidadC;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar]['attributes']['unidad'];
                                $lugar++;
                                //echo $igualMedidaMenor;
                            }
                            $Productos_compra-> igualMedidaMenor = $igualMedidaMenor;
                        }elseif($lugar>0 && $lugar<$count-1){//Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                            $igualMedidaMenor = $cantidadC;
                            $count--;
                            //echo $count;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]['attributes']['unidad'];
                                $lugar++;
                            }
                            $Productos_compra-> igualMedidaMenor = $igualMedidaMenor;
                        }else{

                        }
                    }


                    $Productos_compra->save();//guardamos el modelo

                    //Aqui no se guarda en monitoreo o movimiento de producto por que ese procedimiento se realiza en el metodo updateExistencia

                    //Si todo es correcto mandamos el ultimo producto insertado
                    $data =  array(
                        'status'        => 'success',
                        'code'          =>  200,
                        'Productos_compra'       =>  $Productos_compra
                            );
               }
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

    public function registerLote(){
        //$Compra = Compras::latest('idCompra')->first();

        //idLote -> idCompra
        //idOrigen -> 3
        //codigo -> null

        

        // return response()->json([
        //     'code'         =>  200,
        //     'status'       => 'success',
        //     'Lote'   => $Compra
        // ]);
    }

    /**
     * Actualiza existencia de productos en productos.existenciaG
     * Inserta en moviproducto el stock anterior y el actualizado
     * Inserta en monitoreo la accion
     */
    public function updateExistencia(Request $request){
        //recogemos los datos enviados por post en formato json
        $json = $request -> input('json',null);
        //decodifiamos el json
        $params_array = json_decode($json,true);

        //revisamos que no venga vacio
        if(!empty($params_array)){
            try{//comenzamos transaccion
                DB::beginTransaction();

                //Obtenemos de la ultima compra el idCompra, idOrden y IdEmpleadoRecibe
                $Foliocompra = Compras::latest('idCompra')->first()->idCompra; 
                $idUsuario = Compras::latest('idCompra')->first()->idEmpleadoR;
                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //Recorremos el array para asignar todos los productos
                //Actualizar Producto -> ExistenciaG
                foreach($params_array AS $param => $paramdata){
                
                    //antes de actualizar el producto obtenemos su existencia-
                    $stockanterior = Producto::find($paramdata['idProducto'])->existenciaG;
                    //Buscamos el producto a actualizar y actualizamos
                    $Producto = Producto::find($paramdata['idProducto']);
                    
                    /**
                     * CONVERSION A MEDIDA MENOR
                     * 
                     * lugar - count
                     *   [0] - 1
                     *   [1] - 2
                     *   [2] - 3
                     *   [3] - 4
                     *   [4] - 5
                     * 
                     * Variables para almacenar los datos recibidos
                     * Consulta para saber cuantas medidas tiene un producto
                     * Consulta para obtener la lista de productos_medidas de un producto
                     * Verificar si el producto tiene una sola medida
                     * Si tiene una sola medida agrega directo la existencia ( count == 1 )
                     * Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                     * Se hace un cilo que recorre listaPM
                     * Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                     * Medida mas alta, multiplicar desde el principio ( lugar == 0)
                     * Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                     *  
                     */
                    //Variables para almacenar los datos recibidos
                    $idProductoC = $paramdata['idProducto'];
                    $idProdMedidaC = $paramdata['idProdMedida'];
                    $cantidadC = $paramdata['cantidad'];
                    //Variables para el calculo
                    $igualMedidaMenor = 0;
                    $lugar = 0; 
                    //Consulta para saber cuantas medidas tiene un producto
                    $count = Productos_medidas::where([
                                                        ['productos_medidas.idProducto','=',$paramdata['idProducto']],
                                                        ['productos_medidas.idStatus','=','31']
                                                      ])->count();
                    //Consulta para obtener la lista de productos_medidas de un producto
                    $listaPM = Productos_medidas::where([
                                                            ['productos_medidas.idProducto','=',$paramdata['idProducto']],
                                                            ['productos_medidas.idStatus','=','31']
                                                        ])->get();
                    //var_dump($count);
                    //var_dump($listaPM);
                    //Verificar si el producto tiene una sola medida
                    if($count == 1){//Si tiene una sola medida agrega directo la existencia ( count == 1 )
                        $Producto -> existenciaG = $Producto -> existenciaG + $cantidadC;
                        $igualMedidaMenor = $cantidadC;
                    }else{//Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                        //Se hace un cilo que recorre listaPM
                        while($idProdMedidaC != $listaPM[$lugar]['attributes']['idProdMedida']){
                            //echo $listaPM[$lugar]['attributes']['idProdMedida'];
                            //echo $lugar;
                            $lugar++;
                        }
                        if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                            $Producto -> existenciaG = $Producto -> existenciaG + $cantidadC; 
                            $igualMedidaMenor = $cantidadC;
                        }elseif($lugar == 0){//Medida mas alta, multiplicar desde el principio ( lugar == 0)
                            $igualMedidaMenor = $cantidadC;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar]['attributes']['unidad'];
                                $lugar++;
                                //echo $igualMedidaMenor;
                            }
                            $Producto -> existenciaG = $Producto -> existenciaG + $igualMedidaMenor;
                        }elseif($lugar>0 && $lugar<$count-1){//Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                            $igualMedidaMenor = $cantidadC;
                            $count--;
                            //echo $count;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]['attributes']['unidad'];
                                $lugar++;
                            }
                            $Producto -> existenciaG = $Producto -> existenciaG + $igualMedidaMenor;
                        }else{

                        }
                    }
                    

                    

                    
                    $Producto->save();//guardamos el modelo

                    //obtenemos la existencia del producto actualizado
                    $stockactualizado = Producto::find($paramdata['idProducto'])->existenciaG;

                    //insertamos el movimiento de existencia del producto
                    $moviproduc = new moviproduc();
                    $moviproduc -> idProducto =  $paramdata['idProducto'];
                    $moviproduc -> claveEx =  $paramdata['claveexterna'];
                    $moviproduc -> accion =  "Alta de compra";
                    $moviproduc -> folioAccion =  $Foliocompra;
                    $moviproduc -> cantidad =  $igualMedidaMenor;
                    $moviproduc -> stockanterior =  $stockanterior;
                    $moviproduc -> stockactualizado =  $stockactualizado;
                    $moviproduc -> idUsuario =  $idUsuario;
                    $moviproduc -> pc =  $ip;
                    $moviproduc ->save();

                    //Si todo es correcto mandamos el ultimo producto insertado y el movimiento
                    $data =  array(
                        'status'        => 'success',
                        'code'          =>  200,
                        'Producto'      =>  $Producto,
                        'Movimiento'    =>  $moviproduc,
                        'count'         =>  $count,
                        'listaPM'       =>  $listaPM
                    );
                }
                DB::commit();
            } catch(\Exception $e){
                DB::rollBack();
                $data = array(
                    'code'      => 400,
                    'status'    => 'Error',
                    'message'   =>  'Fallo algo',
                    'messageError' => $e -> getMessage(),
                    'error' => $e
                );
            }

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

    public function updateExistenciaFacturable(Request $request){
        //recogemos los datos enviados por post en formato json
        $json = $request -> input('json',null);
        //decodifiamos el json
        $params_array = json_decode($json,true);

        //revisamos que no venga vacio
        if(!empty($params_array)){
            try{//comenzamos transaccion
                DB::beginTransaction();

                //Obtenemos de la ultima compra el idCompra, idOrden y IdEmpleadoRecibe
                $Foliocompra = Compras::latest('idCompra')->first()->idCompra; 
                $idUsuario = Compras::latest('idCompra')->first()->idEmpleadoR;
                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //Recorremos el array para asignar todos los productos
                //Actualizar Producto -> ExistenciaG
                foreach($params_array AS $param => $paramdata){
                    $Producto = Productos_facturables::find($paramdata['idProducto']);
                    if(!is_object($Producto)){
                        $Productos_facturables = new Productos_facturables();
                        $Productos_facturables -> idProducto =  $paramdata['idProducto'];
                        $Productos_facturables -> existenciaG = 0;
                        $Productos_facturables ->save();

                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $idUsuario;
                        $monitoreo -> accion =  "Alta de producto ".$paramdata['idProducto']." como facturable";
                        $monitoreo -> folioNuevo =  $Foliocompra;
                        $monitoreo -> pc =  $ip;
                        $monitoreo ->save();

                    }else{

                    }

                
                    //antes de actualizar el producto obtenemos su existencia-
                    $stockanterior = Productos_facturables::find($paramdata['idProducto'])->existenciaG;
                    //Buscamos el producto a actualizar y actualizamos
                    $Producto = Productos_facturables::find($paramdata['idProducto']);
                    
                    /**
                     * CONVERSION A MEDIDA MENOR
                     * 
                     * lugar - count
                     *   [0] - 1
                     *   [1] - 2
                     *   [2] - 3
                     *   [3] - 4
                     *   [4] - 5
                     * 
                     * Variables para almacenar los datos recibidos
                     * Consulta para saber cuantas medidas tiene un producto
                     * Consulta para obtener la lista de productos_medidas de un producto
                     * Verificar si el producto tiene una sola medida
                     * Si tiene una sola medida agrega directo la existencia ( count == 1 )
                     * Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                     * Se hace un cilo que recorre listaPM
                     * Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                     * Medida mas alta, multiplicar desde el principio ( lugar == 0)
                     * Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                     *  
                     */
                    //Variables para almacenar los datos recibidos
                    $idProductoC = $paramdata['idProducto'];
                    $idProdMedidaC = $paramdata['idProdMedida'];
                    $cantidadC = $paramdata['cantidad'];
                    //Variables para el calculo
                    $igualMedidaMenor = 0;
                    $lugar = 0; 
                    //Consulta para saber cuantas medidas tiene un producto
                    $count = Productos_medidas::where([
                                                        ['productos_medidas.idProducto','=',$paramdata['idProducto']],
                                                        ['productos_medidas.idStatus','=','31']
                                                      ])->count();
                    //Consulta para obtener la lista de productos_medidas de un producto
                    $listaPM = Productos_medidas::where([
                                                            ['productos_medidas.idProducto','=',$paramdata['idProducto']],
                                                            ['productos_medidas.idStatus','=','31']
                                                        ])->get();
                    //var_dump($count);
                    //var_dump($listaPM);
                    //Verificar si el producto tiene una sola medida
                    if($count == 1){//Si tiene una sola medida agrega directo la existencia ( count == 1 )
                        $Producto -> existenciaG = $Producto -> existenciaG + $cantidadC;
                        $igualMedidaMenor = $Producto -> existenciaG + $cantidadC;
                    }else{//Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                        //Se hace un cilo que recorre listaPM
                        while($idProdMedidaC != $listaPM[$lugar]['attributes']['idProdMedida']){
                            //echo $listaPM[$lugar]['attributes']['idProdMedida'];
                            //echo $lugar;
                            $lugar++;
                        }
                        if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                            $Producto -> existenciaG = $Producto -> existenciaG + $cantidadC;
                            $igualMedidaMenor = $Producto -> existenciaG + $cantidadC;
                        }elseif($lugar == 0){//Medida mas alta, multiplicar desde el principio ( lugar == 0)
                            $igualMedidaMenor = $cantidadC;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar]['attributes']['unidad'];
                                $lugar++;
                                //echo $igualMedidaMenor;
                            }
                            $Producto -> existenciaG = $Producto -> existenciaG + $igualMedidaMenor;
                        }elseif($lugar>0 && $lugar<$count-1){//Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                            $igualMedidaMenor = $cantidadC;
                            $count--;
                            //echo $count;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]['attributes']['unidad'];
                                $lugar++;
                            }
                            $Producto -> existenciaG = $Producto -> existenciaG + $igualMedidaMenor;
                        }else{

                        }
                    }
                    

                    

                    
                    $Producto->save();//guardamos el modelo

                    //obtenemos la existencia del producto actualizado
                    $stockactualizado = Productos_facturables::find($paramdata['idProducto'])->existenciaG;

                    //insertamos el movimiento de existencia del producto
                    $Moviproducfacturables = new Moviproducfacturables();
                    $Moviproducfacturables -> idProducto =  $paramdata['idProducto'];
                    $Moviproducfacturables -> claveEx =  $paramdata['claveEx'];
                    $Moviproducfacturables -> accion =  "Alta de compra";
                    $Moviproducfacturables -> folioAccion =  $Foliocompra;
                    $Moviproducfacturables -> cantidad =  $igualMedidaMenor;
                    $Moviproducfacturables -> stockanterior =  $stockanterior;
                    $Moviproducfacturables -> stockactualizado =  $stockactualizado;
                    $Moviproducfacturables -> idUsuario =  $idUsuario;
                    $Moviproducfacturables -> pc =  $ip;
                    $Moviproducfacturables ->save();

                    //Si todo es correcto mandamos el ultimo producto insertado y el movimiento
                    $data =  array(
                        'status'        => 'success',
                        'code'          =>  200,
                        'Producto'      =>  $Producto,
                        'Movimiento'    =>  $Moviproducfacturables,
                        'count'         =>  $count,
                        'listaPM'       =>  $listaPM
                    );
                }
                DB::commit();
            } catch(\Exception $e){
                DB::rollBack();
                $data = array(
                    'code'      => 400,
                    'status'    => 'Error',
                    'message'   =>  'Fallo algo',
                    'messageError' => $e -> getMessage(),
                    'error' => $e
                );
            }

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

    public function getLastCompra(){
        $Compra = Compras::latest('idCompra')->first();
        return response()->json([
            'code'         =>  200,
            'status'       => 'success',
            'compra'   => $Compra
        ]);

    }

    public function showMejorado($idCompra){
        $compra = DB::table('compra')
            ->join('proveedores','proveedores.idProveedor','=','compra.idProveedor')
            ->join('empleado','empleado.idEmpleado','=','compra.idEmpleadoR')
            ->select('compra.*','proveedores.idProveedor','proveedores.nombre','proveedores.rfc','proveedores.telefono', 
                        DB::raw("CONCAT(proveedores.pais,' ',proveedores.estado,' ',proveedores.ciudad,' ',proveedores.colonia,' ',proveedores.calle,' ',proveedores.numero) as provDireccion"),
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                        DB::raw('DATE_FORMAT(compra.fechaRecibo, "%d/%m/%Y") as fecha_format'))
            ->where('compra.idCompra','=',$idCompra)
            ->get();
        $productosCompra = DB::table('productos_compra')
            ->join('producto','producto.idProducto','=','productos_compra.idProducto')
            ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_compra.idProdMedida')
            ->join('impuesto','impuesto.idImpuesto','=','productos_compra.idImpuesto')
            ->select('productos_compra.*','producto.claveEx as claveexterna','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida','impuesto.nombre as nombreImpuesto','impuesto.valor as valorImpuesto')
            ->where('productos_compra.idCompra','=',$idCompra)
            ->get();         
        

        if(is_object($compra)){
            $data = [
                'code'         => 200,
                'status'       => 'success',
                'compra'       => $compra,
                'productos'    => $productosCompra
            ];
        }else{
            $data = [
                'code'          => 400,
                'status'        => 'error',
                'message'       => 'La compra no existe'
            ];
        }
        return response()->json($data, $data['code']);
    }

    /**
     * Lista de compras
     */
    public function listaComprasRecibidas(){
        $compra = DB::table('compra')
        ->join('proveedores','proveedores.idProveedor','=','compra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','compra.idEmpleadoR')
        ->select('compra.*','proveedores.nombre as nombreProveedor', 
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(compra.fechaRecibo, "%d/%m/%Y") as fecha_format'))
        ->where('compra.idStatus','=',28)
        ->orwhere('compra.idStatus','=',33)
        ->orderBy('compra.idCompra','desc')
        ->paginate(10);

        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'compra'   =>  $compra
        ]);
    }

    public function searchIdCompra($idCompra){
        $compra = DB::table('compra')
        ->join('proveedores','proveedores.idProveedor','=','compra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','compra.idEmpleadoR')
        ->select('compra.*','proveedores.nombre as nombreProveedor', 
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(compra.fechaRecibo, "%d/%m/%Y") as fecha_format'))
        ->where([
                    ['compra.idStatus','=','28'],
                    ['idCompra','like','%'.$idCompra.'%']
                ])
        ->paginate(10);

        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'compra'   =>  $compra
        ]);

    }

    public function searchNombreProveedor($nombreProveedor){
        $compra = DB::table('compra')
        ->join('proveedores','proveedores.idProveedor','=','compra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','compra.idEmpleadoR')
        ->select('compra.*','proveedores.nombre as nombreProveedor', 
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(compra.fechaRecibo, "%d/%m/%Y") as fecha_format'))
        ->where([
                    ['compra.idStatus','=','28'],
                    ['proveedores.nombre','like','%'.$nombreProveedor.'%']
                ])
        ->paginate(10);

        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'compra'   =>  $compra
        ]);

    } 

    public function searchFolioProveedor($folioProveedor){
        $compra = DB::table('compra')
        ->join('proveedores','proveedores.idProveedor','=','compra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','compra.idEmpleadoR')
        ->select('compra.*','proveedores.nombre as nombreProveedor', 
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(compra.fechaRecibo, "%d/%m/%Y") as fecha_format'))
        ->where([
                    ['compra.idStatus','=','28'],
                    ['compra.folioProveedor','like','%'.$folioProveedor.'%']
                ])
        ->paginate(10);

        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'compra'   =>  $compra
        ]);

    } 

    public function searchTotal($total){
        $compra = DB::table('compra')
        ->join('proveedores','proveedores.idProveedor','=','compra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','compra.idEmpleadoR')
        ->select('compra.*','proveedores.nombre as nombreProveedor', 
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(compra.fechaRecibo, "%d/%m/%Y") as fecha_format'))
        ->where([
                    ['compra.idStatus','=','28'],
                    ['compra.total','like','%'.$total.'%']
                ])
        ->paginate(10);

        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'compra'   =>  $compra
        ]);

    }

    public function updateCompra(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        if(!empty($params_array)){
            //eliminar espacios vacios
            //$params_array = array_map('trim', $params_array);
            //Validacion de datos
            $validate = Validator::make($params_array, [
                'idCompra'       => 'required',
                'idOrd'          => 'required',
                'idProveedor'    => 'required',
                'folioProveedor' => 'required',
                'subtotal'       => 'required',
                'total'          => 'required',
                'idEmpleadoR'    => 'required',
                'idStatus'       => 'required',
                'fechaRecibo'    => 'required',
                'facturable'     => 'required',
                'sub'            => 'required'
            ]);
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message_system'   =>  'Fallo la validacion de los datos de la compra',
                    //'message_validation' => $validate->getMessage(),
                    'errors'    =>  $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();
                    DB::enableQueryLog();

                    //Compraracion de datos para saber que cambios se realizaron
                    $antCompra = Compras::where('idCompra',$params_array['idCompra'])->get();

                    //actualizamos
                    $Compra = Compras::where('idCompra',$params_array['idCompra'])->update([
                        'idProveedor'    => $params_array['idProveedor'],
                        'folioProveedor' => $params_array['folioProveedor'],
                        'subtotal'       => $params_array['subtotal'],
                        'total'          => $params_array['total'],
                        //'idEmpleadoR'    => $params_array['idEmpleadoR'],
                        'idStatus'       => $params_array['idStatus'],
                        'fechaRecibo'    => $params_array['fechaRecibo'],
                        'observaciones'  => $params_array['observaciones'],
                        'facturable'     => $params_array['facturable']
                    ]);
                    
                    //consultamos la compra que se actualizo                                
                    $compra = Compras::where('idCompra',$params_array['idCompra'])->get();
                    
                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];
                    
                    //recorremos el producto para ver que atributo cambio y asi guardar la modificacion
                    foreach($antCompra[0]['attributes'] as $clave => $valor){
                        foreach($compra[0]['attributes'] as $clave2 => $valor2){
                           //verificamos que la clave sea igua ejem: claveEx == claveEx
                           // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                           if($clave == $clave2 && $valor !=  $valor2){
                               //insertamos el movimiento realizado
                               $monitoreo = new Monitoreo();
                               $monitoreo -> idUsuario =  $params_array['sub'];
                               $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$valor2." de la compra";
                               $monitoreo -> folioNuevo =  $params_array['idCompra'];
                               $monitoreo -> pc =  $ip;
                               $monitoreo ->save();
                           }
                        }
                    }


                    //insertamos el movimiento que se hizo
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario = $params_array['sub'] ;
                    $monitoreo -> accion =  "Modificacion de compra";
                    $monitoreo -> folioNuevo =  $params_array['idCompra'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Compra actualizada pero sin productos',
                        'compra' => $compra
                    );
                    
                    /****** */
                    DB::commit();
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

    public function updateProductosCompra ($idCompra,$idEmpleado,Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        //obtenemos direccion ip
        $ip = $_SERVER['REMOTE_ADDR'];
        if(!empty($params_array)){//verificamos que no este vacio
            try{
                DB::beginTransaction();
                //Consultamos lista de productos a actualizar
                $productosAnt = Productos_compra::where('idCompra',$idCompra)->get();
                //echo($productosAnt);
                //Restamos la cantidad en medida menor de la existencia general de los productos para deshacer la compra
                foreach($productosAnt AS $param => $paramdata ){
                    //Antes de actualizar el producto obtenemos su existenciaG, se realiza la operacion y se guarda
                    $Producto = Producto::find($paramdata['idProducto']);
                    $stockanterior = $Producto -> existenciaG;
                    $Producto -> existenciaG = $Producto -> existenciaG - $paramdata['igualMedidaMenor'];
                    $Producto->save();//guardamos el modelo
                    //Obtenemos la existencia del producto actualizado
                    $stockactualizado = Producto::find($paramdata['idProducto'])->existenciaG;

                    //insertamos el movimiento de existencia del producto
                    $moviproduc = new moviproduc();
                    $moviproduc -> idProducto =  $paramdata['idProducto'];
                    $moviproduc -> claveEx =  $Producto -> claveEx;
                    $moviproduc -> accion =  "Modificacion de compra, se descuenta del inventario";
                    $moviproduc -> folioAccion =  $idCompra;
                    $moviproduc -> cantidad =  $paramdata['igualMedidaMenor'];
                    $moviproduc -> stockanterior =  $stockanterior;
                    $moviproduc -> stockactualizado =  $stockactualizado;
                    $moviproduc -> idUsuario =  $idEmpleado;
                    $moviproduc -> pc =  $ip;
                    $moviproduc ->save();

                }
                //eliminamos los registros que tengan ese idCompra
                Productos_compra::where('idCompra',$idCompra)->delete();
                //Recorremos el array para registrar los productos de la compra
                foreach($params_array AS $param => $paramdata){

                    $Productos_compra = new Productos_compra();//creamos el modelo
                    $Productos_compra->idCompra = $idCompra;//asignamos el ultimo idCompra para todos los productos
                    $Productos_compra-> idProducto = $paramdata['idProducto'];
                    $Productos_compra-> idProdMedida = $paramdata['idProdMedida'];
                    $Productos_compra-> cantidad = $paramdata['cantidad'];
                    $Productos_compra-> precio = $paramdata['precio'];
                    $Productos_compra-> subtotal = $paramdata['subtotal'];
                    if( $paramdata['idImpuesto'] == 0 || $paramdata['idImpuesto'] == null){
                        $Productos_compra-> idImpuesto = 3;
                    }else{
                        $Productos_compra-> idImpuesto = $paramdata['idImpuesto'];
                    }

                    $stockanterior = 0;
                    $idProductoC = $paramdata['idProducto'];
                    $idProdMedidaC = $paramdata['idProdMedida'];
                    $cantidadC = $paramdata['cantidad'];
                    $igualMedidaMenor = 0;
                    $lugar = 0; 

                    //Consulta para saber cuantas medidas tiene un producto
                    $count = Productos_medidas::where([
                        ['productos_medidas.idProducto','=',$paramdata['idProducto']],
                        ['productos_medidas.idStatus','=','31']
                    ])->count();
                    //Consulta para obtener la lista de productos_medidas de un producto
                    $listaPM = Productos_medidas::where([
                            ['productos_medidas.idProducto','=',$paramdata['idProducto']],
                            ['productos_medidas.idStatus','=','31']
                        ])->get();

                    if($count == 1){//Si tiene una sola medida agrega directo la existencia ( count == 1 )
                        $Productos_compra-> igualMedidaMenor = $cantidadC;
                    }else{//Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                        //Se hace un cilo que recorre listaPM
                        while($idProdMedidaC != $listaPM[$lugar]['attributes']['idProdMedida']){
                            //echo $listaPM[$lugar]['attributes']['idProdMedida'];
                            //echo $lugar;
                            $lugar++;
                        }
                        if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                            $Productos_compra-> igualMedidaMenor = $cantidadC;
                        }elseif($lugar == 0){//Medida mas alta, multiplicar desde el principio ( lugar == 0)
                            $igualMedidaMenor = $cantidadC;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar]['attributes']['unidad'];
                                $lugar++;
                                //echo $igualMedidaMenor;
                            }
                            $Productos_compra-> igualMedidaMenor = $igualMedidaMenor;
                        }elseif($lugar>0 && $lugar<$count-1){//Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                            $igualMedidaMenor = $cantidadC;
                            $count--;
                            //echo $count;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]['attributes']['unidad'];
                                $lugar++;
                            }
                            $Productos_compra-> igualMedidaMenor = $igualMedidaMenor;
                        }else{

                        }
                    }
                    $Productos_compra->save();//guardamos el modelo
                }
                //Actualizamos la existencia general y hacemos el registro de su movimiento
                //Consultamos lista de productos a actualizar
                $productosNew = Productos_compra::where('idCompra',$idCompra)->get();
                //Sumamos la cantidad en medida menor de la existencia general de los productos
                foreach($productosNew AS $param => $paramdata ){
                    //Antes de actualizar el producto obtenemos su existenciaG, se realiza la operacion y se guarda
                    $Producto = Producto::find($paramdata['idProducto']);
                    $stockanterior = $Producto -> existenciaG;
                    $Producto -> existenciaG = $Producto -> existenciaG + $paramdata['igualMedidaMenor'];
                    $Producto->save();//guardamos el modelo
                    //Obtenemos la existencia del producto actualizado
                    $stockactualizado = Producto::find($paramdata['idProducto'])->existenciaG;

                    //insertamos el movimiento de existencia del producto
                    $moviproduc = new moviproduc();
                    $moviproduc -> idProducto =  $paramdata['idProducto'];
                    $moviproduc -> claveEx =  $Producto -> claveEx;
                    $moviproduc -> accion =  "Modificacion de compra, se guarda despues de la modificacion";
                    $moviproduc -> folioAccion =  $idCompra;
                    $moviproduc -> cantidad =  $paramdata['igualMedidaMenor'];
                    $moviproduc -> stockanterior =  $stockanterior;
                    $moviproduc -> stockactualizado =  $stockactualizado;
                    $moviproduc -> idUsuario =  $idEmpleado;
                    $moviproduc -> pc =  $ip;
                    $moviproduc ->save();
                }
                $data =  array(
                    'status'            => 'success',
                    'code'              =>  200,
                    'message'           =>  'Actualización correcta!'
                );
                DB::commit();
            }catch(\Exception $e){
                DB::rollBack();
                $data = array(
                    'code'      => 400,
                    'status'    => 'Error',
                    'message'   =>  'Fallo algo',
                    'messageError' => $e -> getMessage(),
                    'error' => $e
                );
            }
            
            
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

    public function checkUpdates($idCompra){
        $modificacion = DB::table('monitoreo')
            ->join('empleado','empleado.idEmpleado','=','monitoreo.idUsuario')
            ->select('monitoreo.*',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
            ->where([
                        ['monitoreo.accion','like','%compra%'],
                        ['monitoreo.folioNuevo','=',$idCompra]
                    ])
            ->get();
        
        if(is_object($modificacion)){
                $data = [
                    'code'         => 200,
                    'status'       => 'success',
                    'modificacion' => $modificacion
                    
                ];
        }else{
                $data = [
                    'code'          => 400,
                    'status'        => 'error',
                    'message'       => 'La compra no ha sido modificada'
                ];
        }
        return response()->json($data, $data['code']);
    }
    
    public function generatePDF($idCompra,$idEmpleado){
        $Empresa = Empresa::first();

        $compra = DB::table('compra')
        ->join('proveedores','proveedores.idProveedor','=','compra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','compra.idEmpleadoR')
        ->select('compra.*','proveedores.idProveedor','proveedores.nombre as nombreProveedor','proveedores.rfc','proveedores.telefono', 
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(compra.fechaRecibo, "%d/%m/%Y") as fecha_format'),
                    DB::raw('DATE_FORMAT(compra.created_at, "%d/%m/%Y") as created_format'))
        ->where('compra.idCompra','=',$idCompra)
        ->first();

        $productosCompra = DB::table('productos_compra')
        ->join('producto','producto.idProducto','=','productos_compra.idProducto')
        ->join('marca','marca.idMarca','=','producto.idMarca')
        ->join('departamentos','departamentos.idDep','=','producto.idDep')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_compra.idProdMedida')
        ->select('productos_compra.*','producto.claveEx as claveEx','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida',
                    'marca.nombre as marcaN','departamentos.nombre as departamentoN'
                )
        ->where([
                    ['productos_compra.idCompra','=',$idCompra]
                ])
        ->get();

        if(is_object($compra)){

            //obtenemos direccion ip
            $ip = $_SERVER['REMOTE_ADDR'];
            //insertamos el movimiento que se hizo en general
            $monitoreo = new Monitoreo();
            $monitoreo -> idUsuario =  $idEmpleado;
            $monitoreo -> accion =  "Impresión de PDF, compra";
            $monitoreo -> folioNuevo =  $compra->idOrd;
            $monitoreo -> pc =  $ip;
            $monitoreo ->save(); 

            //CREACION DEL PDF
            $pdf = new TCPDF('P', 'MM','A4','UTF-8');
            //ELIMINAMOS CABECERAS Y PIE DE PAGINA
            $pdf-> setPrintHeader(false);
            $pdf-> setPrintFooter(false);
            //INSERTAMOS PAGINA
            $pdf->AddPage();
            //DECLARAMOS FUENTE Y TAMAÑO
            $pdf->SetFont('helvetica', '', 18); // Establece la fuente

            //Buscamos imagen y la decodificamos 
            $file = base64_encode( \Storage::disk('images')->get('logo-solo2.png'));
            //$file = base64_encode( \Storage::disk('images')->get('pe.jpg'));
            //descodificamos y asignamos
            $image = base64_decode($file);
            //insertamos imagen se pone @ para especificar que es a base64
            //              imagen,x1,y1,ancho,largo
            $pdf->Image('@'.$image,10,9,25,25);
            $pdf->setXY(40,8);
            //ESCRIBIMOS
            //        ancho,altura,texto,borde,salto de linea
            $pdf->Cell(0, 10, $Empresa->nombreLargo, 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(45,15);
            $pdf->Cell(0, 10, $Empresa->nombreCorto.': COLONIA '. $Empresa->colonia.', CALLE '. $Empresa->calle. ' #'. 
                                $Empresa->numero. ', '. $Empresa->ciudad. ', '. $Empresa->estado, 0, 1); // Agrega un texto

            $pdf->setXY(60,20);
            $pdf->Cell(0,10,'CORREOS: '. $Empresa->correo1. ', '. $Empresa->correo2);

            $pdf->setXY(68,25);
            $pdf->Cell(0,10,'TELEFONOS: '. $Empresa->telefono. ' ó '. $Empresa->telefono2. '   RFC: '. $Empresa->rfc);

            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,37,200,37);//X1,Y1,X2,Y2

            $pdf->SetLineWidth(5);//grosor de la linea
            $pdf->Line(10,43,58,43);//X1,Y1,X2,Y2

            $pdf->SetFont('helvetica', 'B', 12); // Establece la fuente
            $pdf->setXY(10,38);
            $pdf->Cell(0, 10, 'COMPRA #'. $compra->idCompra, 0, 1); // Agrega un texto
            
            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,38);
            $pdf->Cell(0, 10, 'PROVEEDOR: ', 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            $pdf->setXY(82,38);
            $pdf->Cell(0, 10,strtoupper($compra->nombreProveedor), 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,43);
            $pdf->Cell(0, 10, 'FOLIO DEL PROVEEDOR: ', 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            $pdf->setXY(99.5,43);
            $pdf->Cell(0, 10,strtoupper($compra->folioProveedor), 0, 1); // Agrega un texto
            
            $pdf->setXY(157,43);
            $pdf->Cell(0, 10, 'FECHA: '. substr($compra->created_format,0,10), 0, 1); // Agrega un texto

            
            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,48);
            $pdf->Cell(0, 10, 'EMPLEADO: '. strtoupper($compra->nombreEmpleado), 0, 1); // Agrega un texto

            $mytime = date('d/m/Y H:i:s');
            $pdf->setXY(153,48);
            $pdf->Cell(0, 10, 'IMPRESO: '. $mytime, 0, 1); // Agrega un texto


            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,57,200,57);//X1,Y1,X2,Y2

            $pdf->SetDrawColor(0,0,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(.2);//grosor de la linea
            $pdf->SetFillColor(7, 149, 223  );//Creamos color de relleno para la tabla
            $pdf->setXY(10,62);

            //Contamos el numero de productos
            $numRegistros = count($productosCompra);
            //establecemos limite de productos por pagina
            $RegistroPorPagina = 18;
            //calculamos cuantas paginas van hacer
            $paginas = ceil($numRegistros / $RegistroPorPagina);
            $contRegistros = 0;

            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            //INSERTAMOS CABECERAS TABLA
            $pdf->Cell(29,10,'CLAVE EXTERNA',1,0,'C',true);
            $pdf->Cell(70, 10, 'DESCRIPCION', 1,0,'C',true);
            $pdf->Cell(16, 10, 'MEDIDA', 1,0,'C',true);
            $pdf->Cell(16, 10, 'CANT.', 1,0,'C',true);
            $pdf->Cell(25, 10, 'PRECIO', 1,0,'C',true);
            $pdf->Cell(34, 10, 'SUBTOTAL', 1,0,'C',true);
            $pdf->Ln(); // Nueva línea3

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 10); // Establece la fuente

            //REALIZAMOS RECORRIDO DEL ARRAY DE PRODUCTOS
            foreach($productosCompra  as $prodC){
                /***
                 * Verificamos que nuestro contador sea mayor a cero para no insertar pagina de mas
                 * Utiliza el operador % (módulo) para verificar si el contador de registros es divisible
                 * exactamente por el número de registros por página ($RegistroPorPagina).
                 *  Si el resultado de esta expresión es igual a cero, significa que se ha alcanzado
                 *  un múltiplo del número de registros por página y se necesita agregar una nueva página.
                 */
                if( $contRegistros > 0 && $contRegistros % $RegistroPorPagina == 0){
                    $pdf->AddPage();
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFont('helvetica', 'B', 10); // Establece la fuente
                    //CABECERAS TABLA
                    $pdf->Cell(29,10,'CLAVE EXTERNA',1,0,'C',true);
                    $pdf->Cell(70, 10, 'DESCRIPCION', 1,0,'C',true);
                    $pdf->Cell(16, 10, 'MEDIDA', 1,0,'C',true);
                    $pdf->Cell(16, 10, 'CANT.', 1,0,'C',true);
                    $pdf->Cell(25, 10, 'PRECIO', 1,0,'C',true);
                    $pdf->Cell(34, 10, 'SUBTOTAL', 1,0,'C',true);
                    $pdf->Ln(); // Nueva línea3
                }
                    
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                    $pdf->MultiCell(29,10,$prodC->claveEx,1,'C',false,0);
                    $pdf->MultiCell(70,10,$prodC->descripcion,1,'C',false,0);
                    $pdf->MultiCell(16,10,$prodC->nombreMedida,1,'C',false,0);
                    $pdf->MultiCell(16,10,$prodC->cantidad,1,'C',false,0);
                    $pdf->MultiCell(25,10,'$'.$prodC->precio,1,'C',false,0);
                    $pdf->MultiCell(34,10,'$'.$prodC->subtotal,1,'C',false,0);
                    $pdf->Ln(); // Nueva línea

                    if($contRegistros == 18){
                        $RegistroPorPagina = 25;
                        $contRegistros = $contRegistros + 7;
                    }

                    $contRegistros++;
            }

            $posY= $pdf->getY();

            if($posY > 241){
                $pdf->AddPage();
                $posY = 0;
            }

            $pdf->setXY(145,$posY+10);
            $pdf->Cell(0,10,'TOTAL:                 $'. $compra->total,0,1,'L',false);

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(9,$posY+20);
            $pdf->MultiCell(0,10,'OBSERVACIONES: '. $compra->observaciones ,0,'L',false);

            $posY = $pdf->getY();

            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,$posY+5,200,$posY+5);//X1,Y1,X2,Y2

           

            $contenido = $pdf->Output('', 'I'); // Descarga el PDF con el nombre 'mi-archivo-pdf.pdf'
            $nombrepdf = 'mipdf.pdf';
        }else{


        }

        $nombreArchivo = '';
        return response($contenido)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"$nombreArchivo\"");


    }

    public function cancelarCompra(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        if( !empty($params_array)){
            $statusCompra = Compras::find($params_array['idCompra'])->idStatus; 
            if($statusCompra == 38){
                $data =  array(
                    'status'        => 'error',
                    'code'          =>  404,
                    'message'       =>  'La compra ya está cancelada'
                );
            }else{
                try{
                    DB::beginTransaction();
                    //Cambiamos status de compra a cancelada
                    $Compra = Compras::where('idCompra',$params_array['idCompra'])->update([
                        'idStatus' => 38
                    ]);
                    //Insertamos en monitoreo la cancelacion con su motivo
                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario = $params_array['idEmpleado'];
                    $monitoreo -> accion =  "Cancelacion de compra";
                    $monitoreo -> folioNuevo =  $params_array['idCompra'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo -> motivo = $params_array['motivo'];
                    $monitoreo ->save();
                    //Consultamos productos de la compra
                    $productosC = Productos_compra::where('idCompra',$params_array['idCompra'])->get();
                    //Restamos la existencia e insertamos el movimiento del producto
                    foreach($productosC AS $param => $paramdata ){
                        //Antes de actualizar el producto obtenemos su existenciaG, se realiza la operacion y se guarda
                        $Producto = Producto::find($paramdata['idProducto']);
                        $stockanterior = $Producto -> existenciaG;
                        $Producto -> existenciaG = $Producto -> existenciaG - $paramdata['igualMedidaMenor'];
                        $Producto->save();//guardamos el modelo
                        //Obtenemos la existencia del producto actualizado
                        $stockactualizado = Producto::find($paramdata['idProducto'])->existenciaG;

                        //insertamos el movimiento de existencia del producto
                        $moviproduc = new moviproduc();
                        $moviproduc -> idProducto =  $paramdata['idProducto'];
                        $moviproduc -> claveEx =  $Producto -> claveEx;
                        $moviproduc -> accion =  "Cancelación de compra, se descuenta del inventario";
                        $moviproduc -> folioAccion =  $params_array['idCompra'];
                        $moviproduc -> cantidad =  $paramdata['igualMedidaMenor'];
                        $moviproduc -> stockanterior =  $stockanterior;
                        $moviproduc -> stockactualizado =  $stockactualizado;
                        $moviproduc -> idUsuario =  $params_array['idEmpleado'];
                        $moviproduc -> pc =  $ip;
                        $moviproduc ->save();
                    }

                    $data =  array(
                        'status'            => 'success',
                        'code'              =>  200,
                        'message'           =>  'Cancelación de compra correcta!'
                    );

                    DB::commit();
                }catch(\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   =>  'Fallo algo',
                        'messageError' => $e -> getMessage(),
                        'error' => $e
                    );
                }
            }
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








}

