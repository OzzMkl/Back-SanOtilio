<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\models\Requisiciones;
use App\models\Productos_requisiciones;
use App\Productos_medidas;

class RequisicionController extends Controller
{
    
    public function index(){

    }

    public function registerRequisicion(Request $request){
            $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
            $params = json_decode($json);
            $params_array = json_decode($json,true);

            if(!empty($params) && !empty($params_array)){
                //eliminar espacios vacios
                $params_array = array_map('trim', $params_array);
                //validamos los datos
                $validate = Validator::make($params_array, [
                    'idEmpleado' => 'required',
                    'idStatus'   => 'required'
                ]);
                if($validate->fails()){//si el json esta mal mandamos esto (falta algun dato)
                    $data = array(
                        'status'    => 'error',
                        'code'      => 404, 
                        'message'   => 'Fallo! La requisicion no se ha creado',
                        'errors'    => $validate->errors()
                    );
                }else{
                    try{
                        DB::beginTransaction();
                        $Requisicion = new Requisiciones();
                        $Requisicion->observaciones = $params_array['observaciones'];
                        $Requisicion->idEmpleado = $params_array['idEmpleado'];
                        $Requisicion->idStatus = 29;

                        $Requisicion->save();

                        $data = array(
                            'status'    =>  'success',
                            'code'      =>  200,
                            'message'   =>  'Requisicion creada pero sin productos'
                        );
                        
                        DB::commit();

                    } catch(\Exception $e){
                        DB::rollBack();
                        return response()->json([
                            'code'      => 400,
                            'status'    => 'Error',
                            'message'   =>  'Fallo al crear la requisicion, Rollback!',
                            'error' => $e
                        ]);
                    }
                    return response()->json([
                        'code'      =>  200,
                        'status'    => 'Success!',
                        'Requisicion'   =>  $Requisicion
                    ]);
                }
                
            }
            return response()->json([
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            ]);

    }

    public function registerProductosRequisicion(Request $req){
            $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
            $params_array = json_decode($json,true);//decodifiamos el json
            if(!empty($params_array)){
                //consultamos la ultima compra para poder asignarla
                $Req = Requisiciones::latest('idReq')->first();//la guardamos en compra
                //recorremos el array para asignar todos los productos
                foreach($params_array AS $param => $paramdata){

                        $Productos_requisicion = new Productos_requisiciones();//creamos el modelo
                        $Productos_requisicion->idReq = $Req -> idReq;//asignamos el ultimo idCompra para todos los productos
                        $Productos_requisicion-> idProducto = $paramdata['idProducto'];
                        $Productos_requisicion-> idProdMedida = $paramdata['idProdMedida'];
                        $Productos_requisicion-> cantidad = $paramdata['cantidad'];
                        
                        $idProdMedidaC = $paramdata['idProdMedida'];
                        $cantidadC = $paramdata['cantidad'];
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

                        if($count == 1){//Si tiene una sola medida agrega directo la existencia ( count == 1 )
                            $Productos_requisicion-> igualMedidaMenor = $cantidadC;
                        }else{//Dos medidas en adelante se busca la posicion de la medida en la que se ingreso la compra
                            //Se hace un cilo que recorre listaPM
                            while($idProdMedidaC != $listaPM[$lugar]['attributes']['idProdMedida']){
                                //echo $listaPM[$lugar]['attributes']['idProdMedida'];
                                //echo $lugar;
                                $lugar++;
                            }
                            if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                                $Productos_requisicion-> igualMedidaMenor = $cantidadC;
                            }elseif($lugar == 0){//Medida mas alta, multiplicar desde el principio ( lugar == 0)
                                $igualMedidaMenor = $cantidadC;
                                while($lugar < $count ){
                                    $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar]['attributes']['unidad'];
                                    $lugar++;
                                    //echo $igualMedidaMenor;
                                }
                                $Productos_requisicion-> igualMedidaMenor = $igualMedidaMenor;
                            }elseif($lugar>0 && $lugar<$count-1){//Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                                $igualMedidaMenor = $cantidadC;
                                $count--;
                                //echo $count;
                                while($lugar < $count ){
                                    $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]['attributes']['unidad'];
                                    $lugar++;
                                }
                                $Productos_requisicion-> igualMedidaMenor = $igualMedidaMenor;
                            }else{

                            }
                        }


                        $Productos_requisicion->save();//guardamos el modelo

                        //Aqui no se guarda en monitoreo o movimiento de producto por que ese procedimiento se realiza en el metodo updateExistencia

                        //Si todo es correcto mandamos el ultimo producto insertado
                        $data =  array(
                            'status'        => 'success',
                            'code'          =>  200,
                            'Productos_requisicion'       =>  $Productos_requisicion
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

    public function getLastReq(){
        $Requisicion = Requisiciones::latest('idReq')->first();
        return response()->json([
            'code'         =>  200,
            'status'       => 'success',
            'requisicion'   => $Requisicion
        ]);
    }

    public function showMejorado($idReq){
        $Requisicion = DB::table('requisicion')
            ->join('empleado','empleado.idEmpleado','=','requisicion.idEmpleado')
            ->select('requisicion.*', 
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                        DB::raw('DATE_FORMAT(requisicion.created_at, "%d/%m/%Y") as created_at'))
            ->where('requisicion.idReq','=',$idReq)
            ->get();

        $productosRequisicion = DB::table('productos_requisiciones')
            ->join('producto','producto.idProducto','=','productos_requisiciones.idProducto')
            ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_requisiciones.idProdMedida')
            ->select('productos_requisiciones.*','producto.claveEx as claveexterna','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida')
            ->where('productos_requisiciones.idReq','=',$idReq)
            ->get();
        
        if(is_object($Requisicion)){
            $data = [
                'code'         => 200,
                'status'       => 'success',
                'requisicion'  => $Requisicion,
                'productos'    => $productosRequisicion
            ];
        }else{
            $data = [
                'code'          => 400,
                'status'        => 'error',
                'message'       => 'La requisicion no existe'
            ];
        }
        return response()->json($data, $data['code']);

    }
   





}
