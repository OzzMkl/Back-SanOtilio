<?php

namespace App\Http\Controllers;

use App\models\Productos_ventas_corre;
use App\models\Ventas_corre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\models\Cotizacion;
use App\models\Ventasg;
use App\models\Ventascan;
use App\models\Ventasf;
use App\models\Ventascre;
use App\models\Productos_ventascre;
use App\models\Productos_ventasf;
use App\models\Productos_ventasg;
use App\models\Productos_ventascan;
use App\models\Empresa;
use App\models\Monitoreo;
use App\Producto;
use App\Productos_medidas;
use App\models\moviproduc;
use App\models\impresoras;
use App\models\Abono_venta;
use App\Cliente;

use App\Clases\clsProducto;

use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
//use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
//use Mike42\Escpos\PrintConnectors\FilePrintConnector;
//use Mike42\Escpos\CapabilityProfile;
use Carbon\Carbon;

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
    public function indexVentas(Request $request){
        $status = [
            4, //NO COBRADA
            5, //COBRO PARCIAL
        ];
        /**
         * 1 Todas las ventas
         * 2 ventasg
         * 3 ventascre
         * 4 ventas_corre
         */
        $showVenta = $request->input('showVenta',1);
        $type = $request->input('type',1);
        $search = $request->input('search','');
        // dd($request->all());
        
        // Alta de variables para consulta
        $ventasg = null;
        $ventascre=null;
        $ventas_corre=null;

        //Filtro de busqueda entre tablas
        if ($showVenta == 1) {//mostrar todas las ventas

            $ventasg =  $this->query_ventasg_index();
                    $ventasg->whereIn('ventasg.idStatusCaja', $status);

            $ventascre = $this->query_ventascre_index();
                        $ventascre->whereIn('ventascre.idStatusCaja', $status);

            $ventas_corre = $this->query_ventas_corre_index();
                        $ventas_corre->whereIn('ventas_corre.idStatusCaja', $status);

        } elseif( $showVenta == 2){ //mostrar solo ventasg
            $ventasg =  $this->query_ventasg_index();
                    $ventasg->whereIn('ventasg.idStatusCaja', $status);

        }elseif($showVenta == 3){ //mostrar solo ventascre
            $ventascre = $this->query_ventascre_index();
                        $ventascre->whereIn('ventascre.idStatusCaja', $status);

        }elseif( $showVenta == 4){ //mostrar solo ventas_corre
            $ventas_corre = $this->query_ventas_corre_index('ventas_corre.idStatusCaja', $status);
        }

        // Filtrar por folio (idVenta)
        if ($type == 1 && $search != '') {
            
            if ($ventasg) {
                $ventasg->where('ventasg.idVenta', 'like', '%' . $search . '%');
            }
            if ($ventascre) {
                $ventascre->where('ventascre.idVenta', 'like', '%' . $search . '%');
            }
            if ($ventas_corre) {
                $ventas_corre->where('ventas_corre.idVenta', 'like', '%' . $search . '%');
            }
        }

        // Filtrar por Cliente
        if ($type == 2 && $search != '') {

            if ($ventasg) {
                $ventasg->whereRaw("CONCAT(cliente.nombre, ' ', cliente.aPaterno, ' ', cliente.aMaterno) like ?", ['%' . $search . '%']);
            }
            if ($ventascre) {
                $ventascre->whereRaw("CONCAT(cliente.nombre, ' ', cliente.aPaterno, ' ', cliente.aMaterno) like ?", ['%' . $search . '%']);
            }
            if ($ventas_corre) {
                $ventas_corre->whereRaw("CONCAT(cliente.nombre, ' ', cliente.aPaterno, ' ', cliente.aMaterno) like ?", ['%' . $search . '%']);
            }
        }

        // Filtrar por Vendedor
        if ($type == 3 && $search != '') {

            if ($ventasg) {
                $ventasg->whereRaw("CONCAT(empleado.nombre, ' ', empleado.aPaterno, ' ', empleado.aMaterno) like ?", ['%' . $search . '%']);
            }
            if ($ventascre) {
                $ventascre->whereRaw("CONCAT(empleado.nombre, ' ', empleado.aPaterno, ' ', empleado.aMaterno) like ?", ['%' . $search . '%']);
            }
            if ($ventas_corre) {
                $ventas_corre->whereRaw("CONCAT(empleado.nombre, ' ', empleado.aPaterno, ' ', empleado.aMaterno) like ?", ['%' . $search . '%']);
            }
        }

        // Obtener resultados y combinar según $showVenta
        if ($showVenta == 1) {
            $ventas = $ventasg->union($ventascre)->union($ventas_corre)->orderBy('idVenta', 'desc')->get();
        } elseif ($showVenta == 2) {
            $ventas = $ventasg->orderBy('idVenta', 'desc')->get();
        } elseif ($showVenta == 3) {
            $ventas = $ventascre->orderBy('idVenta', 'desc')->get();
        } elseif($showVenta ==4){
            $ventas = $ventas_corre->orderBy('idVenta', 'desc')->get();
        } else {
            // En caso de un valor inesperado para $showCredito, devuelve un error
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Valor no válido para isCredito'
            ]);
        }


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
        ->join('statuss','statuss.idStatus','=','ventasg.idStatusCaja')
        ->leftjoin('statuss as statuss2','statuss2.idStatus','=','ventasg.idStatusEntregas')//Proximo a modificar
        ->join('empleado','empleado.idEmpleado','=','ventasg.idEmpleado')
        ->select('ventasg.*',
                 'tiposdeventas.nombre as nombreTipoVenta',
                 'statuss.nombre as nombreStatus',
                 'statuss2.nombre as nombreStatusEntregas',
                 DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
                 DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        ->where('ventasg.idVenta','=',$idVenta)
        ->get()
        ->map(function ($obj) {
            $obj->isCredito = false;
            $obj->seEnvia = ($obj->idStatusEntregas == 6) ? false : true;
            return $obj;
        });
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

            //validamos los datos
            $validate = Validator::make($params_array['ventasg'], [
                'idCliente'       => 'required',
                'idTipoVenta'       => 'required',
                'idEmpleado'      => 'required',
                'subtotal'   => 'required',
                'total'   => 'required',
            ]);

            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Validacion fallida, la venta no se genero.',
                    'errors'    => $validate->errors()
                );
            } else{
                try{
                    DB::beginTransaction();

                    $ventasg = new Ventasg();
                    $ventasg->idCliente = $params_array['ventasg']['idCliente'];
                    $ventasg->idTipoVenta = $params_array['ventasg']['idTipoVenta'];
                    $ventasg->observaciones = $params_array['ventasg']['observaciones'];
                    $ventasg->idStatusCaja = 4;
                    $ventasg->idStatusEntregas = ($params_array['ventasg']['seEnvia'] == true ) ? 7 : 6;
                    $ventasg->idEmpleado = $params_array['ventasg']['idEmpleado'];
                    $ventasg->subtotal = $params_array['ventasg']['subtotal'];
                    $ventasg->descuento = $params_array['ventasg']['descuento'] ? $params_array['ventasg']['descuento'] : 0.00;
                    $ventasg->cdireccion = $params_array['ventasg']['cdireccion'] ? $params_array['ventasg']['cdireccion'] : '';
                    $ventasg->total = $params_array['ventasg']['total'];
                    $ventasg->created_at = Carbon::now();
                    $ventasg->updated_at = Carbon::now();
                    $ventasg->save();

                    //obtenemos ip
                    $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);

                    /****   
                     * Verificamos si la venta viene de alguna cotizacion 
                     * Si es que si asignamos status de deshabilitado
                     * ****/
                    if($params_array['ventasg']['idCotiza'] != 0){
                        $cotizacion =  Cotizacion::where('idCotiza',$params_array['ventasg']['idCotiza'])
                                                    ->update([
                                                        'idStatus' => 35
                                                    ]);
                        //insertamos el movimiento realizado
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $params_array['ventasg']['idEmpleado'];
                        $monitoreo -> accion =  "Cotizacion pasada a venta";
                        $monitoreo -> folioAnterior =  $params_array['ventasg']['idCotiza'];
                        $monitoreo -> folioNuevo =  $ventasg->idVenta;
                        $monitoreo -> pc =  $ip;
                        $monitoreo->created_at = Carbon::now();
                        $monitoreo->updated_at = Carbon::now();
                        $monitoreo ->save();
                    }
    
                    /**** Iniciamos proceso de  monitoreo ****/
                    
                    //insertamos el movimiento realizado
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['ventasg']['idEmpleado'];
                    $monitoreo -> accion =  "Alta de venta";
                    $monitoreo -> folioNuevo =  $ventasg->idVenta;
                    $monitoreo -> pc =  $ip;
                    $monitoreo->created_at = Carbon::now();
                    $monitoreo->updated_at = Carbon::now();
                    $monitoreo ->save();
                    /**** FIN proceso de  monitoreo ****/

                    /** INICIO DE INSERCION DE PRODUCTOS */
                    if($ventasg->idTipoVenta == 7){
                        $dataProductos = [];
                        $this->guardaVentaCorre($ventasg,$params_array['lista_productoVentag']);
                    } else{
                        $dataProductos = $this->guardarProductosVenta($ventasg, $params_array['lista_productoVentag']);
                    }
                    /** FIN DE INSERCION DE PRODUCTOS */
    
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Venta creada exitosamente',
                        'idVenta'   =>  $ventasg->idVenta,
                        'data_productos' => $dataProductos
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
        return response()->json($data);
    }

    public function guardarProductosVenta($ventasg, $lista_productosVenta){

        if( count($lista_productosVenta) >= 1 && !empty($lista_productosVenta)){
            try{
                DB::beginTransaction();

                //Creamos instancia para poder ocupar las funciones
                $clsMedMen = new clsProducto();

                //recorremos la lista de productos
                foreach($lista_productosVenta as $param => $paramdata){

                    // calculamos la medida menor
                    $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$paramdata['cantidad']);

                    //Buscamos el producto a actualizar y actualizamos
                    $Producto = Producto::find($paramdata['idProducto']);
                    $stockanterior = $Producto -> existenciaG;
                    //actualizamos la existencia
                    $Producto -> existenciaG = $Producto -> existenciaG - $medidaMenor;
                    $Producto -> save();

                    

                    /**************************** REGISTRA PRODUCTOS VENTAG ***************************************** */
                    
                    Productos_ventasg::insertProductoVentasg($ventasg->idVenta,$paramdata,$medidaMenor);

                    /****************************INGRESA MOVIMIENTO PRODUCTO***************************************** */

                    //obtenemos la existencia del producto actualizado
                    $stockactualizado = $Producto->existenciaG;

                    //insertamos el movimiento de existencia del producto
                    moviproduc::insertMoviproduc(
                        $paramdata,
                        "Alta de venta",
                        $ventasg->idVenta,
                        $medidaMenor,
                        $stockanterior,
                        $stockactualizado,
                        $ventasg->idEmpleado,
                        $_SERVER['REMOTE_ADDR']
                    );
                }

                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'code' =>  200,
                    'status' => 'success',
                    'message' => 'Productos registrados correctamente'
                );

                DB::commit();
            } catch (\Exception $e){
                DB::rollBack();
                // propagamos el er
                throw $e;
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
                if($ventasg->total >= 1000 || count($lista_productosVenta) > 7){
                    $data['ticket'] = $this-> generaTicketPeque($ventasg->idVenta);
                } else{
                    if($ventasg->idTipoVenta == 3){
                        $data['ticket'] = $this-> generaTicket(4,$ventasg->idVenta);
                    } else{
                        $data['ticket'] = $this-> generaTicket(3,$ventasg->idVenta);
                    }
                }
            } elseif($ventasg->idTipoVenta == 4 || $ventasg->idTipoVenta == 5 || $ventasg->idTipoVenta == 6 || $ventasg->idTipoVenta == 8){
                $data['ticket'] = $this-> generaTicketPeque($ventasg->idVenta);
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
                // if($params_array['ventasg']['idStatus'] == 16){ //de momento se omite hasta revisar los status
                    try{
                        DB::beginTransaction();
    
                        //actualizamos valores de venta
                        $ventag = Ventasg::findOrFail($idVenta);
                        $ventag->idCliente = $params_array['ventasg']['idCliente'];
                        $ventag->idTipoVenta = $params_array['ventasg']['idTipoVenta'];
                        $ventag->observaciones = $params_array['ventasg']['observaciones'];
                        $ventag->subtotal = $params_array['ventasg']['subtotal'];
                        $ventag->descuento = $params_array['ventasg']['descuento'];
                        $ventag->total = $params_array['ventasg']['total'];
                        /**
                         * PROXIMO A REVISAR
                         * Nota: Para entregas  revisar si la venta se cambia a no se envia revisar
                         * si el status se pueda cambiar si es que ya cuenta con un envio parcial o un envio total
                         * 
                         */
                            $ventag->cdireccion = $params_array['ventasg']['cdireccion'];
                            if(strlen($ventag->cdireccion) > 10){
                                $ventag->idStatusEntregas = 7;
                            }
                        /** */
                        $ventag->updated_at = Carbon::now();
                        $ventag->save();
                        

                        //obtenemos ip
                        $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                    
                        //insertamos el movimiento realizado
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                        $monitoreo -> accion =  "Modificacion de venta";
                        $monitoreo -> folioNuevo =  $idVenta;
                        $monitoreo -> pc =  $ip;
                        $monitoreo -> motivo =  $params_array['motivo_edicion'];
                        $monitoreo->created_at = Carbon::now();
                        $monitoreo->updated_at = Carbon::now();
                        $monitoreo ->save();

                        /*** INICIO INSERCION DE PRODUCTOS */
                        
                        $dataProductos = $this->updateProductosVenta($params_array['ventasg'], $params_array['lista_productoVentag']);


                        /*** FIN INSERCION DE PRODUCTOS */
                        
                        $data = [
                            'code' => 200,
                            'status' => 'success',
                            'message' => 'Venta #'.$idVenta.' modificada exitosamente',
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
            }
        } else{
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Los valores ingresado no se recibieron correctamente'
            );
        }
        if($data['status'] == 'success'){
            // var_dump($params_array);
            // die();
            if($ventag->idTipoVenta == 1 || $ventag->idTipoVenta == 2 || $ventag->idTipoVenta == 3){
                if($ventag->total >= 1000 || count($params_array['lista_productoVentag']) > 7){
                    $this-> generaTicketPeque($ventag->idVenta);
                } else{
                    if($ventag->idTipoVenta == 3){
                        $this-> generaTicket(4,$ventag->idVenta);
                    } else{
                        $this-> generaTicket(3,$ventag->idVenta);
                    }
                }
            } elseif($ventag->idTipoVenta == 4 || $ventag->idTipoVenta == 5 || $ventag->idTipoVenta == 6){
                $this-> generaTicketPeque($ventag->idVenta);
            }
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
                $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);

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
                    $moviproduc -> created_at = Carbon::now();
                    $moviproduc -> updated_at = Carbon::now();
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
                    $producto_ventasg -> created_at = Carbon::now();
                    $producto_ventasg -> updated_at = Carbon::now();

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
                    $moviproduc -> created_at = Carbon::now();
                    $moviproduc -> updated_at = Carbon::now();
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

    public function cancelaVenta($idVenta, Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        // echo 'vardmp <br>';
        // var_dump($params_array['identity']['sub']);
        // die();
        if(!empty($params_array)){
            if($params_array['identity']['permisos']['cancelar'] == 1){
                try{
                    DB::beginTransaction();

                    //pasos productos
                    $dataProductos = $this->guardaProductosVentaCan($idVenta,$params_array['identity']['sub']);

                    //consultar venta
                    $venta = Ventasg::where('idVenta',$idVenta)->first();

                    //Eliminar venta
                    Ventasg::where('idVenta',$idVenta)->delete();

                    //insertar venta en ventas canceladas
                    $ventascan = new Ventascan();
                    $ventascan->idVenta = $venta->idVenta;
                    $ventascan->idCliente = $venta->idCliente;
                    $ventascan->cdireccion = $venta->cdireccion;
                    $ventascan->idTipoVenta = $venta->idTipoVenta;
                    // $ventascan->idTipoPago = $venta->idStatus;
                    $ventascan->observaciones = $venta->observaciones;
                    $ventascan->idStatusCaja = $venta->idStatusCaja;
                    $ventascan->idStatusEntregas = $venta->idStatusEntregas;
                    $ventascan->fecha = $venta->created_at;
                    $ventascan->idEmpleadoG = $venta->idEmpleado;//Empleado que genero la venta
                    $ventascan->idEmpleadoC = $params_array['identity']['sub'];//idEmpleado que cancelo la venta
                    $ventascan->subtotal = $venta->subtotal;
                    $ventascan->descuento = $venta->descuento;
                    $ventascan->total = $venta->total;
                    $ventascan->created_at = Carbon::now();
                    $ventascan->updated_at = Carbon::now();
                    $ventascan->save();


                    

                    //insertar monitoreo
                    //obtenemos ip
                    $ip = $_SERVER['REMOTE_ADDR'];

                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                    $monitoreo -> accion =  "Cancelacion de venta";
                    $monitoreo -> folioNuevo =  $idVenta;
                    $monitoreo -> pc =  $ip;
                    $monitoreo -> motivo =  $params_array['motivo_cancelacion'];
                    $monitoreo ->save();

                    //Verificamos si la nota tiene abonos
                    $tieneAbono = Abono_venta::where('idVenta',$idVenta)->get();
                    
                    //Si nos regresa registros
                    if(count($tieneAbono) > 0){

                        //sumamos todos los abonos realziados
                        $totalAbono = $tieneAbono->sum('abono');
                        // Buscamos al cliente y actualizamos el cambpo en donde se sumara el saldo abonado
                        $cliente = Cliente::where('idCliente',$venta->idCliente)->first();
                        $cliente->Saldo_SanOtilio = $cliente->Saldo_SanOtilio + $totalAbono;
                        $cliente->save();
                    }

                    //data
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Venta cancelada correctamente',
                        'data_productos' => $dataProductos
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
            } else{
                $data = array(
                    'code'      =>  400,
                    'status'    =>  'error',
                    'message'   =>  'El usuario no cuenta con los permisos para cancelar la venta.'
                );
            }
        } else{
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Los valores ingresados no se recibieron correctamente'
            );
        }

        return response()->json($data, $data['code']);
    }

    public function guardaProductosVentaCan($idVenta, $idEmpleado){
        if(!empty($idVenta) || !empty($idEmpleado)){
            try{
                DB::beginTransaction();

                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //Consultamos productos a eliminar
                $lista_prodVen_ant = Productos_ventasg::where('idVenta',$idVenta)->get();

                //Insertamos productos que le pertenecian a la venta que se elimino
                foreach($lista_prodVen_ant as $param => $paramdata){

                    //Consultamos la existencia antes de actualizar
                    $Producto = Producto::find($paramdata['idProducto']);
                    $stockAnterior = $Producto -> existenciaG;

                    //Actualizamos existencia
                    $Producto -> existenciaG = $Producto -> existenciaG + $paramdata['igualMedidaMenor'];
                    $Producto -> save();

                    //Consultamos la existencia despues de actualizar
                    $stockActualizado = $Producto->existenciaG;

                    //insertamos en productos ventas canceladas
                    $productoCan = new Productos_ventascan();
                    $productoCan->idVenta = $paramdata['idVenta'];
                    $productoCan->idProducto = $paramdata['idProducto'];
                    $productoCan->descripcion = $paramdata['descripcion'];
                    $productoCan->idProdMedida = $paramdata['idProdMedida'];
                    $productoCan->cantidad = $paramdata['cantidad'];
                    $productoCan->precio = $paramdata['precio'];
                    $productoCan->descuento = $paramdata['descuento'];
                    $productoCan->total = $paramdata['total'];
                    $productoCan->igualMedidaMenor = $paramdata['igualMedidaMenor'];
                    $productoCan->created_at = Carbon::now();
                    $productoCan->updated_at = Carbon::now();
                    $productoCan->save();

                    //insertamos el movimiento de existencia que se le realizo al producto
                    $moviproduc = new moviproduc();
                    $moviproduc -> idProducto =  $paramdata['idProducto'];
                    $moviproduc -> claveEx =  $Producto->claveEx;
                    $moviproduc -> accion =  "Cancelacion de venta, se suma al inventario";
                    $moviproduc -> folioAccion =  $paramdata['idVenta'];
                    $moviproduc -> cantidad =  $paramdata['igualMedidaMenor'];
                    $moviproduc -> stockanterior =  $stockAnterior;
                    $moviproduc -> stockactualizado =  $stockActualizado;
                    $moviproduc -> idUsuario =  $idEmpleado;
                    $moviproduc -> pc =  $ip;
                    $moviproduc -> created_at = Carbon::now();
                    $moviproduc -> updated_at = Carbon::now();
                    $moviproduc ->save();
                }

                //eliminamos los registros que tenga esa venta
                Productos_ventasg::where('idVenta',$idVenta)->delete();

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Productos registrados correctamente en ventas canceladas.'
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

    public function generaTicket($NoImpre,$idVenta){
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
                        ->where('idVenta',$idVenta)
                        ->first();
            //informacion de la venta
            $productos_ventasg = Productos_ventasg::where('idVenta',$ventasg->idVenta)
                                 ->join('producto','producto.idProducto','=','productos_ventasg.idProducto')
                                 ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ventasg.idProdMedida')
                                 ->select('productos_ventasg.*','producto.claveEx as claveEx','historialproductos_medidas.nombreMedida')
                                 ->get();

            //obtenemos direccion ip
            $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);
            // $ip = $_SERVER['REMOTE_ADDR'];
            // dd($ip);

            // $datos_imp = Impresoras::where('pcVentas','=',$ip)
            $datos_imp = Impresoras::where('ipVentas','=',$ip)
                            ->latest('idImpresora')
                            ->first();
            // dd($ip, $datos_imp);
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
                                        str_pad(number_format($paramdata['precio'],2),6," ",STR_PAD_BOTH)."/"."$".
                                        str_pad(number_format($paramdata['descuento'],2),3," ",STR_PAD_BOTH)."/"."$".
                                        str_pad(number_format($paramdata['total'],2),6," ",STR_PAD_BOTH)."\n");
                        
                        $impresora->text("- - - - - - - - - - - - - - - - - - - - \n");
                    }
                    /***** INFORMACION DE LA VENTA 2DA PARTE *****/
                    //$impresora->text("---------------------------------------- \n");
                    $impresora->text("SUBTOTAL:".str_pad("$".number_format($ventasg->subtotal,2),30," ",STR_PAD_LEFT)."\n");
                    $impresora->text("DESCUENTO:".str_pad("$".number_format($ventasg->descuento,2),29," ",STR_PAD_LEFT)."\n");
                    $impresora->setJustification(Printer::JUSTIFY_RIGHT);
                    $impresora->text("                   ---------- \n");
                    $impresora->setJustification(Printer::JUSTIFY_LEFT);
                    $impresora->text("TOTAL:".str_pad("$".number_format($ventasg->total,2),33," ",STR_PAD_LEFT)."\n");
                    $impresora->text("----------------------------------------\n");
                    if($ventasg->idStatusEntregas != 6){
                        $impresora->text("DIRECCION: \n");
                        $impresora->text($ventasg->cdireccion."\n\n");
                    }
                    $impresora->text("OBSERVACIONES: \n");
                    $impresora->text($ventasg->observaciones."\n");
                    $impresora->text("========================================\n");
                    $impresora->text("* TODO CAMBIO CAUSARA UN 10% EN EL IMPORTE TOTAL *"."\n");
                    $impresora->text("* TODA CANCELACION SE COBRARA 20% DEL IMPORTE TOTAL SIN EXCEPCION *"."\n");
                    $impresora->text("\n\n\n\n\n\n\n\n");

                    $impresora->cut();
                    $impresora->close();
                    /************** */
                }

                return response()->json([
                    'code'      =>  200,
                    'status'    => 'success',
                    'message'   => 'Ticket generado correctamente'
                ]);
            } else{
                return response()->json([
                    'code'      =>  400,
                    'status'    => 'error',
                    'message'   => 'La ip no esta registrada',
                    'ip'        => $ip
                ]);
            }
            
    }

    public function generaTicketPeque($idVenta){

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
                    ->where('idVenta',$idVenta)
                    ->first();

        //obtenemos direccion ip
        // $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);        
        $ip = $_SERVER['REMOTE_ADDR'];        
        
        //Informacion de impresoras
        // $datos_imp = Impresoras::where('pcVentas','=',$ip)
        $datos_imp = Impresoras::where('ipVentas','=',$ip)
                            ->latest('idImpresora')
                            ->first();
        // dd($datos_imp);
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
            $impresora->text("Subtotal:".str_pad("$".number_format($ventasg->subtotal,2),30," ",STR_PAD_LEFT)."\n");
            $impresora->text("Descuento:".str_pad("$".number_format($ventasg->descuento,2),29," ",STR_PAD_LEFT)."\n");
            $impresora->setJustification(Printer::JUSTIFY_RIGHT);
            $impresora->text("                   ---------- \n");
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            $impresora->text("Total:".str_pad("$".number_format($ventasg->total,2),33," ",STR_PAD_LEFT)."\n");
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
                'message'   => 'La ip no esta registrada',
                'ip'        => $ip
            ]);
        }
    }

    function query_ventasg_index(){
        return Ventasg::join('cliente', 'cliente.idcliente', '=', 'ventasg.idcliente')
        ->join('empleado', 'empleado.idEmpleado', '=', 'ventasg.idEmpleado')
        ->join('tiposdeventas', 'tiposdeventas.idTipoVenta', '=', 'ventasg.idTipoVenta')
        ->select(
            'ventasg.idVenta',
            'ventasg.idCliente',
            'ventasg.cdireccion',
            'ventasg.idTipoVenta',
            'ventasg.observaciones',
            'ventasg.idStatusCaja',
            'ventasg.idStatusEntregas',
            'ventasg.idEmpleado',
            'ventasg.subtotal',
            'ventasg.descuento',
            'ventasg.total',
            'ventasg.idVentaCorre',
            'ventasg.created_at',
            'ventasg.updated_at',
            DB::raw("CONCAT(cliente.nombre, ' ', cliente.aPaterno, ' ', cliente.aMaterno) as nombreCliente"),
            DB::raw("CONCAT(empleado.nombre, ' ', empleado.aPaterno, ' ', empleado.aMaterno) as nombreEmpleado"),
            'tiposdeventas.nombre as nombreTipoventa',
            DB::raw("false as isCredito")
        );
    }
    function query_ventascre_index(){
        return Ventascre::join('cliente', 'cliente.idcliente', '=', 'ventascre.idcliente')
                        ->join('empleado', 'empleado.idEmpleado', '=', 'ventascre.idEmpleadoG')
                        ->join('tiposdeventas', 'tiposdeventas.idTipoVenta', '=', 'ventascre.idTipoVenta')
                        ->select(
                            'ventascre.idVenta',
                            'ventascre.idCliente',
                            'ventascre.cdireccion',
                            'ventascre.idTipoVenta',
                            'ventascre.observaciones',
                            'ventascre.idStatusCaja',
                            'ventascre.idStatusEntregas',
                            'ventascre.idEmpleadoG AS idEmpleado',
                            'ventascre.subtotal',
                            'ventascre.descuento',
                            'ventascre.total',
                            DB::raw("null as idVentaCorre"),
                            'ventascre.created_at',
                            'ventascre.updated_at',
                            DB::raw("CONCAT(cliente.nombre, ' ', cliente.aPaterno, ' ', cliente.aMaterno) as nombreCliente"),
                            DB::raw("CONCAT(empleado.nombre, ' ', empleado.aPaterno, ' ', empleado.aMaterno) as nombreEmpleado"),
                            'tiposdeventas.nombre as nombreTipoventa',
                            DB::raw("true as isCredito")
                        );
    }

    function query_ventas_corre_index(){
        return Ventas_corre::join('cliente', 'cliente.idcliente', '=', 'ventas_corre.idcliente')
                        ->join('empleado', 'empleado.idEmpleado', '=', 'ventas_corre.idEmpleado')
                        ->join('tiposdeventas', 'tiposdeventas.idTipoVenta', '=', 'ventas_corre.idTipoVenta')
                        ->select(
                            'ventas_corre.idVenta',
                            'ventas_corre.idCliente',
                            'ventas_corre.cdireccion',
                            'ventas_corre.idTipoVenta',
                            'ventas_corre.observaciones',
                            'ventas_corre.idStatusCaja',
                            'ventas_corre.idStatusEntregas',
                            'ventas_corre.idEmpleado',
                            'ventas_corre.subtotal',
                            'ventas_corre.descuento',
                            'ventas_corre.total',
                            'ventas_corre.idVentaCorre',
                            'ventas_corre.created_at',
                            'ventas_corre.updated_at',
                            DB::raw("CONCAT(cliente.nombre, ' ', cliente.aPaterno, ' ', cliente.aMaterno) as nombreCliente"),
                            DB::raw("CONCAT(empleado.nombre, ' ', empleado.aPaterno, ' ', empleado.aMaterno) as nombreEmpleado"),
                            'tiposdeventas.nombre as nombreTipoventa',
                            DB::raw("false as isCredito")
                        );
    }

    //VENTA CORRE A CUENTA
    public function guardaVentaCorre($ventag, $lista_productosVenta){
        try{
            DB::beginTransaction();

            //Cremoas venta de tipo corre a cuenta principal
            $venta_corre = new Ventas_corre();
            $venta_corre->idVenta = $ventag->idVenta;
            $venta_corre->idCliente = $ventag->idCliente;
            $venta_corre->cdireccion = $ventag->cdireccion;
            $venta_corre->idTipoVenta = $ventag->idTipoVenta;
            $venta_corre->observaciones = $ventag->observaciones;
            $venta_corre->idStatusCaja = $ventag->idStatusCaja;
            $venta_corre->idStatusEntregas = $ventag->idStatusEntregas;
            $venta_corre->idEmpleado = $ventag->idEmpleado;
            $venta_corre->subtotal = $ventag->subtotal;
            $venta_corre->descuento = $ventag->descuento;
            $venta_corre->total = $ventag->total;
            $venta_corre->created_at = $ventag->created_at;
            $venta_corre->updated_at = $ventag->updated_at;
            $venta_corre->save();

            //Creamos instancia para poder ocupar las funciones
            $clsMedMen = new clsProducto();

            // recorremos la lista de productos
            foreach($lista_productosVenta as $param => $paramdata){

                $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$paramdata['cantidad']);

                $producto_ventas_corre = new Productos_ventas_corre();
                $producto_ventas_corre->idVentaCorre = $venta_corre->idVentaCorre;
                $producto_ventas_corre->idVentag = $ventag->idVenta;
                $producto_ventas_corre->idProducto = $paramdata['idProducto'];
                $producto_ventas_corre->descripcion = $paramdata['descripcion'];
                $producto_ventas_corre->idProdMedida = $paramdata['idProdMedida'];
                $producto_ventas_corre->cantidad = $paramdata['cantidad'];
                $producto_ventas_corre->precio = $paramdata['precio'];
                $producto_ventas_corre->descuento = $paramdata['descuento'];
                $producto_ventas_corre->total = $paramdata['total'];
                $producto_ventas_corre->igualMedidaMenor = $medidaMenor;
                $producto_ventas_corre->created_at = Carbon::now();
                $producto_ventas_corre->updated_at = Carbon::now();
                $producto_ventas_corre-> save();
            }

            $data['ticket'] = $this-> generaTicketPeque($ventag->idVenta);

            //Eliminamos venta de VENTAG
            Ventasg::where('idVenta',$ventag->idVenta)->delete();

            //Si todo es correcto mandamos el ultimo producto insertado
            $data =  array(
                'code' =>  200,
                'status' => 'success',
                'message' => 'Venta y productos registrados correctamente en Corre a cuenta',
            );
            
            DB::commit();

        } catch (\Exception $e){
            DB::rollBack();

            //Si el array esta vacio o mal echo mandamos mensaje de error
            $data =  array(
                'status'        => 'error',
                'code'          =>  404,
                'message'       =>  'ocurrio un error',
                'error'         => $e,
            );
            // propagamos el error
            throw $e;
        }

        return $data;
    }

    public function getDetallesVentaCorreAcuenta($idVenta){
        $venta = Ventas_corre::join('cliente','cliente.idcliente','=','ventas_corre.idcliente')
        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
        ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventas_corre.idTipoVenta')
        ->leftjoin('statuss','statuss.idStatus','=','ventas_corre.idStatusCaja')
        ->leftjoin('statuss as statuss2','statuss2.idStatus','=','ventas_corre.idStatusEntregas')
        ->join('empleado','empleado.idEmpleado','=','ventas_corre.idEmpleado')
        ->select('ventas_corre.*',
                        'tiposdeventas.nombre as nombreTipoVenta',
                        'statuss.nombre as nombreStatus',
                        'statuss2.nombre as nombreStatusEntregas',
                        DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                ->where('ventas_corre.idVenta','=',$idVenta)
                ->first();
        $productosVenta = Productos_ventas_corre::
                join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ventas_corre.idProdMedida')
                ->join('producto','producto.idProducto','=','productos_ventas_corre.idProducto')
                ->select('productos_ventas_corre.*',
                        'productos_ventas_corre.total as subtotal',
                        'historialproductos_medidas.nombreMedida',
                        'producto.claveEx as claveEx')
                ->where('productos_ventas_corre.idVentag','=',$idVenta)
                ->get();
        if(is_object($venta)){
            //Agregamos propiedad de que es acredito
            $venta->isCredito = false;
        
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'venta_correAcuenta'   =>  $venta,
                'productos_ventas_correAcuenta'     => $productosVenta
            ];
        }else{
            $data = [
                'code'          => 404,
                'status'        => 'error',
                'message'       => 'La venta no existe.'
            ];
        }
        return response()->json($data, $data['code']);
    }

    public function getVentaCorreAcuentaActual($idCliente){
        $venta = Ventas_corre::join('cliente','cliente.idcliente','=','ventas_corre.idcliente')
        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
        ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventas_corre.idTipoVenta')
        ->leftjoin('statuss','statuss.idStatus','=','ventas_corre.idStatusCaja')
        ->leftjoin('statuss as statuss2','statuss2.idStatus','=','ventas_corre.idStatusEntregas')
        ->join('empleado','empleado.idEmpleado','=','ventas_corre.idEmpleado')
        ->select('ventas_corre.*',
                        'tiposdeventas.nombre as nombreTipoVenta',
                        'statuss.nombre as nombreStatus',
                        'statuss2.nombre as nombreStatusEntregas',
                        DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                        ->where('ventas_corre.idCliente', '=', $idCliente)
                        ->where('ventas_corre.idStatusCaja', '=', 3)
                        ->where('ventas_corre.idTipoVenta', '=', 7)
                        ->where('ventas_corre.idStatusEntregas', '=', 6)
                        ->first();
        // $productosVenta = Productos_ventas_corre::
        //         join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ventas_corre.idProdMedida')
        //         ->join('producto','producto.idProducto','=','productos_ventas_corre.idProducto')
        //         ->select('productos_ventas_corre.*',
        //                 'productos_ventas_corre.total as subtotal',
        //                 'historialproductos_medidas.nombreMedida',
        //                 'producto.claveEx as claveEx')
        //         ->where('productos_ventas_corre.idVentag','=',$idVenta)
        //         ->get();
        $productosVenta = [];
        if(is_object($venta)){
            //Agregamos propiedad de que es acredito
            $venta->isCredito = false;
        
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'venta_correAcuenta'   =>  $venta,
                'productos_ventas_correAcuenta'     => $productosVenta
            ];
        }else{
            $data = [
                'code'          => 404,
                'status'        => 'error',
                'message'       => 'La venta no existe.'
            ];
        }
        return response()->json($data, $data['code']);
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

    //Ventas canceladas
    public function indexVentasCanceladas($type, $search){
        $ventas_canceladas = Ventascan::join('cliente','cliente.idcliente','=','ventascan.idcliente')
                            ->join('empleado','empleado.idEmpleado','=','ventascan.idEmpleadoG')
                            ->join('empleado as empleado2','empleado2.idEmpleado','=','ventascan.idEmpleadoC')
                            ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventascan.idTipoVenta')
                            ->select('ventascan.*',
                                    DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),
                                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleadoGenera"),
                                    DB::raw("CONCAT(empleado2.nombre,' ',empleado2.aPaterno,' ',empleado2.aMaterno) as nombreEmpleadoCancela"),
                                    'tiposdeventas.nombre as nombreTipoventa');
                            //Folio
                            if($type == 1 && $search != "null"){
                                $ventas_canceladas->where('ventascan.idVenta','like','%'.$search.'%');
                            }
                            // Cliente
                            if($type == 2 && $search != "null") {
                                $ventas_canceladas->where(DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno)"), 'like', '%' . $search . '%');
                            }
                            // empleadoGenera
                            if($type == 3 && $search != "null") {
                                $ventas_canceladas->where(DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno)"), 'like', '%' . $search . '%');
                            }
                            // empleadoCancela
                            if($type == 4 && $search != "null") {
                                $ventas_canceladas->where(DB::raw("CONCAT(empleado2.nombre,' ',empleado2.aPaterno,' ',empleado2.aMaterno)"), 'like', '%' . $search . '%');
                            }
                            
        $ventas_canceladas = $ventas_canceladas ->orderBy('ventascan.idVenta','desc')
                            ->paginate(5);
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'ventas_canceladas'    => $ventas_canceladas
        ]);
    }

    public function getDetallesVentaCancelada($idVenta){
        $venta = Ventascan::join('cliente','cliente.idcliente','=','ventascan.idcliente')
                ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
                ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventascan.idTipoVenta')
                ->leftjoin('statuss','statuss.idStatus','=','ventascan.idStatusCaja')
                ->leftjoin('statuss as statuss2','statuss2.idStatus','=','ventascan.idStatusEntregas')
                ->join('empleado','empleado.idEmpleado','=','ventascan.idEmpleadoG')
                ->select('ventascan.*',
                        'tiposdeventas.nombre as nombreTipoVenta',
                        'statuss.nombre as nombreStatus',
                        'statuss2.nombre as nombreStatusEntregas',
                        DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                ->where('ventascan.idVenta','=',$idVenta)
                ->get();
        $productosVenta = DB::table('productos_ventascan')
                ->join('producto','producto.idProducto','=','productos_ventascan.idProducto')
                ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ventascan.idProdMedida')
                ->select('productos_ventascan.*','productos_ventascan.total as subtotal','producto.claveEx as claveEx','historialproductos_medidas.nombreMedida')
                ->where('productos_ventascan.idVenta','=',$idVenta)
                ->get();
        if(is_object($venta)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'venta_cancelada'   =>  $venta,
                'productos_ventascan'     => $productosVenta
            ];
        }else{
            $data = [
                'code'          => 404,
                'status'        => 'error',
                'message'       => 'La venta no existe.'
            ];
        }
        return response()->json($data, $data['code']);
    }

    //ventas finalizadas
    public function indexVentasFinalizadas($type, $search){
        $ventas_finalizadas = Ventasf::join('cliente','cliente.idcliente','=','ventasf.idcliente')
                            ->join('empleado','empleado.idEmpleado','=','ventasf.idEmpleado')
                            ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventasf.idTipoVenta')
                            ->select('ventasf.*',
                                    DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),
                                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleadoGenera"),
                                    'tiposdeventas.nombre as nombreTipoventa');
                            //Folio
                            if($type == 1 && $search != "null"){
                                $ventas_finalizadas->where('ventasf.idVenta','like','%'.$search.'%');
                            }
                            // Cliente
                            if($type == 2 && $search != "null") {
                                $ventas_finalizadas->where(DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno)"), 'like', '%' . $search . '%');
                            }
                            // empleadoGenera
                            if($type == 3 && $search != "null") {
                                $ventas_finalizadas->where(DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno)"), 'like', '%' . $search . '%');
                            }
                            // empleadoCancela
                            if($type == 4 && $search != "null") {
                                $ventas_finalizadas->where(DB::raw("CONCAT(empleado2.nombre,' ',empleado2.aPaterno,' ',empleado2.aMaterno)"), 'like', '%' . $search . '%');
                            }
                            
        $ventas_finalizadas = $ventas_finalizadas ->orderBy('ventasf.idVenta','desc')
                            ->paginate(5);
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'ventas_finalizadas'    => $ventas_finalizadas
        ]);
    }

    public function getDetallesVentaFinalizada($idVenta){
        $venta = Ventasf::join('cliente','cliente.idcliente','=','Ventasf.idcliente')
                ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
                ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','Ventasf.idTipoVenta')
                ->leftjoin('statuss','statuss.idStatus','=','Ventasf.idStatusCaja')
                ->leftjoin('statuss as statuss2','statuss2.idStatus','=','Ventasf.idStatusEntregas')
                ->join('empleado','empleado.idEmpleado','=','Ventasf.idEmpleado')
                ->select('Ventasf.*',
                        'tiposdeventas.nombre as nombreTipoVenta',
                        'statuss.nombre as nombreStatus',
                        'statuss2.nombre as nombreStatusEntregas',
                        DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                ->where('Ventasf.idVenta','=',$idVenta)
                ->get();
        $productosVenta = DB::table('Productos_ventasf')
                ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','Productos_ventasf.idProdMedida')
                ->select('Productos_ventasf.*','Productos_ventasf.total as subtotal','historialproductos_medidas.nombreMedida')
                ->where('Productos_ventasf.idVenta','=',$idVenta)
                ->get();
        if(is_object($venta)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'venta_finalizada'   =>  $venta,
                'productos_ventasf'     => $productosVenta
            ];
        }else{
            $data = [
                'code'          => 404,
                'status'        => 'error',
                'message'       => 'La venta no existe.'
            ];
        }
        return response()->json($data, $data['code']);
    }

    //ventas credito
    public function indexVentasCredito($type, $search){
        $ventas_credito = Ventascre::join('cliente','cliente.idcliente','=','ventascre.idcliente')
                            ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventascre.idTipoVenta')
                            ->leftjoin('statuss','statuss.idStatus','=','ventascre.idStatusCaja')
                            ->leftjoin('statuss as statuss2','statuss2.idStatus','=','ventascre.idStatusEntregas')
                            ->join('empleado','empleado.idEmpleado','=','ventascre.idEmpleadoG')
                            // ->leftJoin('empleado as empleadoC','empleadoC.idEmpleado','=','ventascre.idEmpleadoC')
                            // ->leftJoin('empleado as empleadoF','empleadoF.idEmpleado','=','ventascre.idEmpleadoF')
                            ->select('ventascre.*',
                                    'tiposdeventas.nombre as nombreTipoventa',
                                    'statuss.nombre as nombreStatus',
                                    'statuss2.nombre as nombreStatusEntregas',
                                    DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),
                                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleadoGenera"),
                                    // DB::raw("CONCAT(empleadoC.nombre,' ',empleadoC.aPaterno,' ',empleadoC.aMaterno) as nombreEmpleadoCredito"),
                                    // DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleadoGenera"),
                                );
                            //Folio
                            if($type == 1 && $search != "null"){
                                $ventas_credito->where('ventascre.idVenta','like','%'.$search.'%');
                            }
                            // Cliente
                            if($type == 2 && $search != "null") {
                                $ventas_credito->where(DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno)"), 'like', '%' . $search . '%');
                            }
                            // empleadoGenera
                            if($type == 3 && $search != "null") {
                                $ventas_credito->where(DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno)"), 'like', '%' . $search . '%');
                            }
                            
        $ventas_credito = $ventas_credito ->orderBy('ventascre.idVenta','desc')
                            ->paginate(5);
        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'ventas_credito'    => $ventas_credito
        ]);
    }

    public function getDetallesVentaCredito($idVenta){
        $venta = Ventascre::join('cliente','cliente.idcliente','=','ventascre.idcliente')
                ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
                ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventascre.idTipoVenta')
                ->leftjoin('statuss','statuss.idStatus','=','ventascre.idStatusCaja')
                ->leftjoin('statuss as statuss2','statuss2.idStatus','=','ventascre.idStatusEntregas')
                ->join('empleado','empleado.idEmpleado','=','ventascre.idEmpleadoG')
                ->select('ventascre.*',
                        'tiposdeventas.nombre as nombreTipoVenta',
                        'statuss.nombre as nombreStatus',
                        'statuss2.nombre as nombreStatusEntregas',
                        DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                ->where('ventascre.idVenta','=',$idVenta)
                ->first();
        $productosVenta = Productos_ventascre::
                join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','Productos_ventascre.idProdMedida')
                ->join('producto','producto.idProducto','=','Productos_ventascre.idProducto')
                ->select('Productos_ventascre.*',
                        'Productos_ventascre.total as subtotal',
                        'historialproductos_medidas.nombreMedida',
                        'producto.claveEx as claveEx')
                ->where('Productos_ventascre.idVenta','=',$idVenta)
                ->get();
        if(is_object($venta)){
            //Agregamos propiedad de que es acredito
            $venta->isCredito = true;

            $data = [
                'code'          => 200,
                'status'        => 'success',
                'venta_credito'   =>  $venta,
                'productos_ventascre'     => $productosVenta
            ];
        }else{
            $data = [
                'code'          => 404,
                'status'        => 'error',
                'message'       => 'La venta no existe.'
            ];
        }
        return response()->json($data, $data['code']);
    }
}
