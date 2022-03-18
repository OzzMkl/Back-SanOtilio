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
         $ordencompra = DB::table('ordendecompra')->get();
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
     public function show($idOrd){
        $ordencompra = DB::table('ordendecompra')
        ->join('productos_ordenes','productos_ordenes.idOrd','=','ordendecompra.idOrd')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->select('ordendecompra.*','productos_ordenes.*','proveedores.nombre as nombreProveedor')
        ->where('ordendecompra.idOrd','=',$idOrd)
        ->get();
        if(is_object($ordencompra)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'ordencompra'   =>  $ordencompra
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
