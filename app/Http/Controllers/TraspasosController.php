<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\models\traspasoe;
use App\models\Productos_traspasoE;
use App\models\traspasor;
use App\models\Productos_traspasoR;
use App\models\moviproduc;
use App\models\Monitoreo;
use App\models\Sucursal;
use App\models\Empresa;
use TCPDF;
use App\Clases\clsProducto;
use App\Producto;


class TraspasosController extends Controller
{

    public function index($tipoTraspaso, Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);
        
        if(!empty($tipoTraspaso) && strlen($params_array['str_traspaso']) >= 1){
            //$tabla = 'App\models\\'.$tipoTraspaso;
            
            $traspaso = DB::table($tipoTraspaso)
            ->join('sucursal as E','E.idSuc','=',$tipoTraspaso.'.sucursalE')
            ->join('sucursal as R','R.idSuc','=',$tipoTraspaso.'.sucursalR')
            ->join('empleado','empleado.idEmpleado','=',$tipoTraspaso.'.idEmpleado')    
            ->join('statuss','statuss.idStatus','=',$tipoTraspaso.'.idStatus')    
            ->select($tipoTraspaso.'.*','E.nombre as sucursalEN','R.nombre as sucursalRN','statuss.nombre as nombreStatus',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado")
                    )
            ->where([
                [$tipoTraspaso.'.id'.$tipoTraspaso,'like','%'.$params_array['str_traspaso'].'%']
            ])
            //->whereDate($tipoTraspaso. '.created_at',$params_array['date_inicial'])
            
            // ->whereRaw('DATE(' . $tipoTraspaso . '.created_at) BETWEEN \'' . $params_array['date_inicial'] . '\' AND \'' . $params_array['date_final'] . '\'')
            // ->get();
            ->paginate(10);
            // $traspaso = $tabla::with(['sucursalE', 'empleado', 'status'])->paginate(10);

            $data= array(
                'code'     =>  200,
               'status'    => 'success',
               'traspasos' => $traspaso
            );
            
        } elseif(!empty($tipoTraspaso) && strlen($params_array['str_traspaso']) == 0 ){
            // Si $params_array['str_traspaso'] no está presente o está vacío,
            // busca los últimos 200 traspasos realizados
            $traspaso = DB::table($tipoTraspaso)
            ->join('sucursal as E', 'E.idSuc', '=', $tipoTraspaso . '.sucursalE')
            ->join('sucursal as R', 'R.idSuc', '=', $tipoTraspaso . '.sucursalR')
            ->join('empleado', 'empleado.idEmpleado', '=', $tipoTraspaso . '.idEmpleado')
            ->join('statuss', 'statuss.idStatus', '=', $tipoTraspaso . '.idStatus')
            ->select(
                $tipoTraspaso . '.*',
                'E.nombre as sucursalEN',
                'R.nombre as sucursalRN',
                'statuss.nombre as nombreStatus',
                DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado")
            )
            ->orderByDesc($tipoTraspaso . '.created_at')
            ->limit(200)
            ->paginate(10);

            $data= array(
                'code'     =>  200,
               'status'    => 'success',
               'traspasos' => $traspaso
            );
        } else{
            $data= array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }

