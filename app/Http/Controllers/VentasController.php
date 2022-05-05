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
                $productos_cotizacion->total = $paramdata['subtotal'];
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
}

/******************************************************** */
