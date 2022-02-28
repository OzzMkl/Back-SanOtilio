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
    // public function index(){
    //     $Ordencompra = DB::table()->get();
    //     return response()->json({

    //     });

    // }
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

                     $Productos_orden = new Productos_ordenes();
                     $Productos_orden->idOrd = $Ordencompra -> idOrd;
                     $Productos_orden-> idProducto = $params_array['idProducto'];
                     $Productos_orden-> cantidad = $params_array['cantidad'];

                     $Productos_orden->save();

                     $data = array(
                         'status'    =>  'success',
                         'code'      =>  200,
                         'message'   =>  'Orden creada y lista de productos tambien!'
                     );
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
            }
            return response()->json([
                'code'      =>  200,
                'status'    => 'Success!',
                'Ordencompra'   =>  $Ordencompra
            ]);
        }

    }
}
