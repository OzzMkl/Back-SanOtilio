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

    public function index($tipoTrasp, Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);
        
        if(!empty($tipoTrasp) && strlen($params_array['str_traspaso']) >= 1){

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
                    $tipoTraspaso.'.id'.$tipoTraspaso,'like','%'.$params_array['str_traspaso'].'%',
                    
                ]
            ])
            ->paginate(10);

            $data= array(
                'code'     =>  200,
               'status'    => 'success',
               'traspasos' => $traspaso
            );
            
        } elseif(!empty($tipoTrasp) && strlen($params_array['str_traspaso']) == 0 ){
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
        //var_dump($params_array);
        //die();

        if(!empty($params_array)){

            if($params_array['tipoTraspaso']== 'Envia'){
                $validate = Validator::make($params_array['traspaso'],[
                    'sucursalE' =>  'required',
                    'sucursalR' =>  'required'
                ]);
            }elseif($params_array['tipoTraspaso']== 'Recibe'){
                $validate = Validator::make($params_array['traspaso'],[
                    'sucursalE' =>  'required',
                    'sucursalR' =>  'required',
                    'folio'     =>  'required|unique:traspasoR'
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
                $Traspaso = traspasor::latest('idTraspasoR')->get();
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

                DB::commit();

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Traspaso registrado correctamente',
                    'traspaso' => $traspasoNuevo
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
                    if($tipoTraspaso == 'Envia'){
                        $Producto -> existenciaG = $Producto -> existenciaG - $medidaMenor;
                    }elseif($tipoTraspaso == 'Recibe'){
                        $Producto -> existenciaG = $Producto -> existenciaG + $medidaMenor;
                    }
                    $Producto -> save();


                    //Consultamos la existencia despues de actualizar
                    $stockActualizado = $Producto->existenciaG;

                    //insertamos el movimiento de existencia que se le realizo al producto
                    moviproduc::insertMoviproduc($paramdata,$accion = "Alta de traspaso, ".$tipoTraspaso,
                                                $Traspaso,$medidaMenor,$stockAnterior,$stockActualizado,$idEmpleado);

                    //Agregamos los productos del traspaso
                    if($tipoTraspaso == 'Envia'){
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

    public function generatePDF($idTraspaso,$idEmpleado,$tipoTraspaso){
        //dd($idTraspaso,$idEmpleado,$tipoTraspaso);
        $Empresa = Empresa::first();

        if($tipoTraspaso == 'Envia'){
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
                            $params_array['idEmpleado']
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

}
