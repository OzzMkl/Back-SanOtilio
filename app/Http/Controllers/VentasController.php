<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\models\Cotizacion;
use App\models\Ventasg;
use App\models\Productos_ventasg;
use App\models\Empresa;
use App\models\Monitoreo;
use App\Producto;
use App\Productos_medidas;
use App\models\moviproduc;
use App\models\impresoras;

use App\Clases\clsProducto;

use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
//use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
//use Mike42\Escpos\PrintConnectors\FilePrintConnector;
//use Mike42\Escpos\CapabilityProfile;

class VentasController extends Controller
{
    /**
     * TIPO PAGO
     */
    public function indexTP(){
        //GENERAMOS CONSULTA
        $tp = DB::table('tipo_pago')
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'tipo_pago'   =>  $tp
        ]);
    }

    /**
     * TIPO VENTA
     */
    public function indexTipoVenta(){
        $tipo_venta = DB::table('tiposdeventas')
                        ->get();
                return response()->json([
                    'code' => 200,
                    'status' => 'success',
                    'tipo_venta' => $tipo_venta
                ]);
    }

    /***** VENTAS *****/
    public function indexVentas(){
        $ventas = DB::table('ventasg')
        ->join('cliente','cliente.idcliente','=','ventasg.idcliente')
        ->join('empleado','empleado.idEmpleado','=','ventasg.idEmpleado')
        ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventasg.idTipoVenta')
        ->select('ventasg.*',
                 DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),
                 DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                 'tiposdeventas.nombre as nombreTipoventa')
        ->where('ventasg.idStatus',16)
        ->orderBy('ventasg.idVenta','desc')
        ->get();
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'Ventas'    => $ventas
        ]);
    }

    public function getDetallesVenta($idVenta){
        $venta = DB::table('ventasg')
        ->join('cliente','cliente.idcliente','=','ventasg.idcliente')
        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
        ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventasg.idTipoVenta')
        ->join('statuss','statuss.idStatus','=','ventasg.idStatus')
        ->join('empleado','empleado.idEmpleado','=','ventasg.idEmpleado')
        ->select('ventasg.*',
                 'tiposdeventas.nombre as nombreTipoVenta',
                 'statuss.nombre as nombreStatus',
                 DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
                 DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        ->where('ventasg.idVenta','=',$idVenta)
        ->get();
        $productosVenta = DB::table('productos_ventasg')
        ->join('producto','producto.idProducto','=','productos_ventasg.idProducto')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ventasg.idProdMedida')
        ->select('productos_ventasg.*','productos_ventasg.total as subtotal','producto.claveEx as claveEx','historialproductos_medidas.nombreMedida')
        ->where('productos_ventasg.idVenta','=',$idVenta)
        ->get();
        if(is_object($venta)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'venta'   =>  $venta,
                'productos_ventasg'     => $productosVenta
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

    public function guardarVenta(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);
        if(!empty($params) && !empty($params_array)){
            //eliminamos espacios vacios
            $params_array = array_map('trim',$params_array);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'idCliente'       => 'required',
                'idTipoVenta'       => 'required',
                'idStatus'   => 'required',
                'idEmpleado'      => 'required',//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
                'subtotal'   => 'required',
                'total'   => 'required',
            ]);
            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo! La orden de compra no se ha creado',
                    'errors'    => $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();

                    $ventasg = new Ventasg();
                    $ventasg->idCliente = $params_array['idCliente'];
                    $ventasg->idTipoVenta = $params_array['idTipoVenta'];
                    $ventasg->observaciones = $params_array['observaciones'];
                    $ventasg->idStatus = 16;
                    $ventasg->idEmpleado = $params_array['idEmpleado'];
                    $ventasg->subtotal = $params_array['subtotal'];
                    if(isset($params_array['descuento'])){
                        $ventasg->descuento = $params_array['descuento'];
                    }
                    if(isset($params_array['cdireccion'])){
                        $ventasg->cdireccion = $params_array['cdireccion'];
                    }
                    $ventasg->total = $params_array['total'];
    
                    $ventasg->save();

                    //obtemos id de la ultima venta insertada
                    $ultimaVenta = Ventasg::latest('idVenta')->pluck('idVenta')->first();
                    //obtenemos ip
                    $ip = $_SERVER['REMOTE_ADDR'];

                    /****   
                     * Verificamos si la venta viene de alguna cotizacion 
                     * Si es que si asignamos status de deshabilitado
                     * ****/
                    if($params_array['idCotiza'] != 0){
                        $cotizacion =  Cotizacion::where('idCotiza',$params_array['idCotiza'])
                                                    ->update([
                                                        'idStatus' => 35
                                                    ]);
                        //insertamos el movimiento realizado
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                        $monitoreo -> accion =  "Cotizacion pasada a venta";
                        $monitoreo -> folioAnterior =  $params_array['idCotiza'];
                        $monitoreo -> folioNuevo =  $ultimaVenta;
                        $monitoreo -> pc =  $ip;
                        $monitoreo ->save();
                    }
    
                    /**** Iniciamos proceso de  monitoreo ****/
                    
                    //insertamos el movimiento realizado
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                    $monitoreo -> accion =  "Alta de venta";
                    $monitoreo -> folioNuevo =  $ultimaVenta;
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();
                    /**** FIN proceso de  monitoreo ****/
    
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Venta creada pero sin productos'
                    );

                    DB::commit();
                } catch (\Exception $e){
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
            $data = array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'Los datos enviados son incorrectos'
            );
        }
        return response()->json($data, $data['code']);
    }

    public function guardarProductosVenta(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json,true);
        if(!empty($params_array)){
            try{
                DB::beginTransaction();

                //consultamos la ultima venta realizada
                $ventasg = Ventasg::latest('idVenta')->first();
                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //recorremos la lista de productos
                foreach($params_array as $param => $paramdata){

                    /**************************** ACTUALIZA EXISTENCIA ***************************************** */

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
                        $Producto -> existenciaG = $Producto -> existenciaG - $cantidadC;
                        $igualMedidaMenor = $cantidadC;
                    }else{//Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                        //Se hace un cilo que recorre listaPM
                        while($idProdMedidaC != $listaPM[$lugar]['attributes']['idProdMedida']){
                            //echo $listaPM[$lugar]['attributes']['idProdMedida'];
                            //echo $lugar;
                            $lugar++;
                        }
                        if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                            $Producto -> existenciaG = $Producto -> existenciaG - $cantidadC;
                            $igualMedidaMenor = $cantidadC;
                        }elseif($lugar == 0){//Medida mas alta, multiplicar desde el principio ( lugar == 0)
                            $igualMedidaMenor = $cantidadC;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar]['attributes']['unidad'];
                                $lugar++;
                                //echo $igualMedidaMenor;
                            }
                            $Producto -> existenciaG = $Producto -> existenciaG - $igualMedidaMenor;
                        }elseif($lugar>0 && $lugar<$count-1){//Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                            $igualMedidaMenor = $cantidadC;
                            $count--;
                            //echo $count;
                            while($lugar < $count ){
                                $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]['attributes']['unidad'];
                                $lugar++;
                            }
                            $Producto -> existenciaG = $Producto -> existenciaG - $igualMedidaMenor;
                        }else{

                        }
                    }
                    
                    $Producto->save();//guardamos el modelo
                    /****************************FIN ACTUALIZA EXISTENCIA***************************************** */

                    /****************************INGRESA MOVIMIENTO PRODUCTO***************************************** */

                    //obtenemos la existencia del producto actualizado
                    $stockactualizado = Producto::find($paramdata['idProducto'])->existenciaG;

                    //insertamos el movimiento de existencia del producto
                    $moviproduc = new moviproduc();
                    $moviproduc -> idProducto =  $paramdata['idProducto'];
                    $moviproduc -> claveEx =  $paramdata['claveEx'];
                    $moviproduc -> accion =  "Alta de venta";
                    $moviproduc -> folioAccion =  $ventasg->idVenta;
                    $moviproduc -> cantidad =  $igualMedidaMenor;
                    $moviproduc -> stockanterior =  $stockanterior;
                    $moviproduc -> stockactualizado =  $stockactualizado;
                    $moviproduc -> idUsuario =  $ventasg->idEmpleado;
                    $moviproduc -> pc =  $ip;
                    $moviproduc ->save();

                    /**************************** FIN INGRESA MOVIMIENTO PRODUCTO ***************************************** */

                    /**************************** REGISTRA PRODUCTOS VENTAG ***************************************** */

                    $productos_ventasg = new Productos_ventasg();
                    $productos_ventasg-> idVenta = $ventasg->idVenta;
                    $productos_ventasg-> idProducto = $paramdata['idProducto'];
                    $productos_ventasg-> descripcion = $paramdata['descripcion'];
                    $productos_ventasg-> idProdMedida = $paramdata['idProdMedida'];
                    $productos_ventasg-> cantidad = $paramdata['cantidad'];
                    $productos_ventasg-> precio = $paramdata['precio'];
                    if(isset($paramdata['descuento'])){
                        $productos_ventasg-> descuento = $paramdata['descuento'];
                    }
                    $productos_ventasg-> total = $paramdata['subtotal'];
                    $productos_ventasg-> igualMedidaMenor = $igualMedidaMenor;
                    //guardamos el producto
                    $productos_ventasg->save();

                    /**************************** FIN REGISTRA PRODUCTOS VENTAG ***************************************** */
                }

                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'status'        => 'success',
                    'code'          =>  200,
                    'Productos_ventasg'       =>  $productos_ventasg
                );

                DB::commit();
            } catch (\Exception $e){
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
        if($data['status'] == 'success'){
            // var_dump($params_array);
            // die();
            if($ventasg->idTipoVenta == 1 || $ventasg->idTipoVenta == 2 || $ventasg->idTipoVenta == 3){
                if($ventasg->total >= 1000 || count($params_array) > 7){
                    $this-> generaTicketPeque();
                } else{
                    if($ventasg->idTipoVenta == 3){
                        $this-> generaTicket($NoImpre=4);
                    } else{
                        $this-> generaTicket($NoImpre=3);
                    }
                }
            } elseif($ventasg->idTipoVenta == 4 || $ventasg->idTipoVenta == 5 || $ventasg->idTipoVenta == 6){
                $this-> generaTicketPeque();
            }
        }
        return response()->json($data, $data['code']);
    }

    public function updateVenta($idVenta, Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        // echo 'vardmp <br>';
        // var_dump($params_array);
        // die();

        if(!empty($params_array)){
            //eliminar espacios vacios
            //$params_array = array_map('trim', $params_array['ventasg']);   

            $validate = Validator::make($params_array['ventasg'],[
                'idVenta'       =>  'required',
                'idCliente'     =>  'required',
                'idTipoVenta'   =>  'required',
                //'observaciones' =>  'required',//realmente no es necesario XD
                'idStatus'      =>  'required',
                'idEmpleado'    =>  'required',
                'subtotal'      =>  'required',
                'descuento'     =>  'required',
                'total'         =>  'required'
            ]);
            
            //si falla creamos la respuesta a enviar
            if($validate->fails()){
                $data = array(
                    'code'      =>  '404',
                    'status'    =>  'error',
                    'message'   =>  'Fallo la validacion de los datos del producto',
                    'errors'    =>  $validate->errors()
                );
            } else{
                //Verificar si la venta tiene status correcto para editar
                if($params_array['ventasg']['idStatus'] == 16){
                    try{
                        DB::beginTransaction();
    
                        //Consultamos venta a actualizar
                        //$antVenta = Ventasg::where('idVenta',$idVenta)->firts();
    
                        //actualizamos valores de venta
                        $ventag = Ventasg::where('idVenta',$idVenta)->update([
                                    'idCliente' => $params_array['ventasg']['idCliente'],
                                    'idTipoVenta' => $params_array['ventasg']['idTipoVenta'],
                                    'observaciones' => $params_array['ventasg']['observaciones'],
                                    'idStatus' => 35,
                                    'idEmpleado' => $params_array['ventasg']['idEmpleado'],
                                    'subtotal' => $params_array['ventasg']['subtotal'],
                                    'descuento' => $params_array['ventasg']['descuento'],
                                    'total' => $params_array['ventasg']['total'],
                                ]);

                        //obtenemos ip
                        $ip = $_SERVER['REMOTE_ADDR'];
                    
                        //insertamos el movimiento realizado
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                        $monitoreo -> accion =  "Modificacion de venta";
                        $monitoreo -> folioNuevo =  $idVenta;
                        $monitoreo -> pc =  $ip;
                        $monitoreo -> motivo =  $params_array['motivo_edicion'];
                        $monitoreo ->save();

                        /*** INICIO INSERCION DE PRODUCTOS */
                        
                        $dataProductos = $this->updateProductosVenta($params_array['ventasg'], $params_array['lista_productoVentag']);


                        /*** FIN INSERCION DE PRODUCTOS */
                        
                        $data = [
                            'code' => 200,
                            'status' => 'success',
                            'message' => 'venta modificada exitosamente',
                            'data_productos' => $dataProductos
                        ];
    
    
                        DB::commit();
    
                    } catch (\Exception $e){
                        DB::rollBack();
                        $data = array(
                            'code'      => 400,
                            'status'    => 'Error',
                            'message'   => $e->getMessage(),
                            'error'     => $e
                        );
                    }
                } else{
                    $data = array(
                        'code'      =>  404,
                        'status'    =>  'error',
                        'message'   =>  'La venta no tiene el status correcto',
                    );
                }
            }
        } else{
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Los valores ingresado no se recibieron correctamente'
            );
        }
        return response()->json($data,$data['code']);
    }
    public function updateProductosVenta($ventasg,$lista_productosVenta){
        if(count($lista_productosVenta) >= 1 && !empty($lista_productosVenta)){
            try{
                DB::beginTransaction();
                //Creamos instancia para poder ocupar las funciones
                $clsMedMen = new clsProducto();

                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //Consultamos productos a eliminar
                $lista_prodVen_ant = Productos_ventasg::where('idVenta',$ventasg['idVenta'])->get();

                /**
                 * Reccorremos la consulta resultante para:
                 * -Regresar la existencia que se desconnto de la venta
                 * -Insertar Movimiento de existencia del producto
                 */
                foreach($lista_prodVen_ant as $param => $paramdata){
                    //Consultamos la existencia antes de actualizar
                    $Producto = Producto::find($paramdata['idProducto']);
                    $stockAnterior = $Producto -> existenciaG;

                    //Actualizamos
                    $Producto -> existenciaG = $Producto -> existenciaG + $paramdata['igualMedidaMenor'];
                    $Producto -> save();

                    //Consultamos la existencia despues de actualizar
                    $stockActualizado = $Producto->existenciaG;

                    //insertamos el movimiento de existencia que se le realizo al producto
                    $moviproduc = new moviproduc();
                    $moviproduc -> idProducto =  $paramdata['idProducto'];
                    $moviproduc -> claveEx =  $Producto -> claveEx;
                    $moviproduc -> accion =  "Modificacion de venta, se suma al inventario";
                    $moviproduc -> folioAccion =  $ventasg['idVenta'];
                    $moviproduc -> cantidad =  $paramdata['igualMedidaMenor'];
                    $moviproduc -> stockanterior =  $stockAnterior;
                    $moviproduc -> stockactualizado =  $stockActualizado;
                    $moviproduc -> idUsuario =  $ventasg['idEmpleado'];
                    $moviproduc -> pc =  $ip;
                    $moviproduc ->save();
                }
                
                //eliminamos los registros que tenga esa venta
                Productos_ventasg::where('idVenta',$ventasg['idVenta'])->delete();

                /**
                 * Recorremos el array para:
                 * -Actualizar existencia en el producto
                 * -Actualizar los productos de la venta
                 * -Insertar Movimiento de existencia del producto
                 */
                foreach($lista_productosVenta as $param => $paramdata){

                    //calculamos medida menor
                    $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$paramdata['cantidad']);

                    //Consultamos la existencia antes de actualizar
                    $Producto = Producto::find($paramdata['idProducto']);
                    $stockAnterior = $Producto -> existenciaG;
                    //actualizamos la existencia
                    $Producto -> existenciaG = $Producto -> existenciaG - $medidaMenor;
                    $Producto -> save();
                    //Consultamos la existencia despues de actualizar
                    $stockActualizado = $Producto->existenciaG;

                    //Actualimos en los productos de la venta
                    $producto_ventasg = new Productos_ventasg();
                    $producto_ventasg->idVenta = $ventasg['idVenta'];
                    $producto_ventasg->idProducto = $paramdata['idProducto'];
                    $producto_ventasg->descripcion = $paramdata['descripcion'];
                    $producto_ventasg->idProdMedida = $paramdata['idProdMedida'];
                    $producto_ventasg->cantidad = $paramdata['cantidad'];
                    $producto_ventasg->precio = $paramdata['precio'];

                    if(isset($paramdata['descuento'])){
                        $producto_ventasg->descuento = $paramdata['descuento'];
                    }

                    $producto_ventasg->igualMedidaMenor = $medidaMenor;
                    $producto_ventasg->total = $paramdata['subtotal'];

                    //guardamos el producto
                    $producto_ventasg->save();

                    //Insertamos movimiento producto
                    $moviproduc = new moviproduc();
                    $moviproduc -> idProducto =  $paramdata['idProducto'];
                    $moviproduc -> claveEx =  $Producto->claveEx;
                    $moviproduc -> accion =  "Modificacion de venta, se guarda despues de la modificacion";
                    $moviproduc -> folioAccion =  $ventasg['idVenta'];
                    $moviproduc -> cantidad =  $medidaMenor;
                    $moviproduc -> stockanterior =  $stockAnterior;
                    $moviproduc -> stockactualizado =  $stockActualizado;
                    $moviproduc -> idUsuario =  $ventasg['idEmpleado'];
                    $moviproduc -> pc =  $ip;
                    $moviproduc ->save();
                    
                }

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Productos actualizados correctamente'
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

    public function generaTicket($NoImpre){
        /************** */
            //$nombreImpresora = "EPSON TM-U220 Receipt";
            //$profile = CapabilityProfile::load("simple");
                                                    //  Usuario,Contraseña,nombremaquina ó ip,nombre de la impresora
            
            //$connector = new FilePrintConnector("//SISTEMAS02/EPSON TM-U220 Receipt");

            /*****traemos informacion de la empresa*****/
            $empresa = Empresa::first();
            //informacion de la venta
            $ventasg = DB::table('ventasg')
                        ->join('cliente','cliente.idcliente','=','ventasg.idcliente')
                        ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventasg.idTipoVenta')
                        ->join('empleado','empleado.idEmpleado','=','ventasg.idEmpleado')
                        ->select('ventasg.*',
                                 'tiposdeventas.nombre as nombreVenta',
                                 DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),
                                 DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                        ->latest('idVenta')
                        ->first();
            //informacion de la venta
            $productos_ventasg = Productos_ventasg::where('idVenta',$ventasg->idVenta)
                                 ->join('producto','producto.idProducto','=','productos_ventasg.idProducto')
                                 ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ventasg.idProdMedida')
                                 ->select('productos_ventasg.*','producto.claveEx as claveEx','historialproductos_medidas.nombreMedida')
                                 ->get();

            //obtenemos direccion ip
            $ip = $_SERVER['REMOTE_ADDR'];

            $datos_imp = Impresoras::where('ipVentas','=',$ip)
                            ->latest('idImpresora')
                            ->first();

            if(is_object($datos_imp)){
                for($i = 1; $i<= $NoImpre ; $i++){
                    //declaramos el nombre de la impresora
                    //$connector = new WindowsPrintConnector("smb://Admin:soMATv03@ventas03mat/EPSONTMU220B V3");
                    $connector = new WindowsPrintConnector("smb://".$datos_imp->usuario.":".$datos_imp->contrasena."@".$datos_imp->nombreMaquina."/".$datos_imp->nombreImpresora."");
                    //$connector = new WindowsPrintConnector("EPSON TM-U220 Receipt");
                    //asociamos la impresora
                    $impresora = new Printer($connector);
                    //ajustamos el texto en el centro
                    $impresora->setJustification(Printer::JUSTIFY_CENTER);
                    //declaramos imagen
                    $img = EscposImage::load("../storage/app/images/logo2.png");
                    //insertamos imagen
                    $impresora->bitImageColumnFormat($img, Printer::IMG_DOUBLE_WIDTH | Printer::IMG_DOUBLE_HEIGHT);
                    //ajustamos tamaño del texto
                    $impresora->setTextSize(1, 1);
                    //escribimos MATERIALES PARA CONSTRUCCION SAN OTILIO
                    $impresora->text( $empresa->nombreLargo ."\n");
                    //Escribimos SUCURSAL MATRIZ
                    $impresora->text($empresa->nombreCorto ." \n");
                    //Empezamos con la direccion C. SONORA SUR #2509, MEXICO SUR
                    $impresora->text("C.". $empresa->calle." #".$empresa->numero.", ".$empresa->colonia ."\n");
                    //TEHUACAN, PUEBLA. RFC: LUPB7803313V9
                    $impresora->text($empresa->ciudad.", ".$empresa->estado.". RFC: ".$empresa->rfc."\n");
                    //EMAIL: sabin_mil1000@hotmail.com
                    $impresora->text("EMAIL:".$empresa->correo1. "\n");
                    //238 107 1077 - 238 125 7845
                    $impresora->text($empresa->telefono." - ".$empresa->telefono2. "\n");
                    $impresora->text("========================================\n");
                    /***** INFORMACION DE LA VENTA PRIMERA PARTE*****/
                    //ajustamos el texto en el centro
                    $impresora->setJustification(Printer::JUSTIFY_LEFT);
                    //VENTA: 00000
                    $impresora->text("VENTA: ".$ventasg->idVenta."   TV: ".$ventasg->nombreVenta. "\n");
                    //VENDEDOR: 
                    $impresora->text("VENDEDOR: ".$ventasg->nombreEmpleado. "\n");
                    //CLIENTE: 
                    $impresora->text("CLIENTE: ".str_pad($ventasg->nombreCliente,25," "). "\n");
                    //fecha y hora
                    $impresora->text("FECHA: ".$ventasg->created_at. "\n");
                    $impresora->text("========================================\n");
                    /***** PRODUCTOS *****/
                    $impresora->text("CL./ CANT./ MED./ PRECIO/ DESC./ SUBTO."." \n");//40
                    foreach($productos_ventasg AS $param => $paramdata){
                        //
                        $impresora->text($paramdata['descripcion']."\n");
                        $impresora->text(str_pad($paramdata['claveEx'],10," ")."/".
                                        str_pad($paramdata['cantidad'],3," ",STR_PAD_BOTH)."/".
                                        str_pad($paramdata['nombreMedida'],4," ",STR_PAD_BOTH)."/"."$".
                                        str_pad($paramdata['precio'],6," ",STR_PAD_BOTH)."/"."$".
                                        str_pad($paramdata['descuento'],3," ",STR_PAD_BOTH)."/"."$".
                                        str_pad($paramdata['total'],6," ",STR_PAD_BOTH)."\n");
                        
                        $impresora->text("- - - - - - - - - - - - - - - - - - - - \n");
                    }
                    /***** INFORMACION DE LA VENTA 2DA PARTE *****/
                    //$impresora->text("---------------------------------------- \n");
                    $impresora->text("SUBTOTAL:".str_pad("$".$ventasg->subtotal,30," ",STR_PAD_LEFT)."\n");
                    $impresora->text("DESCUENTO:".str_pad("$".$ventasg->descuento,29," ",STR_PAD_LEFT)."\n");
                    $impresora->setJustification(Printer::JUSTIFY_RIGHT);
                    $impresora->text("                   ---------- \n");
                    $impresora->setJustification(Printer::JUSTIFY_LEFT);
                    $impresora->text("TOTAL:".str_pad("$".$ventasg->total,33," ",STR_PAD_LEFT)."\n");
                    $impresora->text("----------------------------------------\n");
                    $impresora->text("OBSERVACIONES: \n");
                    $impresora->text($ventasg->observaciones."\n");
                    $impresora->text("========================================\n");
                    $impresora->text("* TODO CAMBIO CAUSARA UN 10% EN EL IMPORTE TOTAL *"."\n");
                    $impresora->text("* TODA CANCELACION SE COBRARA 20% DEL IMPORTE TOTAL SIN EXCEPCION *"."\n");
                    $impresora->cut();
                    $impresora->close();
                    /************** */
                }
            } else{
                return response()->json([
                    'code'      =>  200,
                    'status'    => 'success',
                    'message'   => 'La ip no esta registrada'
                ]);
            }
            
    }

    public function generaTicketPeque(){

        //informacion de la venta
        $ventasg = DB::table('ventasg')
                    ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventasg.idTipoVenta')
                    ->select('ventasg.idVenta',
                            'ventasg.subtotal',
                            'ventasg.descuento',
                            'ventasg.total',
                            'ventasg.observaciones',
                            'ventasg.created_at',
                            'tiposdeventas.nombre as nombreVenta')
                    ->latest('idVenta')
                    ->first();

        //obtenemos direccion ip
        $ip = $_SERVER['REMOTE_ADDR'];
        
        //Informacion de impresoras
        $datos_imp = Impresoras::where('ipVentas','=',$ip)
                            ->latest('idImpresora')
                            ->first();

        if(is_object($datos_imp)){

            //declaramos el nombre de la impresora
            //$connector = new WindowsPrintConnector("smb://Admin:soMATv03@ventas03mat/EPSONTMU220B V3");
            $connector = new WindowsPrintConnector("smb://".$datos_imp->usuario.":".$datos_imp->contrasena."@".$datos_imp->nombreMaquina."/".$datos_imp->nombreImpresora."");
            //$connector = new WindowsPrintConnector("EPSON TM-U220 Receipt");
            //asociamos la impresora
            $impresora = new Printer($connector);
            //ajustamos tamaño del texto
            $impresora->setTextSize(1, 1);
            //escribimos     Folio venta: ####
            $impresora->text( "Folio venta: ".$ventasg->idVenta."\n");
            //Escribimos     Tipo venta: Paga, se lo lleva etc ...
            $impresora->text( "Tipo venta: ".$ventasg->nombreVenta."\n");
            //fecha y hora
            $impresora->text("Fecha: ".$ventasg->created_at. "\n");
            $impresora->text("========================================\n");
            $impresora->text("Subtotal:".str_pad("$".$ventasg->subtotal,30," ",STR_PAD_LEFT)."\n");
            $impresora->text("Descuento:".str_pad("$".$ventasg->descuento,29," ",STR_PAD_LEFT)."\n");
            $impresora->setJustification(Printer::JUSTIFY_RIGHT);
            $impresora->text("                   ---------- \n");
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            $impresora->text("Total:".str_pad("$".$ventasg->total,33," ",STR_PAD_LEFT)."\n");
            $impresora->text("----------------------------------------\n");
            $impresora->text("Observaciones: \n");
            $impresora->text($ventasg->observaciones."\n");
            $impresora->text("\n");
            $impresora->cut();
            $impresora->close();
        } else{
            return response()->json([
                'code'      =>  200,
                'status'    => 'success',
                'message'   => 'La ip no esta registrada'
            ]);
        }
    }
    
    /****ENTREGAS */
    public function indexEntregas(){
        $ventas = DB::table('ventasg')
        ->join('cliente','cliente.idcliente','=','ventasg.idcliente')
        ->join('empleado','empleado.idEmpleado','=','ventasg.idEmpleado')
        ->join('statuss','statuss.idStatus','ventasg.idStatus')
        ->select('ventasg.*',
                 DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),
                 DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                 'statuss.nombre as nombreStatus')
        ->where('ventasg.idStatus',22)
        ->orwhere('ventasg.idStatus',16)
        ->orwhere('ventasg.idStatus',3)
        ->get();
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'entregas'    => $ventas
        ]);
    }
}
