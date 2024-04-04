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
use Carbon\Carbon;




class TraspasosController extends Controller
{

    public function newIndex($tipoTrasp, $str_traspaso){
        // $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        // $params = json_decode($json);
        // $params_array = json_decode($json,true);
        
        if(!empty($tipoTrasp) && $str_traspaso != 'vacio'){
            
            if($tipoTrasp== 'Envia'){
                $tipoTraspaso = 'traspasoE';
            }elseif($tipoTrasp == 'Recibe'){
                $tipoTraspaso = 'traspasoR';
            }
            
            $traspaso = DB::table($tipoTraspaso)
            ->join('sucursal as E','E.idSuc','=',$tipoTraspaso.'.sucursalE')
            ->join('sucursal as R','R.idSuc','=',$tipoTraspaso.'.sucursalR')
            ->join('empleado','empleado.idEmpleado','=',$tipoTraspaso.'.idEmpleado')    
            ->join('statuss','statuss.idStatus','=',$tipoTraspaso.'.idStatus')    
            ->select($tipoTraspaso.'.*','E.nombre as sucursalEN','R.nombre as sucursalRN','statuss.nombre as nombreStatus',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado")
                    )
            ->where([
                [
                    $tipoTraspaso.'.id'.$tipoTraspaso,'like','%'.$str_traspaso.'%',
                    
                ]
            ])
            ->paginate(10);

            $data= array(
                'code'     =>  200,
               'status'    => 'success',
               'traspasos' => $traspaso
            );
            
        } elseif(!empty($tipoTrasp) && $str_traspaso == 'vacio' ){
            // Si $params_array['str_traspaso'] no está presente o está vacío,
            // busca los últimos 200 traspasos realizados

            if($tipoTrasp== 'Envia'){
                $tipoTraspaso = 'traspasoE';
            }elseif($tipoTrasp == 'Recibe'){
                $tipoTraspaso = 'traspasoR';
            }

            $traspaso = DB::table($tipoTraspaso)
            ->join('sucursal as E', 'E.idSuc', '=', $tipoTraspaso . '.sucursalE')
            ->join('sucursal as R', 'R.idSuc', '=', $tipoTraspaso . '.sucursalR')
            ->join('empleado', 'empleado.idEmpleado', '=', $tipoTraspaso . '.idEmpleado')
            ->join('statuss', 'statuss.idStatus',    '=', $tipoTraspaso . '.idStatus')
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
            // ->get();

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
        //var_dump($params_array);
        //die();

        if(!empty($params_array)){

            if($params_array['tipoTraspaso']== 'Envia'){
                $validate = Validator::make($params_array['traspaso'],[
                    'sucursalE' =>  'required',
                    'sucursalR' =>  'required|different:sucursalE'
                ]);
            }elseif($params_array['tipoTraspaso']== 'Recibe'){
                $validate = Validator::make($params_array['traspaso'],[
                    'sucursalE' =>  'required',
                    'sucursalR' =>  'required|different:sucursalE',
                    'folio'     =>  'required'
                ]);
            }else{

            }

            if($validate->fails()){
                $data = array(
                    'code'      =>  '404',
                    'status'    =>  'error',
                    'message'   =>  'Fallo la validación de los datos del traspaso',
                    'errors'    =>  $validate->errors()
                );
            }else{
                DB::beginTransaction();
                if($params_array['tipoTraspaso']== 'Envia'){
                    $traspasoNuevo = new traspasoe();
                    $tipoTraspaso = $params_array['tipoTraspaso'];
                }elseif($params_array['tipoTraspaso']== 'Recibe'){
                    $traspasoNuevo = new traspasor();
                    $tipoTraspaso = $params_array['tipoTraspaso'];
                    $traspasoNuevo->folio = $params_array['traspaso']['folio'];
                }

                $traspasoNuevo->sucursalE = $params_array['traspaso']['sucursalE'];
                $traspasoNuevo->sucursalR = $params_array['traspaso']['sucursalR'];
                $traspasoNuevo->idEmpleado = $params_array['traspaso']['idEmpleado'];
                $traspasoNuevo->idStatus = 39;
                
                if(isset($params_array['traspaso']['observaciones'])){
                    $traspasoNuevo->observaciones = $params_array['traspaso']['observaciones'];
                }

                $traspasoNuevo->save();
                //$Traspaso = traspasor::latest('idTraspasoR')->get();
                // echo $tipoTraspaso;
                // echo $Traspaso;
                // var_dump($Traspaso);
                // die();

                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                if($params_array['tipoTraspaso']== 'Envia'){
                    $Traspaso = traspasoe::latest('idTraspasoE')->value('idTraspasoE');
                }elseif($params_array['tipoTraspaso']== 'Recibe'){
                    $Traspaso = traspasor::latest('idTraspasoR')->value('idTraspasoR');
                }

                //insertamos el movimiento que se hizo en general
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                $monitoreo -> accion =  "Alta de traspaso, ".$tipoTraspaso;
                $monitoreo -> folioNuevo =  $Traspaso;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                /**INICIO INSERCION DE PRODUCTOS */

                $dataProductos = $this->registerProductosTraspaso($Traspaso,$tipoTraspaso,$params_array['lista_producto_traspaso'],$params_array['identity']['sub']);
                
                /**FIN INSERCION DE PRODUCTOS */
                
                /**INICIO DE INSERCION EN SUCRSAL DESTINO */
                //Verificamos sucursal de destino, en caso de ser un traspaso a una sucursal foránea no se realiza la inserción
                //Obtenemos el campo connection de la sucursal destino, si es nulo o vacío no se hace nada
                $connection = DB::table('sucursal')->select('connection')->where('idSuc','=',$params_array['traspaso']['sucursalR'])->value('connection');

                //if(count($connection) >= 1 && !empty($connection) && $params_array['tipoTraspaso'] == 'Envia'){
                if($connection!=NULL  && $params_array['tipoTraspaso'] == 'Envia'){
                    $dataRegistroExternos = $this->registerTraspasoExterno($Traspaso,$params_array['traspaso']['sucursalR']);
                }

                /**FIN DE INSERCION EN SUCRSAL DESTINO */



                DB::commit();

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Traspaso registrado correctamente',
                    'traspaso' => $Traspaso,
                    'dataProductos' => $dataProductos,
                    'dataRegistroExternos' => $dataRegistroExternos
                );
            }
        }else{
            $data= array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);

    }

