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
use App\models\moviproduc;
use App\models\Monitoreo;


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

                    $Compra->save();
                    
                    //Obtenemos de la ultima compra el idCompra, idOrden y IdEmpleadoRecibe
                    $Foliocompra = Compras::latest('idCompra')->first()->idCompra; 
                    //obtenemos el nombre de la maquina
                    $pc = gethostname();

                    //insertamos el movimiento que se hizo en general
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['idEmpleadoR'];
                    $monitoreo -> accion =  "Alta de compra";
                    $monitoreo -> folioAnterior = $params_array['idOrd'];
                    $monitoreo -> folioNuevo =  $Foliocompra;
                    $monitoreo -> pc =  $pc;
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
                           $Productos_compra-> cantidad = $paramdata['cantidad'];
                           $Productos_compra-> precio = $paramdata['precio'];
                           if( $paramdata['idImpuesto'] != 0){
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
        $Compra = Compras::latest('idCompra')->first();

        //idLote -> idCompra
        //idOrigen -> 3
        //codigo -> null

        

        return response()->json([
            'code'         =>  200,
            'status'       => 'success',
            'Lote'   => $Compra
        ]);
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
                //obtenemos el nombre de la maquina
                $pc = gethostname();

                //Recorremos el array para asignar todos los productos
                //Actualizar Producto -> ExistenciaG
                foreach($params_array AS $param => $paramdata){
                
                    //antes de actualizar el producto obtenemos su existencia
                    $stockanterior = Producto::find($paramdata['idProducto'])->existenciaG;
                    //Buscamos el producto a actualizar y actualizamos
                    $Producto = Producto::find($paramdata['idProducto']);
                    $Producto -> existenciaG = $Producto -> existenciaG + $paramdata['cantidad'];
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
                    $moviproduc -> pc =  $pc;
                    $moviproduc ->save();

                    //Si todo es correcto mandamos el ultimo producto insertado y el movimiento
                    $data =  array(
                        'status'        => 'success',
                        'code'          =>  200,
                        'Producto'      =>  $Producto,
                        'Movimiento'    =>  $moviproduc
                    );
                }
                DB::commit();
            } catch(\Exception $e){
                DB::rollBack();
                $data = array(
                    'code'      => 400,
                    'status'    => 'Error',
                    'message'   =>  'Fallo algo',
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
        ->select('compra.*','proveedores.nombre as nombreProveedor', 
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(compra.fechaRecibo, "%d/%m/%Y") as fecha_format'))
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
        ->where('compra.idStatus','=',1)
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
                    ['compra.idStatus','=','1'],
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
                    ['compra.idStatus','=','1'],
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
                    ['compra.idStatus','=','1'],
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
                    ['compra.idStatus','=','1'],
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