        return response()->json($data, $data['code']);
       
    }


    public function registerTraspaso(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if(empty($params_array)){
            $validate = Validator::make($params_array['traspaso'],[
                'sucursalE'     =>  'required',
                'sucursalR'     =>  'required',
                'idEmpleado'    =>  'required',
                'observaciones' =>  'required'
            ]);

            if($validate->fails()){
                $data = array(
                    'code'      =>  '404',
                    'status'    =>  'error',
                    'message'   =>  'Fallo la validacion de los datos del traspaso',
                    'errors'    =>  $validate->errors()
                );
            } else{
                DB::beginTransaction();
                if($params_array['tipoTraspaso']== 'traspasoE'){
                    $traspasoNuevo = new traspasoe();
                    $tipoTraspaso = 'envio';
                }elseif($params_array['tipoTraspaso']== 'traspasoR'){
                    $traspasoNuevo = new traspasor();
                    $tipoTraspaso = 'recepcion';
                }

                if(isset($params_array['traspaso']['folio'])){
                    $traspasoNuevo->folio = $params_array['traspaso']['folio'];
                }

                $traspasoNuevo->sucursalE = $params_array['traspaso']['sucursalE'];
                $traspasoNuevo->sucursalR = $params_array['traspaso']['sucursalR'];
                $traspasoNuevo->idEmpleado = $params_array['traspaso']['idempleado'];
                $traspasoNuevo->idStatus = 39;
                
                if(isset($params_array['traspaso']['observaciones'])){
                    $traspasoNuevo->observaciones = $params_array['traspaso']['observaciones'];
                }

                $traspasoNuevo->save();

                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                if($params_array['tipoTraspaso']== 'traspasoE'){
                    $Traspaso = traspasoe::latest('idTraspasoE')->first()->idTraspasoE;
                }elseif($params_array['tipoTraspaso']== 'traspasoR'){
                    $Traspaso = traspasor::latest('idTraspasoR')->first()->idTraspasoR;
                }

                //insertamos el movimiento que se hizo en general
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                $monitoreo -> accion =  "Alta de traspaso, ".$tipoTraspaso;
                $monitoreo -> folioNuevo =  $Traspaso;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                /**INICIO INSERCION DE PRODUCTOS */

                $dataProductos = $this->registerProductosTraspaso($Traspaso,$tipoTraspaso,$params_array['productosTraspaso'],$params_array['identity']['sub']);
                
                /**FIN INSERCION DE PRODUCTOS */
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

    public function registerProductosTraspaso($Traspaso,$tipoTraspaso,$productosTraspaso,$idEmpleado){
        if(count($productosTraspaso) >= 1 && !empty($productosTraspaso)){
            try{
                DB::beginTransaction();
                //Creamos instancia para poder ocupar las funciones
                $clsMedMen = new clsProducto();
                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                foreach($productosTraspaso as $param => $paramdata){
                    //calculamos medida menor
                    $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$paramdata['cantidad']);

                    //Consultamos la existencia antes de actualizar
                    $Producto = Producto::find($paramdata['idProducto']);
                    $stockAnterior = $Producto -> existenciaG;

                    //Actualizamos
                    if($tipoTraspaso == 'envio'){
                        $Producto -> existenciaG = $Producto -> existenciaG - $paramdata['igualMedidaMenor'];
                    }elseif($tipoTraspaso == 'recepcion'){
                        $Producto -> existenciaG = $Producto -> existenciaG + $paramdata['igualMedidaMenor'];
                    }
                    $Producto -> save();

                    //Consultamos la existencia despues de actualizar
                    $stockActualizado = $Producto->existenciaG;

                    //insertamos el movimiento de existencia que se le realizo al producto
                    $moviproduc = new moviproduc();
                    $moviproduc -> idProducto =  $paramdata['idProducto'];
                    $moviproduc -> claveEx =  $Producto -> claveEx;
                    $moviproduc -> accion =  "Alta de traspaso, ".$tipoTraspaso;
                    $moviproduc -> folioAccion =  $Traspaso;
                    $moviproduc -> cantidad =  $medidaMenor;
                    $moviproduc -> stockanterior =  $stockAnterior;
                    $moviproduc -> stockactualizado =  $stockActualizado;
                    $moviproduc -> idUsuario =  $idEmpleado;
                    $moviproduc -> pc =  $ip;
                    $moviproduc ->save();

                    //Agregamos los productos del traspaso
                    if($tipoTraspaso == 'envio'){
                        $producto_traspaso = new Productos_traspasoE();
                    }elseif($tipoTraspaso == 'recepcion'){
                        $producto_traspaso = new Productos_traspasoR();
                    }

                    
                    


                }

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Productos agregados correctamente'
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






}
