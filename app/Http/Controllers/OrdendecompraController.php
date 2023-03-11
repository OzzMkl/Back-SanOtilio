<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\OrdenDeCompra;
use App\Productos_ordenes;

class OrdendecompraController extends Controller
{
    //
     public function index(){
         $ordencompra = DB::table('ordendecompra')
         ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
         ->select('ordendecompra.*','proveedores.nombre as nombreProveedor')
        ->orderBy('ordendecompra.idOrd','desc')
         ->get();
         return response()->json([
            'code'         =>  200,
            'status'       => 'success',
            'ordencompra'   => $ordencompra
        ]);

    }

    public function registerOrdencompra(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            //eliminar espacios vacios
            $params_array = array_map('trim', $params_array);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'idProveedor'       => 'required',
                'observaciones'    => 'required',
                'idEmpleado'      => 'required',//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
                'idStatus'   => 'required'
            ]);
            if($validate->fails()){//si el json esta mal mandamos esto (falta algun dato)
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo! La orden de compra no se ha creado',
                    'errors'    => $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();
                    $Ordencompra = new OrdenDeCompra();
                    $Ordencompra->idProveedor = $params_array['idProveedor'];
                    $Ordencompra->observaciones = $params_array['observaciones'];
                    $Ordencompra->fecha = $params_array['fecha'];
                    $Ordencompra->idEmpleado = $params_array['idEmpleado'];
                    $Ordencompra->idStatus = $params_array['idStatus'];

                    $Ordencompra->save();

                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Orden creada pero sin productos'
                    );

                    //   $Productos_orden = new Productos_ordenes();
                    //   $Productos_orden->idOrd = $Ordencompra -> idOrd;
                    //   $Productos_orden-> idProducto = $params_array['idProducto'];
                    //   $Productos_orden-> cantidad = $params_array['cantidad'];

                    //   $Productos_orden->save();

                    //   $data = array(
                    //       'status'    =>  'success',
                    //       'code'      =>  200,
                    //       'message'   =>  'Orden creada y lista de productos tambien!'
                    //   );
                    DB::commit();

                } catch(\Exception $e){
                    DB::rollBack();
                    return response()->json([
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   =>  'Fallo al crear la orden compra Rollback!',
                        'error' => $e
                    ]);
                }
                return response()->json([
                    'code'      =>  200,
                    'status'    => 'Success!',
                    'Ordencompra'   =>  $Ordencompra
                ]);
            }
            
        }
        return response()->json([
            'code'      =>  400,
            'status'    => 'Error!',
            'message'   =>  'json vacio'
        ]);

    }

    public function registerProductosOrden(Request $req){
         $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
         $params_array = json_decode($json,true);//decodifiamos el json
         if(!empty($params_array)){
                //consultamos la ultima compra para poder asignarla
                $Orden = OrdenDeCompra::latest('idOrd')->first();//la guardamos en orden
                //recorremos el array para asignar todos los productos
                foreach($params_array AS $param => $paramdata){
                            $Productos_orden = new Productos_ordenes();//creamos el modelo
                            $Productos_orden->idOrd = $Orden -> idOrd;//asignamos el ultimo idOrd para todos los productos
                            $Productos_orden-> idProducto = $paramdata['idProducto'];
                            $Productos_orden-> idProdMedida = $paramdata['idProdMedida'];
                            $Productos_orden-> cantidad = $paramdata['cantidad'];
                            
                            $Productos_orden->save();//guardamos el modelo
                            //Si todo es correcto mandamos el ultimo producto insertado
                            $data =  array(
                                'status'        => 'success',
                                'code'          =>  200,
                                'Productos_orden'       =>  $Productos_orden
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

    public function getLastOrder(){
         $ordencompra = OrdenDeCompra::latest('idOrd')->first();
         return response()->json([
             'code'         =>  200,
             'status'       => 'success',
             'ordencompra'   => $ordencompra
         ]);
    }
    
    public function showMejorado($idOrd){
        $ordencompra = DB::table('ordendecompra')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','ordendecompra.idEmpleado')
        ->select('ordendecompra.*','proveedores.nombre as nombreProveedor', DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        ->where('ordendecompra.idOrd','=',$idOrd)
        ->get();
        $productosOrden = DB::table('productos_ordenes')
        ->join('producto','producto.idProducto','=','productos_ordenes.idProducto')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ordenes.idProdMedida')
        ->select('productos_ordenes.*','producto.claveEx as claveEx','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida')
        ->where([
                    ['productos_ordenes.idOrd','=',$idOrd]
                ])
        ->get();

        if(is_object($ordencompra)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'ordencompra'   =>  $ordencompra,
                'productos'     => $productosOrden
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

    public function updateOrder($idOrd, Request $request){

        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
         if(!empty($params_array)){
             //eliminar espacios vacios
            $params_array = array_map('trim', $params_array);
            //quitamos lo que no queremos actualizar
            unset($params_array['idOrd']);
            unset($params_array['idReq']);
            unset($params_array['created_at']);
            //actualizamos
            $Ordencompra = OrdenDeCompra::where('idOrd',$idOrd)->update($params_array);
                //retornamos la respuesta si esta
                 return response()->json([
                    'status'    =>  'success',
                    'code'      =>  200,
                    'message'   =>  'Orden actualizada'
                 ]);

         }else{
            return response()->json([
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            ]);   
         }
    }

    public function updateProductsOrder($idOrd,Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){//verificamos que no este vacio

            //eliminamos los registros que tengab ese idOrd
            Productos_ordenes::where('idOrd',$idOrd)->delete();
            //recorremos el array para asignar todos los productos
            foreach($params_array AS $param => $paramdata){
                $Productos_orden = new Productos_ordenes();//creamos el modelo
                $Productos_orden->idOrd = $idOrd;//asignamos el id desde el parametro que recibimos
                $Productos_orden-> idProducto = $paramdata['idProducto'];//asginamos segun el recorrido
                $Productos_orden-> cantidad = $paramdata['cantidad'];
                
                $Productos_orden->save();//guardamos el modelo
                //Si todo es correcto mandamos el ultimo producto insertado
            }
            $data =  array(
                'status'            => 'success',
                'code'              =>  200,
                'message'           =>  'Eliminacion e insercion correcta!',
                'Productos_orden'   =>  $Productos_orden
            );
            
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
