<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\OrdenDeCompra;
use App\Productos_compra;
use App\Compras;
use App\Producto;
use App\lote;
use App\Pelote;


class CompraController extends Controller
{
    //
    public function index(){
        $compra = DB::table('compra')
        ->join('ordendecompra','ordendecompra.idOrden','=','compra.idOrden')
        ->join('pedido','pedido.idPedido','=','compra.idPedido')
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

                    if($params_array['idPedido']== null){
                        $Compra->idProveedor = $params_array['idProveedor'];
                        $Compra->folioProveedor = $params_array['folioProveedor'];
                        $Compra->subtotal = $params_array['subtotal'];
                        $Compra->total = $params_array['total'];
                        $Compra->idEmpleadoR = $params_array['idEmpleadoR'];
                        $Compra->idStatus = $params_array['idStatus'];
                        $Compra->fechaRecibo = $params_array['fechaRecibo'];                    
                        if(isset($params_array['observaciones'])){
                            $Compra->observaciones = $params_array['observaciones'];
                        }
                        if(isset($params_array['idOrd'])){
                            $Compra->idOrd = $params_array['idOrd'];
                        }
                    }
                    else{
                        $Compra->idOrd = $params_array['idOrd'];
                        $Compra->idPedido = $params_array['idPedido'];
                        $Compra->idProveedor = $params_array['idProveedor'];
                        $Compra->folioProveedor = $params_array['folioProveedor'];
                        $Compra->subtotal = $params_array['subtotal'];
                        $Compra->total = $params_array['total'];
                        $Compra->idEmpleadoR = $params_array['idEmpleadoR'];
                        $Compra->idStatus = $params_array['idStatus'];
                        $Compra->fechaRecibo = $params_array['fechaRecibo'];
                        if(isset($params_array['observaciones'])){
                            $Compra->observaciones = $params_array['observaciones'];
                        }
                    }

                    $Compra->save();

                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Compra creada pero sin productos'
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
                        'message'   =>  'Fallo al crear la compra Rollback!',
                        'error' => $e
                    ]);
                }
                return response()->json([
                    'code'      =>  200,
                    'status'    => 'Success!',
                    'compra'   =>  $Compra
                ]);
            }
            
        }
        return response()->json([
            'code'      =>  400,
            'status'    => 'Error!',
            'message'   =>  'json vacio'
        ]);

        
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
                           $Productos_compra-> cantidad = $paramdata['cantidad'];
                           $Productos_compra-> precio = $paramdata['precio'];
                           //$Productos_compra-> idImpuesto = $paramdata['idImpuesto'];
                           
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
        $Compra = Compras::latest('idCompra')->first();

        //idLote -> idCompra
        //idOrigen -> 3
        //codigo -> null

        $Lote = new Lote();//creamos el modelo
        $Lote->idLote = $Compra -> idCompra;//Asignamos el id de la ultima compra a idlote
        $Lote->idOrigen = 3; //Asignamos el numero de modulo    
        
        $Lote->save();//guardamos el modelo

        return response()->json([
            'code'         =>  200,
            'status'       => 'success',
            'Lote'   => $Lote
        ]);
    }

    public function updateExistencia(Request $request){
        //Alta de lote con codigo y idLote = idCompra


        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){
            //Obtener idLotess
            $Lote = lote::latest('idLote')->first();//la guardamos en Lote
            //Recorremos el array para asignar todos los productos
            //Agregar Producto - Existencia - Lote
            foreach($params_array AS $param => $paramdata){
                        $Pelote = new Pelote();//creamos el modelo
                        $Pelote->idLote = $Lote -> idLote;//asignamos el ultimo idLote para todos los productos
                        $Pelote-> idProducto = $paramdata['idProducto'];
                        $Pelote-> existencia = $paramdata['cantidad'];
                        $Pelote-> caducidad = $paramdata['caducidad'];
                        
                        $Pelote->save();//guardamos el modelo
                        //Si todo es correcto mandamos el ultimo producto insertado
                        $data =  array(
                            'status'        => 'success',
                            'code'          =>  200,
                            'Pelote'       =>  $Pelote
                        );
            }
            
            //Recalcular la existencia general y la actualizamos
            

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
        ->select('compra.*','proveedores.nombre as nombreProveedor', DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        ->where('compra.idCompra','=',$idCompra)
        ->get();
        $productosCompra = DB::table('productos_compra')
        ->join('producto','producto.idProducto','=','productos_compra.idProducto')
        ->join('medidas','medidas.idMedida','=','producto.idMedida')
        ->select('productos_compra.*','producto.claveEx as claveexterna','producto.descripcion as descripcion','medidas.nombre as nombreMedida')
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



}