    public function registerUsoInterno(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){

            if($params_array['tipoTraspaso'] == 'Uso interno'){
                $validate = Validator::make($params_array['traspaso'],[
                    'sucursalE' =>  'required',
                    'sucursalR' =>  'required|same:sucursalE'
                ]);
            }

            if($validate->fails()){
                $data = array(
                    'code'      =>  '404',
                    'status'    =>  'error',
                    'message'   =>  'Fallo la validación de los datos del traspaso',
                    'errors'    =>  $validate->errors()
                );
            }else{
                //var_dump($params_array);

                DB::beginTransaction();

                $traspasoNuevo = new traspasoe();
                $tipoTraspaso = $params_array['tipoTraspaso'];
                $traspasoNuevo->sucursalE = $params_array['traspaso']['sucursalE'];
                $traspasoNuevo->sucursalR = $params_array['traspaso']['sucursalR'];
                $traspasoNuevo->idEmpleado = $params_array['traspaso']['idEmpleado'];
                $traspasoNuevo->idStatus = 39;
                
                if(isset($params_array['traspaso']['observaciones'])){
                    $traspasoNuevo->observaciones = $params_array['traspaso']['observaciones'];
                }

                $traspasoNuevo->save();
               
                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                $Traspaso = traspasoe::latest('idTraspasoE')->value('idTraspasoE');

                //insertamos el movimiento que se hizo en general
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                $monitoreo -> accion =  "Alta de traspaso, ".$tipoTraspaso;
                $monitoreo -> folioNuevo =  $Traspaso;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                /**INICIO INSERCION DE PRODUCTOS */

                $dataProductos = $this->registerProductosTraspaso($Traspaso,$tipoTraspaso,$params_array['lista_producto_traspaso'],$params_array['identity']['sub']);
                
                /**FIN INSERCION DE PRODUCTOS */

                DB::commit();

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Traspaso registrado correctamente',
                    'traspaso' => $Traspaso,
                    'dataProductos' => $dataProductos
                );
            }
        }else{
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

                    //Actualizamos existenciaG
                    if($tipoTraspaso == 'Envia' || $tipoTraspaso ==  'Uso interno' ){
                        $Producto -> existenciaG = $Producto -> existenciaG - $medidaMenor;
                    }elseif($tipoTraspaso == 'Recibe'){
                        $Producto -> existenciaG = $Producto -> existenciaG + $medidaMenor;
                    }
                    $Producto -> save();


                    //Consultamos la existencia despues de actualizar
                    $stockActualizado = $Producto->existenciaG;

                    //insertamos el movimiento de existencia que se le realizo al producto
                    moviproduc::insertMoviproduc($paramdata,$accion = "Alta de traspaso, ".$tipoTraspaso,
                                                $Traspaso,$medidaMenor,$stockAnterior,$stockActualizado,$idEmpleado,
                                                $_SERVER['REMOTE_ADDR']);

                    //Agregamos los productos del traspaso
                    if($tipoTraspaso == 'Envia' || $tipoTraspaso ==  'Uso interno' ){
                        $producto_traspaso = new Productos_traspasoE();
                        $producto_traspaso -> idTraspasoE = $Traspaso;
                    }elseif($tipoTraspaso == 'Recibe'){
                        $producto_traspaso = new Productos_traspasoR();
                        $producto_traspaso -> idTraspasoR = $Traspaso;
                    }

                    $producto_traspaso -> idProducto = $paramdata['idProducto'];
                    $producto_traspaso -> descripcion = $Producto -> descripcion;
                    $producto_traspaso -> claveEx = $Producto -> claveEx;
                    $producto_traspaso -> idProdMedida = $paramdata['idProdMedida'];
                    $producto_traspaso -> cantidad = $paramdata['cantidad'];
                    $producto_traspaso -> precio = $paramdata['precio'];
                    $producto_traspaso -> subtotal = $paramdata['subtotal'];
                    $producto_traspaso -> igualMedidaMenor = $medidaMenor;
                    $producto_traspaso -> save();
                    
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

    public function registerTraspasoExterno($idTraspasoE,$sucursalR){
        //Consulta de datos para enviar
        //Traspaso a enviar
        $Traspaso = DB::table('traspasoE')->where('idTraspasoE','=',$idTraspasoE)->first();
        //Productos del traspaso a enviar
        $productosTraspaso = DB::table('productos_traspasoE')->where('idTraspasoE','=',$idTraspasoE)->get();
        // dd($productosTraspaso);
        //Base de datos a la que nos vamos a conectar
        $connection = DB::table('sucursal')->select('connection')->where('idSuc','=',$sucursalR)->value('connection');
        //Nombre de sucursal que envía
        $sucursalE = DB::table('empresa')->select('nombreCorto')->value('nombreCorto');


        DB::connection($connection)->beginTransaction();
        try {

            //Insersión de traspaso en tabla traspasoR en sucursal de destino
            DB::connection($connection)->table('traspasoR')->insert([

                'folio' => $Traspaso->idTraspasoE,
                'sucursalE' => $Traspaso->sucursalE,
                'sucursalR' => $Traspaso->sucursalR,
                'idEmpleado' => $Traspaso->idEmpleado,
                'idStatus' => 50,
                'observaciones' => $Traspaso->observaciones,
                'created_at' => Carbon::now(),
                'updated_at' =>  Carbon::now()

            ]);

            //Obtener idTraspasoR del traspaso que acabamos de insertar
            $idTraspasoR=DB::connection($connection)->table('traspasoR')->latest('idTraspasoR')->value('idTraspasoR');

            if(count($productosTraspaso) >= 1 && !empty($productosTraspaso)){
                
                foreach($productosTraspaso as $param => $paramdata){
                    //var_dump($paramdata->claveEx);
                    // die;

                    
                    //Insertamos en tabla de proudctos_traspasoR de sucursal que recibe
                    DB::connection($connection)->table('productos_traspasor')->insert([
                        'idTraspasoR' => $idTraspasoR,
                        'idProducto' => $paramdata->idProducto,
                        'descripcion' => $paramdata->descripcion,
                        'claveEx' => $paramdata->claveEx,
                        'idProdMedida' => $paramdata->idProdMedida,
                        'cantidad' => $paramdata->cantidad,
                        'precio' => $paramdata->precio,
                        'subtotal' => $paramdata->subtotal,
                        'igualMedidaMenor' => $paramdata->igualMedidaMenor,
                        'created_at' => Carbon::now(),
                        'updated_at' =>  Carbon::now()
                    ]);
                    
                }

            }else{
                $productosTraspaso = 'El traspaso no tiene productos';
            }       

            //Obtenemos direccion ip
            $ip = $_SERVER['REMOTE_ADDR'];
            //Insertamos movimiento en monitoreo de sucursal que envia
            $monitoreo = new Monitoreo();
            $monitoreo -> idUsuario =   $Traspaso->idEmpleado;
            $monitoreo -> accion =  "Alta de traspaso, envia, en SUCURSAL ".strtoupper($connection);
            $monitoreo -> folioAnterior =  $idTraspasoE;
            $monitoreo -> folioNuevo =  $Traspaso->idTraspasoE;
            $monitoreo -> pc =  $ip;
            $monitoreo ->save();

            // Insertamos movimiento en monitoreo de sucursal que recibe
            DB::connection($connection)->table('monitoreo')-> insert([
                'idUsuario' => $Traspaso->idEmpleado,
                'accion' => "Alta de traspaso, recibe, de ".$sucursalE,
                'folioNuevo' => $idTraspasoR,
                'pc' => $ip,
                'created_at' => Carbon::now(),
                'updated_at' =>  Carbon::now()
            ]);

            DB::connection($connection)->commit();

            $TraspasoN = DB::connection($connection)->table('traspasoR')
                ->where([
                            ['folio','=',$Traspaso->idTraspasoE],
                            ['sucursalE','=',$Traspaso->sucursalE]
                        ])
                ->get();


            $data =  array(
                'code'      =>  200,
                'status'    =>  'success',
                'message'   =>  '',
                'Traspaso'  =>  $Traspaso,
                'productos' =>  $productosTraspaso,
                'connection' => $connection,
                'TraspasoN' =>  $TraspasoN
            );
            
        } catch (\Exception $e) {
            DB::connection($connection)->rollback();
            $data =  array(
                'code'    => 400,
                'status'  => 'error',
                'message' => 'Fallo al registrar el traspaso en la sucursal que recibe',
                'error'   => $e
            );
            
        }

        return $data;
    
    }

    public function generatePDF($idTraspaso,$idEmpleado,$tipoTraspaso){
        //dd($idTraspaso,$idEmpleado,$tipoTraspaso);
        $Empresa = Empresa::first();

        if($tipoTraspaso == 'Envia' || $tipoTraspaso == 'Uso interno' ){
            $tabla = 'traspasoE';
            $tablaProd = 'productos_traspasoE';
        }elseif($tipoTraspaso == 'Recibe'){
            $tabla = 'traspasoR';
            $tablaProd = 'productos_traspasoR';
        }
        //dd($tabla,$tablaProd);
        $traspaso = DB::table($tabla)
        ->join('sucursal as E','E.idSuc','=',$tabla.'.sucursalE')
        ->join('sucursal as R','R.idSuc','=',$tabla.'.sucursalR')
        ->join('empleado','empleado.idEmpleado','=',$tabla.'.idEmpleado')    
        ->join('statuss','statuss.idStatus','=',$tabla.'.idStatus')    
        ->select($tabla.'.*','E.nombre as sucursalEN','R.nombre as sucursalRN','statuss.nombre as nombreStatus',
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT('.$tabla.'.created_at, "%d/%m/%Y") as created_format')
                )
        ->where([   [$tabla.'.id'.$tabla,'=',$idTraspaso]   ])
        ->first();

        $productosTraspaso = DB::table($tablaProd)
        ->join('producto','producto.idProducto','=',$tablaProd.'.idProducto')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=',$tablaProd.'.idProdMedida')
        ->select( $tablaProd.'.*','historialproductos_medidas.nombreMedida as nombreMedida' )
        ->where([
                    [$tablaProd.'.id'.$tabla,'=',$idTraspaso]
                ])
        ->get();

        if(is_object($traspaso)){

            //Registramos acción en monitoreo
            Monitoreo::insertMonitoreo(
                $idEmpleado,
                $accion = "Impresión de PDF, traspaso ".$tipoTraspaso,
                null,
                $idTraspaso,
                null
            );

            //CREACION DEL PDF
            $pdf = new TCPDF('P', 'MM','A4','UTF-8');
            //ELIMINAMOS CABECERAS Y PIE DE PAGINA
            $pdf-> setPrintHeader(false);
            $pdf-> setPrintFooter(false);
            //INSERTAMOS PAGINA
            $pdf->AddPage();
            //DECLARAMOS FUENTE Y TAMAÑO
            $pdf->SetFont('helvetica', '', 18); // Establece la fuente

            //Buscamos imagen y la decodificamos 
            $file = base64_encode( \Storage::disk('images')->get('logo-solo2.png'));
            //$file = base64_encode( \Storage::disk('images')->get('pe.jpg'));
            //descodificamos y asignamos
            $image = base64_decode($file);
            //insertamos imagen se pone @ para especificar que es a base64
            //              imagen,x1,y1,ancho,largo
            $pdf->Image('@'.$image,10,9,25,25);
            $pdf->setXY(40,8);
            //ESCRIBIMOS
            //        ancho,altura,texto,borde,salto de linea
            $pdf->Cell(0, 10, $Empresa->nombreLargo, 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(45,15);
            $pdf->Cell(0, 10, $Empresa->nombreCorto.': COLONIA '. $Empresa->colonia.', CALLE '. $Empresa->calle. ' #'. 
                                $Empresa->numero. ', '. $Empresa->ciudad. ', '. $Empresa->estado, 0, 1); // Agrega un texto

            $pdf->setXY(60,20);
            $pdf->Cell(0,10,'CORREOS: '. $Empresa->correo1. ', '. $Empresa->correo2);

            $pdf->setXY(68,25);
            $pdf->Cell(0,10,'TELEFONOS: '. $Empresa->telefono. ' ó '. $Empresa->telefono2. '   RFC: '. $Empresa->rfc);

            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,37,200,37);//X1,Y1,X2,Y2

            $pdf->SetLineWidth(5);//grosor de la linea
            $pdf->Line(10,43,58,43);//X1,Y1,X2,Y2

            $pdf->SetFont('helvetica', 'B', 12); // Establece la fuente
            $pdf->setXY(10,38);

            if($tipoTraspaso == 'Envia'){
                $pdf->Cell(0, 10, 'TRASPASO #'. $traspaso->idTraspasoE, 0, 1); // Agrega un texto
            }elseif($tipoTraspaso == 'Recibe'){
                $pdf->Cell(0, 10, 'TRASPASO #'. $traspaso->idTraspasoR, 0, 1); // Agrega un texto
            }

                $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                $pdf->setXY(60,38);
                $pdf->Cell(0, 10, 'ENVIA: ', 0, 1); // Agrega un texto

                $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
                $pdf->setXY(71,38);
                $pdf->Cell(0, 10,strtoupper($traspaso->sucursalEN), 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(10,43);
            $pdf->Cell(0, 10, 'TIPO TRASPASO:', 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            $pdf->setXY(37,43);
            $pdf->Cell(0, 10, strtoupper($tipoTraspaso), 0, 1); // Agrega un texto

            if($tipoTraspaso == 'Recibe'){
             $pdf->SetFont('helvetica', '', 9); // Establece la fuente
             $pdf->setXY(10,48);
             $pdf->Cell(0, 10, 'FOLIO ENVIO:', 0, 1); // Agrega un texto 

             $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
             $pdf->setXY(32,48);
             $pdf->Cell(0, 10, strtoupper($traspaso->folio), 0, 1); // Agrega un texto
            }

                $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                $pdf->setXY(60,43);
                $pdf->Cell(0, 10, 'RECIBE:',0, 1); // Agrega un texto

                $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
                $pdf->setXY(73.5,43);
                $pdf->Cell(0, 10, strtoupper($traspaso->sucursalRN),0, 1); // Agrega un texto


                
                    $pdf->setXY(157,43);
                    $pdf->Cell(0, 10, 'FECHA: '. substr($traspaso->created_format,0,10), 0, 1); // Agrega un texto

            
                $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                $pdf->setXY(60,48);
                $pdf->Cell(0, 10, 'EMPLEADO: '. strtoupper($traspaso->nombreEmpleado), 0, 1); // Agrega un texto

                    $mytime = date('d/m/Y H:i:s');
                    $pdf->setXY(153,48);
                    $pdf->Cell(0, 10, 'IMPRESO: '. $mytime, 0, 1); // Agrega un texto


            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,57,200,57);//X1,Y1,X2,Y2

            $pdf->SetDrawColor(0,0,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(.2);//grosor de la linea
            $pdf->SetFillColor(7, 149, 223  );//Creamos color de relleno para la tabla
            $pdf->setXY(10,62);

            //Contamos el numero de productos
            $numRegistros = count($productosTraspaso);
            //establecemos limite de productos por pagina
            $RegistroPorPagina = 18;
            //calculamos cuantas paginas van hacer
            $paginas = ceil($numRegistros / $RegistroPorPagina);
            $contRegistros = 0;

            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            //INSERTAMOS CABECERAS TABLA
            $pdf->Cell(35,10,'CLAVE EXTERNA',1,0,'C',true);
            $pdf->Cell(81, 10, 'DESCRIPCION', 1,0,'C',true);
            $pdf->Cell(18, 10, 'MEDIDA', 1,0,'C',true);
            $pdf->Cell(16, 10, 'CANT.', 1,0,'C',true);
            $pdf->Cell(40, 10, 'IGUAL MEDIDA MENOR', 1,0,'C',true);
            $pdf->Ln(); // Nueva línea3

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 10); // Establece la fuente

            //REALIZAMOS RECORRIDO DEL ARRAY DE PRODUCTOS
            foreach($productosTraspaso  as $prod){
                /***
                 * Verificamos que nuestro contador sea mayor a cero para no insertar pagina de mas
                 * Utiliza el operador % (módulo) para verificar si el contador de registros es divisible
                 * exactamente por el número de registros por página ($RegistroPorPagina).
                 *  Si el resultado de esta expresión es igual a cero, significa que se ha alcanzado
                 *  un múltiplo del número de registros por página y se necesita agregar una nueva página.
                 */
                if( $contRegistros > 0 && $contRegistros % $RegistroPorPagina == 0){
                    $pdf->AddPage();
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFont('helvetica', 'B', 10); // Establece la fuente
                    //CABECERAS TABLA
                    $pdf->Cell(35,10,'CLAVE EXTERNA',1,0,'C',true);
                    $pdf->Cell(81, 10, 'DESCRIPCION', 1,0,'C',true);
                    $pdf->Cell(18, 10, 'MEDIDA', 1,0,'C',true);
                    $pdf->Cell(16, 10, 'CANT.', 1,0,'C',true);
                    $pdf->Cell(40, 10, 'IGUAL MEDIDA MENOR', 1,0,'C',true);
                    $pdf->Ln(); // Nueva línea3
                }
                    
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                    $pdf->MultiCell(35,0,$prod->claveEx,1,'C',false,0);
                    $pdf->MultiCell(81,0,$prod->descripcion,1,'C',false,0);
                    $pdf->MultiCell(18,0,$prod->nombreMedida,1,'C',false,0);
                    $pdf->MultiCell(16,0,$prod->cantidad,1,'C',false,0);
                    $pdf->MultiCell(40,0,$prod->igualMedidaMenor,1,'C',false,0);
                    $pdf->Ln(); // Nueva línea

                    if($contRegistros == 18){
                        $RegistroPorPagina = 25;
                        $contRegistros = $contRegistros + 7;
                    }

                    $contRegistros++;
            }

            $posY= $pdf->getY();

            if($posY > 241){
                $pdf->AddPage();
                $posY = 0;
            }

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(9,$posY+20);
            $pdf->MultiCell(0,10,'OBSERVACIONES: '. $traspaso->observaciones ,0,'L',false);

            $posY = $pdf->getY();

            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,$posY+5,200,$posY+5);//X1,Y1,X2,Y2

           

            $contenido = $pdf->Output('', 'I'); // Descarga el PDF con el nombre 'mi-archivo-pdf.pdf'
            $nombrepdf = 'mipdf.pdf';
        }else{


        }

        $nombreArchivo = '';
        return response($contenido)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"$nombreArchivo\"");


    }

    public function showMejorado($idTraspaso,$tipoTraspaso){
        if($tipoTraspaso == 'Envia'){
            $tabla = 'traspasoE';
            $tablaProd = 'productos_traspasoE';
        }elseif($tipoTraspaso == 'Recibe'){
            $tabla = 'traspasoR';
            $tablaProd = 'productos_traspasoR';
        }
        
        $traspaso = DB::table($tabla)
        ->join('sucursal as E','E.idSuc','=',$tabla.'.sucursalE')
        ->join('sucursal as R','R.idSuc','=',$tabla.'.sucursalR')
        ->join('empleado','empleado.idEmpleado','=',$tabla.'.idEmpleado')    
        ->join('statuss','statuss.idStatus','=',$tabla.'.idStatus')    
        ->select($tabla.'.*','E.nombre as sucursalEN','R.nombre as sucursalRN','statuss.nombre as nombreStatus',
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT('.$tabla.'.created_at, "%d/%m/%Y") as created_format'),
                    DB::raw('id'.$tabla.' as idTraspaso')
                )
        ->where([   [$tabla.'.id'.$tabla,'=',$idTraspaso]   ])
        ->get();

        $productosTraspaso = DB::table($tablaProd)
        ->join('producto','producto.idProducto','=',$tablaProd.'.idProducto')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=',$tablaProd.'.idProdMedida')
        ->select( $tablaProd.'.*','historialproductos_medidas.nombreMedida as nombreMedida' )
        ->where([
                    [$tablaProd.'.id'.$tabla,'=',$idTraspaso]
                ])
        ->get();

        if(is_object($traspaso)){
            $data = [
                'code'         => 200,
                'status'       => 'success',
                'traspaso'     => $traspaso,
                'productos'    => $productosTraspaso
            ];
        }else{
            $data = [
                'code'         => 400,
                'status'       => 'error',
                'message'      => 'El traspaso no existe'
            ];
        }
        return response()->json($data, $data['code']);
    }

    public function cancelarTraspaso(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if($params_array['tipoTraspaso'] == 'Envia'){
            $tabla = DB::table('traspasoE');
            $modelo = 'traspasoE';
            $tablaProd = DB::table('productos_traspasoE');
        }elseif($params_array['tipoTraspaso'] == 'Recibe'){
            $tabla = DB::table('traspasoR');
            $modelo = 'traspasoR';
            $tablaProd = DB::table('productos_traspasoR');
        }

        if( !empty($params_array)){
            
            //$statusTraspaso = $tabla::find($params_array['idTraspaso'])->idStatus; 
            // $statusTraspaso = DB::table($tabla)->select('idStatus')->where([['.id'.$tabla,'=',$params_array['idTraspaso']] ])->value('idStatus');
            $statusTraspaso = $tabla->select('idStatus')->where([['.id'.$modelo,'=',$params_array['idTraspaso']] ])->value('idStatus');
            
            if($statusTraspaso == 49){
                $data =  array(
                    'status'        => 'error',
                    'code'          =>  404,
                    'message'       =>  'El traspaso ya está cancelado'
                );
            }else{
                try{
                    DB::beginTransaction();
                    //Cambiamos status de compra a cancelada
                    $traspaso = $tabla->where('id'.$modelo,$params_array['idTraspaso'])->update([
                        'idStatus' => 49
                    ]);
                    
                    //Insertamos en monitoreo la cancelacion con su motivo
                    Monitoreo::insertMonitoreo(
                        $params_array['idEmpleado'],
                        'Cancelacion de traspaso',
                        null,
                        $params_array['idTraspaso'],
                        $params_array['motivo']
                    );

                    //Consultamos productos del traspaso
                    $productosT = $tablaProd->where('id'.$modelo,$params_array['idTraspaso'])->get()->toArray();
                    $productosTArray = json_decode(json_encode($productosT), true);
                    //Restamos la existencia e insertamos el movimiento del producto
                    foreach($productosTArray AS $paramdata ){
                        //Antes de actualizar el producto obtenemos su existenciaG, se realiza la operacion y se guarda
                        $Producto = Producto::find($paramdata['idProducto']);
                        $stockanterior = $Producto -> existenciaG;
                        if($params_array['tipoTraspaso'] == 'Envia'){
                            $Producto -> existenciaG = $Producto -> existenciaG + $paramdata['igualMedidaMenor'];
                            $accion = "Cancelación de traspaso Envía, ".$params_array['idTraspaso'].", se suma al inventario";
                        }elseif($params_array['tipoTraspaso'] == 'Recibe'){
                            $Producto -> existenciaG = $Producto -> existenciaG - $paramdata['igualMedidaMenor'];
                            $accion = "Cancelación de traspaso Recibe, ".$params_array['idTraspaso'].", se descuenta del inventario";
                        }
                        $Producto->save();//guardamos el modelo
                        //Obtenemos la existencia del producto actualizado
                        $stockactualizado = Producto::find($paramdata['idProducto'])->value('existenciaG');

                        //insertamos el movimiento de existencia del producto
                        moviproduc::insertMoviproduc(
                            $paramdata,
                            $accion,
                            $params_array['idTraspaso'],
                            $paramdata['igualMedidaMenor'],
                            $stockanterior,
                            $stockactualizado,
                            $params_array['idEmpleado'],
                            $_SERVER['REMOTE_ADDR']
                        );
                    }

                    $data =  array(
                        'status'            => 'success',
                        'code'              =>  200,
                        'message'           =>  'Cancelación de traspaso correcta!'
                    );

                    DB::commit();
                }catch(\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   =>  'Fallo algo',
                        'messageError' => $e -> getMessage(),
                        'error' => $e
                    );
                }
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

    public function updateTraspaso(Request $request){
        //Traspaso
        //tipoTraspaso
        //productosTraspaso
        //identity

        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        //Verificar si es local o foráneo
        $connection = DB::table('sucursal')->select('connection')->where('idSuc','=',$params_array['traspaso']['sucursalR'])->value('connection');
        //dd($connection);
        //if(count($connection) >= 1 && !empty($connection) && $params_array['tipoTraspaso'] == 'Envia'){
        if($connection != null){
            //si es local se consulta idStatuss en sucursalR
            //dd($params_array['traspaso']['sucursalE']);
            $idStatus = DB::connection($connection)->table('traspasoR')->select('idStatus')
                ->where([
                            ['folio','=',$params_array['traspaso']['idTraspasoE']],
                            ['sucursalE','=',$params_array['traspaso']['sucursalE']]
                        ])
                ->value('idStatus');
            
            if($idStatus == 50){
                //Llamar método de actualizacion en sucursalE
                $registroSucursalE = $this->updateSucursalE($params_array);
                //Llamar método de actualización en sucursalR
                $registroSucursalR = $this->updateSucursalR($params_array);

                $data = array(
                    'status'    =>  'success',
                    'code'      =>  200,
                    'message'   =>  'Traspaso Local actualizado',
                    'connection' => $connection,
                    'registroSucursalE' =>   $registroSucursalE,
                    'registroSucursalR' =>   $registroSucursalR
                );

            }else{

                $data= array(
                    'code'      =>  400,
                    'status'    =>  'Error!',
                    'message'   =>  'El traspaso no se puede modificar',
                    'idStatuss' =>  $idStatus
                ); 
            }

        }else{
            //Foraneo            
            //Llamar método de actualizacion en sucursalE
            $registroSucursalE = $this->updateSucursalE($params_array);

            $data = array(
                'status'    =>  'success',
                'code'      =>  200,
                'message'   =>  'Traspaso Foráneo actualizado',
                'connection' => $connection,
                'registroSucursalE' =>   $registroSucursalE
            );

        }

        //Return -> data
        return response()->json($data, $data['code']);     


    }

    //Actualización de información de traspaso en sucursal que envía o actualizacion de informacion de un traspaso que es de una sucursal
    public function updateSucursalE($params_array){
        //dd($params_array);
        if(!empty($params_array)){

            $validate = Validator::make($params_array['traspaso'], [
                'idTraspasoE'   => 'required',
                'sucursalE'     => 'required',
                'sucursalR'     => 'required',
                'idEmpleado'    => 'required'
            ]);
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message_system'   =>  'Fallo la validacion de los datos del traspaso',
                    'errors'    =>  $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();
                    DB::enableQueryLog();

                    //Compraracion de datos para saber que cambios se realizaron
                    $anTraspaso = TraspasoE::where('idTraspasoE',$params_array['traspaso']['idTraspasoE'])->get();
                    //Actualizamos
                    $traspaso = TraspasoE::where('idTraspasoE',$params_array['traspaso']['idTraspasoE'])->update([
                        'folio' => $params_array['traspaso']['folio'],
                        'sucursalE' => $params_array['traspaso']['sucursalE'],
                        'idStatus' => 40,
                        'observaciones' => $params_array['traspaso']['observaciones']
                    ]);
                    //Consultamos el traspaso que se actualizó
                    $traspaso = TraspasoE::where('idTraspasoE',$params_array['traspaso']['idTraspasoE'])->get();
                    //Obtnemos direción IP
                    $ip = $_SERVER['REMOTE_ADDR'];

                    //Recorremos el traspaso para ver que atributo cambio y asi guardar la modificación
                    foreach($anTraspaso[0]['attributes'] as $clave => $valor){
                        foreach($traspaso[0]['attributes'] as $clave2 => $valor2){
                           //verificamos que la clave sea igua ejem: claveEx == claveEx
                           // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                           if($clave == $clave2 && $valor !=  $valor2){
                               //insertamos el movimiento realizado
                               $monitoreo = new Monitoreo();
                               $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                               $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$valor2." del traspaso envia";
                               $monitoreo -> folioNuevo =  $params_array['traspaso']['idTraspasoE'];
                               $monitoreo -> pc =  $ip;
                               $monitoreo ->save();
                           }
                        }
                    }


                    //insertamos el movimiento que se hizo
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario = $params_array['identity']['sub'];
                    $monitoreo -> accion =  "Modificacion de traspaso";
                    $monitoreo -> folioNuevo =  $params_array['traspaso']['idTraspasoE'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    $producto_traspaso = $this->updateProductosSucursalE($params_array);

                    DB::commit();


                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Traspaso actualizado',
                        'traspaso' =>   $traspaso,
                        'producto_traspaso' => $producto_traspaso
                    );


                }catch (\Exception $e){
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
            $data= array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            ); 
        }
        return response()->json($data, $data['code']);     

    }

    public function updateProductosSucursalE($params_array){
        $productosTraspaso = $params_array['lista_producto_traspaso'];
        $tipoTraspaso = $params_array['tipoTraspaso'];
        $idTraspaso = $params_array['traspaso']['idTraspaso'];

        
        if(count($productosTraspaso) >= 1 && !empty($productosTraspaso)){
            try{
                DB::beginTransaction();
                //Creamos instancia para poder ocupar las funciones
                    $clsMedMen = new clsProducto();
                //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];
                //Comparar tipo de traspaso para saber en qué tabla trabajar
                if($tipoTraspaso == 'Envia'){
                    //Asignar idTraspaso a una variable para su uso
                        $idTraspaso = $params_array['traspaso']['idTraspasoE'];
                    //Obtener lista de productos del traspaso
                        $productosAnt = Productos_TraspasoE::where('idTraspasoE','=',$idTraspaso)->get();
                    //Se define acción a realizar para su inserción en moviproduc
                        $accion = "Modificación de traspaso Envía, ".$idTraspaso.", se suma al inventario";
                    //Eliminación de productos del traspaso
                        Productos_traspasoE::where('idTraspasoE',$idTraspaso)->delete();
                }elseif($tipoTraspaso == 'Recibe'){
                    //Asignar idTraspaso a una variable para su uso
                        $idTraspaso = $params_array['traspaso']['idTraspasoR'];
                    //Obtener lista de productos del traspaso
                        $productosAnt = Productos_TraspasoR::where('idTraspasoR','=',$idTraspaso)->get();           
                    //Se define acción a realizar para su inserción en moviproduc
                        $accion = "Modificación de traspaso Recibe, ".$idTraspaso.", se resta al inventario";
                    //Eliminación de productos del traspaso
                        Productos_traspasoR::where('idTraspasoR',$idTraspaso)->delete();
                }

                
                //Restar o agregar existencia antes de una modificacion
                foreach($productosAnt as $param => $paramdata){
                    
                    //Obtener producto
                        $Producto = Producto::find($paramdata['idProducto']);
                    //Obtener su existencia antes de actualizar
                        $stockAnterior = $Producto -> existenciaG;
                    //Actualizar existencia de acuerdo al tipo de traspaso
                    //Se reingresa si es un traspasoE  y se resta si es un traspasoR
                        if($tipoTraspaso == 'Envia'){
                            $Producto -> existenciaG = $Producto -> existenciaG + $paramdata['igualMedidaMenor'];
                        }elseif($tipoTraspaso == 'Recibe'){
                            $Producto -> existenciaG = $Producto -> existenciaG - $paramdata['igualMedidaMenor'];
                        }
                    //Guardar modelo
                        $Producto->save();
                    //Obtenemos la existencia del producto actualizado
                        $stockActualizado = Producto::find($paramdata['idProducto'])->existenciaG;
                    //insertamos el movimiento de existencia que se le realizo al producto
                        moviproduc::insertMoviproduc($paramdata,$accion,$idTraspaso,$paramdata['igualMedidaMenor'],
                        $stockAnterior,$stockActualizado,$params_array['identity']['sub'],
                        $_SERVER['REMOTE_ADDR']);

                }

                //Registro de productos del traspaso
                foreach($productosTraspaso as $param => $paramdata){

                    //Obtener producto 
                        $Producto = Producto::find($paramdata['idProducto']);
                    //Calcular su medida menor
                        $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$paramdata['cantidad']);
                    //Obtener su existencia antes de actualizar
                        $stockAnterior = $Producto -> existenciaG;
                    //Actualizar existencia de acuerdo al tipo de traspaso
                    //Se resta si es un traspasoE  y se suma si es un traspasoR
                        if($tipoTraspaso == 'Envia'){
                            $Producto -> existenciaG = $Producto -> existenciaG - $medidaMenor;
                            $accion = "Se guarda después de la modificación de traspaso Envía, ".$idTraspaso.", se resta al inventario";
                        }elseif($tipoTraspaso == 'Recibe'){
                            $Producto -> existenciaG = $Producto -> existenciaG + $medidaMenor;
                            $accion = "Se guarda después de la modificación de traspaso Recibe, ".$idTraspaso.", se suma al inventario";
                        }
                    //Guardar modelo
                        $Producto->save();
                    //Obtenemos la existencia del producto actualizado
                        $stockActualizado = Producto::find($paramdata['idProducto'])->existenciaG;
                    //insertamos el movimiento de existencia que se le realizo al producto
                        moviproduc::insertMoviproduc($paramdata,$accion,$idTraspaso,$medidaMenor,$stockAnterior,
                        $stockActualizado,$params_array['identity']['sub'],
                        $_SERVER['REMOTE_ADDR']);


                    //Agregamos los productos del traspaso
                    if($tipoTraspaso == 'Envia'){
                        $producto_traspaso = new Productos_traspasoE();
                        $producto_traspaso -> idTraspasoE = $idTraspaso;
                    }elseif($tipoTraspaso == 'Recibe'){
                        $producto_traspaso = new Productos_traspasoR();
                        $producto_traspaso -> idTraspasoR = $idTraspaso;
                    }

                    $producto_traspaso -> idProducto = $paramdata['idProducto'];
                    $producto_traspaso -> descripcion = $Producto -> descripcion;
                    $producto_traspaso -> claveEx = $Producto -> claveEx;
                    $producto_traspaso -> idProdMedida = $paramdata['idProdMedida'];
                    $producto_traspaso -> cantidad = $paramdata['cantidad'];
                    $producto_traspaso -> precio = $paramdata['precio'];
                    $producto_traspaso -> subtotal = $paramdata['subtotal'];
                    $producto_traspaso -> igualMedidaMenor = $medidaMenor;
                    $producto_traspaso -> save();
                    
                }

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Productos modificados correctamente'
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

    //Actualización de información de traspaso en sucursal que recibe
    public function updateSucursalR($params_array){
        //Traspaso
        //tipoTraspaso
        //productosTraspaso
        //identity

        //Asignando información del traspaso para su uso
            $idTraspasoE = $params_array['traspaso']['idTraspasoE'];
            $sucursalR = $params_array['traspaso']['sucursalR'];

        //Consulta de datos para enviar
        //Traspaso a enviar
            $Traspaso = DB::table('traspasoE')->where('idTraspasoE','=',$idTraspasoE)->first();
        //Productos del traspaso a enviar
            $productosTraspaso = DB::table('productos_traspasoE')->where('idTraspasoE','=',$idTraspasoE)->get();
            //dd($productosTraspaso);
        //Base de datos a la que nos vamos a conectar
            $connection = DB::table('sucursal')->select('connection')->where('idSuc','=',$sucursalR)->value('connection');
        //Nombre de sucursal que envía
            $sucursalE = DB::table('empresa')->select('nombreCorto')->value('nombreCorto');
        //Obtenemos IP
            $ip = $_SERVER['REMOTE_ADDR'];

        DB::connection($connection)->beginTransaction();
        try {
            //Consultamos información del traspaso antes de actualizarlo
                $TraspasoAnt = DB::connection($connection)->table('traspasoR')
                ->where([
                            ['folio','=',$Traspaso->idTraspasoE],
                            ['sucursalE','=',$Traspaso->sucursalE]
                        ])
                ->get();


            //Actualización de traspaso en tabla traspasoR en sucursal de destino
                
                DB::connection($connection)->table('traspasoR')
                ->where('idTraspasoR','=',$TraspasoAnt[0]->idTraspasoR)
                ->update([
                    'idStatus' => 50,
                    'observaciones' => $Traspaso->observaciones,
                    'updated_at' =>  Carbon::now()
                ]);

            //Consultar información actualizada
                $TraspasoAct = DB::connection($connection)->table('traspasoR')->where('idtraspasoR',$TraspasoAnt[0]->idTraspasoR)->get();
                $idTraspasoR = $TraspasoAct[0]->idTraspasoR;

            //Recorremos el traspaso para ver que atributo cambio y asi guardar la modificación
                foreach($TraspasoAnt[0] as $clave => $valor){
                    foreach($TraspasoAct[0] as $clave2 => $valor2){
                        //verificamos que la clave sea igual ejem: claveEx == claveEx
                        // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                            if($clave == $clave2 && $valor !=  $valor2){
                                //insertamos el movimiento realizado
                                    DB::connection($connection)->table('monitoreo')-> insert([
                                        'idUsuario' => $params_array['identity']['sub'],
                                        'accion' =>  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$valor2." del traspaso recibe",
                                        'folioNuevo' => $idTraspasoR,
                                        'pc' => $ip,
                                        'created_at' => Carbon::now(),
                                        'updated_at' =>  Carbon::now()
                                    ]);
                            }
                    }
                }
            
            // Insertamos movimiento en monitoreo de sucursal que recibe
                DB::connection($connection)->table('monitoreo')-> insert([
                    'idUsuario' => $params_array['identity']['sub'],
                    'accion' => "Modificación de traspaso, recibe, de ".$sucursalE,
                    'folioNuevo' => $idTraspasoR,
                    'pc' => $ip,
                    'created_at' => Carbon::now(),
                    'updated_at' =>  Carbon::now()
                ]);

            //Eliminamos los productos del traspaso para después agregarlos 
                DB::connection($connection)->table('productos_traspasoR')->where('idTraspasoR','=',$idTraspasoR)->delete();

            if(count($productosTraspaso) >= 1 && !empty($productosTraspaso)){
                
                foreach($productosTraspaso as $param => $paramdata){
                    //var_dump($paramdata->claveEx);
                    // die;
                    //Insertamos en tabla de prouductos_traspasoR de sucursal que recibe
                        DB::connection($connection)->table('productos_traspasor')->insert([
                            'idTraspasoR' => $idTraspasoR,
                            'idProducto' => $paramdata->idProducto,
                            'descripcion' => $paramdata->descripcion,
                            'claveEx' => $paramdata->claveEx,
                            'idProdMedida' => $paramdata->idProdMedida,
                            'cantidad' => $paramdata->cantidad,
                            'precio' => $paramdata->precio,
                            'subtotal' => $paramdata->subtotal,
                            'igualMedidaMenor' => $paramdata->igualMedidaMenor,
                            'created_at' => Carbon::now(),
                            'updated_at' =>  Carbon::now()
                        ]);                    
                }

            }else{
                $productosTraspaso = 'El traspaso no tiene productos';
            }   

            DB::connection($connection)->commit();

            $TraspasoN =  DB::connection($connection)->table('traspasoR')->where('idtraspasoR',$idTraspasoR)->get();
            //Antes de eliminar consultamos productos del traspaso en la sucursalR
            $productosTraspasoN = DB::connection($connection)->table('productos_traspasoR')->where('idTraspasoR',$idTraspasoR)->get();

            $data =  array(
                'code'      =>  200,
                'status'    =>  'success',
                'message'   =>  '',
                'Traspaso'  =>  $Traspaso,
                'productos' =>  $productosTraspaso,
                'connection' => $connection,
                'TraspasoN' =>  $TraspasoN,
                'productosN' =>  $productosTraspasoN
            );
            
        } catch (\Exception $e) {
            DB::connection($connection)->rollback();
            $data =  array(
                'code'    => 400,
                'status'  => 'error',
                'message' => 'Fallo al registrar el traspaso en la sucursal que recibe',
                'error'   => $e
            );
            
        }

        return $data;


        

        

        
    }

    public function recibirTraspaso(Request $request){

        
        //idtraspaso
        //Sucursal
        
        //consultar informacion del traspaso en suc origen y comparar con la información almacenada
        //Si es diferente -> no hacer nada 
        //Si es igual, continuar
        //Tomar campo igual medida menor de cada producto y sumarlo a la existenciaG
        
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        
        $tabla = 'traspasoR';
        $tablaProd = 'productos_traspasoR';
        $idTraspaso = $params_array['idTraspaso'];
        $idEmpleado = $params_array['idEmpleado'];
        $observaciones = $params_array['observaciones'];

        if(!empty($params_array)){
            //Obtener información del traspaso en sucursal destino
                $TraspasoSR = DB::table($tabla)
                ->select($tabla.'.*')
                ->where([   [$tabla.'.id'.$tabla,'=',$idTraspaso]   ])
                ->first();
            
            //Validación de status del traspaso en la sucursal que recibe, si es = 43 no se hace nada
            if($TraspasoSR->idStatus == 43){
                $data= array(
                    'code'      =>  400,
                    'status'    => 'Error!',
                    'message'   =>  'El traspaso ya fue ingresado'
                );

            }else{
                    
                    $productosTraspasoSR = DB::table($tablaProd)
                    ->select( $tablaProd.'.*')
                    ->where([
                                [$tablaProd.'.id'.$tabla,'=',$idTraspaso]
                            ])
                    ->get()
                    ->map(function($productosTraspasoSR)use($TraspasoSR){
                        $productosTraspasoSR->idTraspasoE = $TraspasoSR->folio;
                        return $productosTraspasoSR;
                    });

                //Obtener connection
                    $connection = DB::table('sucursal')->select('connection')->where('idSuc','=',$TraspasoSR->sucursalE)->value('connection');
                    
                //Consultar la informacion del traspaso en sucursal que envía
                    $TraspasoSE =  DB::connection($connection)->table('traspasoE')->where('idtraspasoE',$TraspasoSR->folio)->first();
                    $productosTraspasoSE = DB::connection($connection)->table('productos_traspasoE')->where('idTraspasoE',$TraspasoSR->folio)->get()
                    ->map(function($productosTraspasoSE)use($TraspasoSR){
                        $productosTraspasoSE->idTraspasoR = $TraspasoSR->idTraspasoR;
                        return $productosTraspasoSE;
                    });

                //Variable para almacenar cuántas diferencias existen entre los datos
                    $diferencias = false;
                    // dd($productosTraspasoSR,$productosTraspasoSE);
                    // dd($productosTraspasoSE);
                //Comparar informacion del traspaso para verificar que ambas sucursales tengan la misma información
                    if(count($productosTraspasoSR) == count($productosTraspasoSE)){
                        for($i=0;$i < count($productosTraspasoSR);$i++){
                            // dd($productosTraspasoSR[$i]->idTraspasoR);
                            if($productosTraspasoSR[$i]->idProducto != $productosTraspasoSE[$i]->idProducto  ||
                                $productosTraspasoSR[$i]->idProdMedida != $productosTraspasoSE[$i]->idProdMedida  ||
                                $productosTraspasoSR[$i]->cantidad != $productosTraspasoSE[$i]->cantidad ||
                                $productosTraspasoSR[$i]->igualMedidaMenor != $productosTraspasoSE[$i]->igualMedidaMenor){
                                    $diferencias = true;
                            }

                        }
                    }
                    
                //Evaluar el resultado de la comparación, si existen regresar error y no hacer nada
                    if($diferencias == false){
                        
                        try {
                            
                            DB::beginTransaction();
                            //dd($productosTraspasoSR);

                            //Se hace el ingreso de los productos
                                foreach($productosTraspasoSR as $param => $paramdata){
                                    //asignamos medida menor
                                        $medidaMenor = $paramdata->igualMedidaMenor;
                
                                    //Consultamos la existencia antes de actualizar
                                        $Producto = Producto::find($paramdata->idProducto);
                                        $stockAnterior = $Producto -> existenciaG;
                
                                    //Actualizamos existenciaG
                                        $Producto -> existenciaG = $Producto -> existenciaG + $medidaMenor;

                                    //Guardamos el modelo
                                        $Producto -> save();
                
                                    //Consultamos la existencia despues de actualizar
                                        $stockActualizado = $Producto->existenciaG;

                                    $paramdataArray = get_object_vars($paramdata);

                                    //insertamos el movimiento de existencia que se le realizo al producto
                                        moviproduc::insertMoviproduc($paramdataArray,
                                        $accion = "Ingreso de traspaso, RECIBE",$idTraspaso,$medidaMenor,
                                        $stockAnterior,$stockActualizado,$idEmpleado,
                                        $_SERVER['REMOTE_ADDR']);
                                    
                                }

                            //Registramos acción en monitoreo
                                Monitoreo::insertMonitoreo(
                                    $idEmpleado,
                                    $accion = "Ingreso de trapaso RECIBE",
                                    null,
                                    $idTraspaso,
                                    null
                                );

                            //Actualizamos
                                $Traspaso = TraspasoR::where('idTraspasoR',$idTraspaso)
                                                ->update([
                                                    'idStatus' => 43,
                                                    'observaciones' => $observaciones
                                                ]);

                            //Registramos acción en monitoreo
                                Monitoreo::insertMonitoreo(
                                    $idEmpleado,
                                    $accion = "Modificación de status por recepción de traspaso RECIBE ".$idTraspaso,
                                    null,
                                    $idTraspaso,
                                    null
                                );
                            
                            DB::commit();

                            //Actualización de statuss y folio en sucursalE
                            DB::connection($connection)->beginTransaction();
                            DB::connection($connection)->table('traspasoE')
                                ->where('idTraspasoE','=',$TraspasoSR->folio)
                                ->update([
                                    'folio' => $idTraspaso,
                                    'idStatus' => 50,
                                    'updated_at' =>  Carbon::now()
                                ]);
                            DB::connection($connection)->commit();
                        
                            $data =  array(
                                'code'      =>  200,
                                'status'    =>  'success',
                                'message'   =>  'Traspaso ingresado correctamente',
                                'TraspasoSR'  =>  $TraspasoSR,
                                'productosTraspasoSR' =>  $productosTraspasoSR,
                                'connection' => $connection,
                                'TraspasoSE' =>  $TraspasoSE,
                                'productosTraspasoSE' =>  $productosTraspasoSE
                            );

                        } catch (\Exception $e) {
                            DB::rollback();
                            $data =  array(
                                'code'    => 400,
                                'status'  => 'error',
                                'message' => 'Fallo al ingresar traspaso a la sucursal',
                                'error'   => $e
                            );
                            
                        }

                    }else{
                        $data= array(
                            'code'      =>  400,
                            'status'    => 'Error!',
                            'message'   =>  'Diferencias encontradas en los productos del traspaso',
                            'TraspasoSR'  =>  $TraspasoSR,
                            'productosTraspasoSR' =>  $productosTraspasoSR,
                            'connection' => $connection,
                            'TraspasoSE' =>  $TraspasoSE,
                            'productosTraspasoSE' =>  $productosTraspasoSE

                        );
                    }
            }

        }else{
            $data= array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);


        
    }
}
