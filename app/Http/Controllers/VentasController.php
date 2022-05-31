<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\models\tipo_pago;
use App\models\Cotizacion;
use App\models\Productos_cotizaciones;
use App\models\Ventasg;
use App\models\Productos_ventasg;
use App\models\Empresa;

use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
//use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
//use Mike42\Escpos\PrintConnectors\FilePrintConnector;
//use Mike42\Escpos\CapabilityProfile;

class VentasController extends Controller
{
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
    /****** COTIZACIONES *****/
    public function indexCotiza(){
        $Cotizaciones = DB::table('cotizaciones')
        ->join('cliente','cliente.idCliente','=','cotizaciones.idCliente')
        ->join('empleado','empleado.idEmpleado','=','cotizaciones.idEmpleado')
        ->join('statuss','statuss.idStatus','=','cotizaciones.idStatus')
        ->select('cotizaciones.*',
        DB::raw("CONCAT(cliente.nombre,' ',cliente.Apaterno,' ',cliente.Amaterno) as nombreCliente"),
        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
        'statuss.nombre as nombreStatus')
        ->get();
        return response()->json([
            'code'          => 200,
            'status'        => 'success',
            'Cotizaciones'  => $Cotizaciones
        ]);
    }
    public function guardarCotizacion(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            //eliminamos espacios vacios
            $params_array = array_map('trim',$params_array);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'idCliente'       => 'required',
                'idEmpleado'      => 'required',//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
                'idStatus'   => 'required',
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
                $cotizacion = new Cotizacion();
                $cotizacion->idCliente = $params_array['idCliente'];
                if(isset($params_array['dirCliente'])){
                    $cotizacion->cdireccion = $params_array['dirCliente'];
                }
                $cotizacion->idEmpleado = $params_array['idEmpleado'];
                $cotizacion->idStatus = $params_array['idStatus'];
                if(isset($params_array['observaciones'])){
                    $cotizacion->observaciones = $params_array['observaciones'];
                }
                if(isset($params_array['descuento'])){
                    $cotizacion->descuento = $params_array['descuento'];
                }
                $cotizacion->subtotal = $params_array['subtotal'];
                $cotizacion->total = $params_array['total'];

                $cotizacion->save();

                $data = array(
                    'status'    =>  'success',
                    'code'      =>  200,
                    'message'   =>  'Cotizacion creada pero sin productos'
                );
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
    public function guardarProductosCotiza(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){
            //consultamos la ulitma cotizacion que se reazalizo
            $Cotizacion = Cotizacion::latest('idCotiza')->first();
            //recoremos la lista de productos mandada
            foreach($params_array as $param => $paramdata){
                $productos_cotizacion = new Productos_cotizaciones();
                $productos_cotizacion->idCotiza = $Cotizacion->idCotiza;
                $productos_cotizacion->idProducto = $paramdata['idProducto'];
                //$productos_cotizacion->descripcion = $paramdata['descripcion'];
                $productos_cotizacion->precio = $paramdata['precio'];
                $productos_cotizacion->cantidad = $paramdata['cantidad'];
                if(isset($paramdata['descuento'])){
                    $productos_cotizacion->descuento = $paramdata['descuento'];
                }
                $productos_cotizacion->subtotal = $paramdata['subtotal'];
                //guardamos el producto
                $productos_cotizacion->save();
                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'status'        => 'success',
                    'code'          =>  200,
                    'Productos_cotizacion'       =>  $productos_cotizacion
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
    public function consultaUltimaCotiza(){
        $Cotiza = Cotizacion::latest('idCotiza')->first();
        return response()->json([
            'code'          => 200,
            'status'        => 'success',
            'Cotizacion'    => $Cotiza
        ]);
    }
    public function detallesCotizacion($idCotiza){
        $Cotiza = DB::table('cotizaciones')
        ->join('cliente','cliente.idCliente','=','cotizaciones.idCliente')
        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
        ->join('empleado','empleado.idEmpleado','=','cotizaciones.idEmpleado')
        ->join('statuss','statuss.idStatus','=','cotizaciones.idStatus')
        ->select('cotizaciones.*',
        DB::raw("CONCAT(cliente.nombre,' ',cliente.Apaterno,' ',cliente.Amaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        ->where('cotizaciones.idCotiza','=',$idCotiza)
        ->get();
        $productosCotiza = DB::table('productos_cotizaciones')
        ->join('producto','producto.idProducto','=','productos_cotizaciones.idProducto')
        ->join('medidas','medidas.idMedida','=','producto.idMedida')
        ->select('productos_cotizaciones.*','producto.claveEx as claveEx','producto.descripcion as descripcion','medidas.nombre as nombreMedida')
        ->where('productos_cotizaciones.idCotiza','=',$idCotiza)
        ->get();
        if(is_object($Cotiza)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'Cotizacion'   =>  $Cotiza,
                'productos_cotiza'     => $productosCotiza
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
    public function actualizaCotizacion($idCotiza, Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        if(!empty($params_array)){
            //eliminar espacios vacios
            $params_array = array_map('trim', $params_array);
            //quitamos lo que no queremos actualizar o no son necesarios
            unset($params_array['idCotiza']);
            unset($params_array['idVenta']);
            unset($params_array['fecha']);
            unset($params_array['idTipoVenta']);
            unset($params_array['nombreCliente']);
            unset($params_array['created_at']);
            //actualizamos
            $Cotizacion = Cotizacion ::where('idCotiza',$idCotiza)->update($params_array);
                //retornamos la respuesta si esta
                 return response()->json([
                    'status'    =>  'success',
                    'code'      =>  200,
                    'message'   =>  'Cotizacion actualizada'
                 ]);
        }else{
            return response()->json([
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Algo salio mal, favor de revisar'
            ]);
        }
    }
    public function actualizaProductosCotiza($idCotiza, Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){
            //eliminamos los registros que tengab ese idOrd
            Productos_cotizaciones::where('idCotiza',$idCotiza)->delete();
            //recorremos el array para asignar todos los productos
            foreach($params_array as $param => $paramdata){
                $productos_cotizacion = new Productos_cotizaciones();
                $productos_cotizacion->idCotiza = $idCotiza;
                $productos_cotizacion->idProducto = $paramdata['idProducto'];
                //$productos_cotizacion->descripcion = $paramdata['descripcion'];
                $productos_cotizacion->precio = $paramdata['precio'];
                $productos_cotizacion->cantidad = $paramdata['cantidad'];
                if(isset($paramdata['descuento'])){
                    $productos_cotizacion->descuento = $paramdata['descuento'];
                }
                $productos_cotizacion->subtotal = $paramdata['subtotal'];
                //guardamos el producto
                $productos_cotizacion->save();
                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'status'        => 'success',
                    'code'          =>  200,
                    'Productos_cotizacion'       =>  $productos_cotizacion
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
    /***** VENTAS *****/
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
                $ventasg = new Ventasg();
                $ventasg->idCliente = $params_array['idCliente'];
                $ventasg->idTipoVenta = $params_array['idTipoVenta'];
                $ventasg->observaciones = $params_array['observaciones'];
                $ventasg->idStatus = $params_array['idStatus'];
                $ventasg->idEmpleado = $params_array['idEmpleado'];
                $ventasg->subtotal = $params_array['subtotal'];
                if(isset($params_array['descuento'])){
                    $ventasg->descuento = $params_array['descuento'];
                }
                $ventasg->total = $params_array['total'];

                $ventasg->save();

                $data = array(
                    'status'    =>  'success',
                    'code'      =>  200,
                    'message'   =>  'Venta creada pero sin productos'
                );
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
            //consultamos la ultima venta realizada
            $ventasg = Ventasg::latest('idVenta')->first();
            //recorremos la lista de productos
            foreach($params_array as $param => $paramdata){
                $productos_ventasg = new Productos_ventasg();
                $productos_ventasg->idVenta = $ventasg->idVenta;
                $productos_ventasg-> idProducto = $paramdata['idProducto'];
                $productos_ventasg-> descripcion = $paramdata['descripcion'];
                $productos_ventasg-> cantidad = $paramdata['cantidad'];
                $productos_ventasg-> precio = $paramdata['precio'];
                if(isset($paramdata['descuento'])){
                    $productos_ventasg-> descuento = $paramdata['descuento'];
                }
                $productos_ventasg-> total = $paramdata['subtotal'];
                //guardamos el producto
                $productos_ventasg->save();
                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'status'        => 'success',
                    'code'          =>  200,
                    'Productos_ventasg'       =>  $productos_ventasg
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
    public function generaTicket(){
        /************** */
            //$nombreImpresora = "EPSON TM-U220 Receipt";
            //$profile = CapabilityProfile::load("simple");
                                                    //  Usuario,Contraseña,nombremaquina ó ip,nombre de la impresora
            //$connector = new WindowsPrintConnector("smb://ventas03mat/EPSONTMU220B V3");
            //$connector = new FilePrintConnector("//SISTEMAS02/EPSON TM-U220 Receipt");

            /*****traemos informacion de la empresa*****/

            $empresa = Empresa::first();
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
            $productos_ventasg = Productos_ventasg::where('idVenta',$ventasg->idVenta)
                                 ->join('producto','producto.idProducto','=','productos_ventasg.idProducto')
                                 ->select('productos_ventasg.*','producto.claveEx as claveEx')
                                 ->get();
            //declaramos el nombre de la impresora
            $connector = new WindowsPrintConnector("EPSON TM-U220 Receipt");
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
            $impresora->text("======================================== \n");
            /***** INFORMACION DE LA VENTA PRIMERA PARTE*****/
            //ajustamos el texto en el centro
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            //VENTA: 00000
            $impresora->text("VENTA: ".$ventasg->idVenta."   TV: ".$ventasg->nombreVenta. "\n");
            //VENDEDOR: 
            $impresora->text("VENDEDOR: ".$ventasg->nombreEmpleado. "\n");
            //CLIENTE: 
            $impresora->text("CLIENTE: ".$ventasg->nombreCliente. "\n");
            //fecha y hora
            $impresora->text("FECHA: ".$ventasg->created_at. "\n");
            $impresora->text("======================================== \n");
            /***** PRODUCTOS *****/
            $impresora->text("CLAVE- PRECIO- CANTIDAD- DESC.- SUBTOTAL"."\n");
            foreach($productos_ventasg AS $param => $paramdata){
                //
                $impresora->text($paramdata['claveEx']."- ".
                                 $paramdata['precio']."- ".
                                 $paramdata['cantidad']."- ".
                                 $paramdata['descuento']."- ".
                                 $paramdata['total']."\n");
            }
            /***** INFORMACION DE LA VENTA 2DA PARTE *****/
            $impresora->text("---------------------------------------- \n");
            $impresora->text("SUBTOTAL:");
            $impresora->setJustification(Printer::JUSTIFY_RIGHT);
            $impresora->text("$".$ventasg->subtotal."\n");
            //$impresora->setJustification(Printer::JUSTIFY_LEFT);
            $impresora->text("DESCUENTO: ");
            //$impresora->setJustification(Printer::JUSTIFY_RIGHT);
            $impresora->text("$".$ventasg->descuento."\n");
            //$impresora->setJustification(Printer::JUSTIFY_LEFT);
            $impresora->text("TOTAL: ");
            //$impresora->setJustification(Printer::JUSTIFY_RIGHT);
            $impresora->text("---------- \n");
            $impresora->text("$".$ventasg->total."\n");
            $impresora->text("---------------------------------------- \n");
            $impresora->text($ventasg->observaciones."\n");
            $impresora->cut();
            $impresora->close();
            /************** */
    }
}
