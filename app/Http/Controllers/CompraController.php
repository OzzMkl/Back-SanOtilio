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


class CompraController extends Controller
{
    //
    public function index(){
        $compra = DB::table('compra')
        ->join('ordendecompra','ordendecompra.idOrden','=','compra.idOrden')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->join('empleado','empleado.idEmpleadoR','=','empleado.idEmpleado')
        ->select('compra.*','proveedores.nombre as nombreProveedor',)
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
                'folioProveedor'   => 'required'
            ]);
            if($validate->fails()){//si el json esta mal mandamos esto (falta algun dato)
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo! La compra no se ha creado',
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
                           
                           $Productos_compra->save();//guardamos el modelo
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
                    }else{//Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                        //Se hace un cilo que recorre listaPM
                        while($idProdMedidaC != $listaPM[$lugar]['attributes']['idProdMedida']){
                            //echo $listaPM[$lugar]['attributes']['idProdMedida'];
                            //echo $lugar;
                            $lugar++;
                        }
                        if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                            $Producto -> existenciaG = $Producto -> existenciaG + $cantidadC;
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
                    $moviproduc -> claveEx =  $paramdata['claveEx'];
                    $moviproduc -> accion =  "Alta de compra";
                    $moviproduc -> folioAccion =  $Foliocompra;
                    $moviproduc -> cantidad =  $paramdata['cantidad'];
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
                    }else{//Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                        //Se hace un cilo que recorre listaPM
                        while($idProdMedidaC != $listaPM[$lugar]['attributes']['idProdMedida']){
                            //echo $listaPM[$lugar]['attributes']['idProdMedida'];
                            //echo $lugar;
                            $lugar++;
                        }
                        if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                            $Producto -> existenciaG = $Producto -> existenciaG + $cantidadC;
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
                    $moviproduc -> claveEx =  $paramdata['claveEx'];
                    $moviproduc -> accion =  "Alta de compra";
                    $moviproduc -> folioAccion =  $Foliocompra;
                    $moviproduc -> cantidad =  $paramdata['cantidad'];
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
                'code'          => 200,
                'status'        => 'success',
                'compra'   =>  $compra,
                'productos'     => $productosCompra
            ];
        }else{
            $data = [
                'code'          => 400,
                'status'        => 'error',
                'message'       => 'El producto no existe'
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



}

