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
use App\models\Empresa;
use TCPDF;
use App\models\Monitoreo;
use App\OrdenDeCompra;
use App\Clases\clsProducto;
use App\Producto;

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
                        $Requisicion->idProveedor = $params_array['idProveedor'];
                        $Requisicion->observaciones = $params_array['observaciones'];
                        $Requisicion->idEmpleado = $params_array['idEmpleado'];
                        $Requisicion->idStatus = 29;

                        $Requisicion->save();

                        $data = array(
                            'status'    =>  'success',
                            'code'      =>  200,
                            'message'   =>  'Requisicion creada pero sin productos'
                        );

                        //obtenemos folio
                        $FolioRequisicion = Requisiciones::latest('idReq')->first()->idReq;
                        //obtenemos direccion ip
                        $ip = $_SERVER['REMOTE_ADDR'];
                        
                        //insertamos el movimiento que se hizo en general
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                        $monitoreo -> accion =  "Alta de requisicion";
                        $monitoreo -> folioNuevo =  $FolioRequisicion;
                        $monitoreo -> pc =  $ip;
                        $monitoreo ->save();

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
                            while($idProdMedidaC != $listaPM[$lugar]->idProdMedida){
                                //echo $listaPM[$lugar]->idProdMedida;
                                //echo $lugar;
                                $lugar++;
                            }
                            if($lugar == $count-1){//Si la medida de compra a ingresar es la medida mas baja ingresar directo ( lugar == count-1 )
                                $Productos_requisicion-> igualMedidaMenor = $cantidadC;
                            }elseif($lugar == 0){//Medida mas alta, multiplicar desde el principio ( lugar == 0)
                                $igualMedidaMenor = $cantidadC;
                                while($lugar < $count ){
                                    $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar]->unidad;
                                    $lugar++;
                                    //echo $igualMedidaMenor;
                                }
                                $Productos_requisicion-> igualMedidaMenor = $igualMedidaMenor;
                            }elseif($lugar>0 && $lugar<$count-1){//Medida [1] a [3] multiplicar en diagonal hacia abajo ( lugar > 0 && lugar < count-1 )
                                $igualMedidaMenor = $cantidadC;
                                $count--;
                                //echo $count;
                                while($lugar < $count ){
                                    $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]->unidad;
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
            ->leftjoin('proveedores','proveedores.idProveedor','=','requisicion.idProveedor')
            ->select('requisicion.*',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                        'proveedores.nombre as nombreProveedor')
            ->where('requisicion.idReq','=',$idReq)
            ->get();

        $productosRequisicion = DB::table('productos_requisiciones')
            ->join('producto','producto.idProducto','=','productos_requisiciones.idProducto')
            ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_requisiciones.idProdMedida')
            ->join('marca','marca.idMarca','=','producto.idMarca')
            ->join('departamentos','departamentos.idDep','=','producto.idDep')
            ->select('productos_requisiciones.*','producto.claveEx as claveEx','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida',
                        'marca.nombre as marca','departamentos.nombre as departamento'
                    )
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

    public function generatePDF($idReq,$idEmpleado){



        $Empresa = Empresa::first();

        $Requisicion = DB::table('requisicion')
            ->join('empleado','empleado.idEmpleado','=','requisicion.idEmpleado')
            ->leftjoin('proveedores','proveedores.idProveedor','=','requisicion.idProveedor')
            ->select('requisicion.*',
                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                        DB::raw('DATE_FORMAT(requisicion.created_at, "%d/%m/%Y") as created_at'),
                        'proveedores.nombre as nombreProveedor'
                    )

            ->where('requisicion.idReq','=',$idReq)
            ->first();

        $productosRequisicion = DB::table('productos_requisiciones')
        ->join('producto','producto.idProducto','=','productos_requisiciones.idProducto')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_requisiciones.idProdMedida')
        ->join('marca','marca.idMarca','=','producto.idMarca')
        ->join('departamentos','departamentos.idDep','=','producto.idDep')
        ->select('productos_requisiciones.*','producto.claveEx as claveexterna','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida',
                    'marca.nombre as marca','departamentos.nombre as departamento'
                )
        ->where('productos_requisiciones.idReq','=',$idReq)
        ->get();

        if(is_object($Requisicion)){

            //obtenemos direccion ip
            $ip = $_SERVER['REMOTE_ADDR'];
            //insertamos el movimiento que se hizo en general
            $monitoreo = new Monitoreo();
            $monitoreo -> idUsuario =  $idEmpleado;
            $monitoreo -> accion =  "Impresión de PDF, requisicion";
            $monitoreo -> folioNuevo =  $Requisicion->idReq;
            $monitoreo -> pc =  $ip;
            $monitoreo ->save();
 
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
            $pdf->Cell(0, 10, 'REQUISICION #'. $Requisicion->idReq, 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,38);
            $pdf->Cell(0, 10, 'PROVEEDOR: '. strtoupper($Requisicion->nombreProveedor), 0, 1); // Agrega un texto

            $pdf->setXY(157,38);
            $pdf->Cell(0, 10, 'FECHA: '. substr($Requisicion->created_at,0,10), 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,43);
            $pdf->Cell(0, 10, 'EMPLEADO: '. strtoupper($Requisicion->nombreEmpleado), 0, 1); // Agrega un texto


            $mytime = date('d/m/Y H:i:s');
            $pdf->setXY(153,43);
            $pdf->Cell(0, 10, 'IMPRESO: '. $mytime, 0, 1); // Agrega un texto


            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,52,200,52);//X1,Y1,X2,Y2

            $pdf->SetDrawColor(0,0,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(.2);//grosor de la linea
            $pdf->SetFillColor(7, 149, 223  );//Creamos color de relleno para la tabla
            $pdf->setXY(10,62);

            //Contamos el numero de productos
            $numRegistros = count($productosRequisicion);
            //establecemos limite de productos por pagina
            $RegistroPorPagina = 18;
            //calculamos cuantas paginas van hacer
            $paginas = ceil($numRegistros / $RegistroPorPagina);
            $contRegistros = 0;

            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            //INSERTAMOS CABECERAS TABLA
            $pdf->Cell(29,10,'CLAVE EXTERNA',1,0,'C',true);
            $pdf->Cell(70, 10, 'DESCRIPCION', 1,0,'C',true);
            $pdf->Cell(16, 10, 'MEDIDA', 1,0,'C',true);
            $pdf->Cell(16, 10, 'CANT.', 1,0,'C',true);
            $pdf->Cell(25, 10, 'MARCA', 1,0,'C',true);
            $pdf->Cell(34, 10, 'DEPARTAMENTO', 1,0,'C',true);
            $pdf->Ln(); // Nueva línea3

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 10); // Establece la fuente

            //REALIZAMOS RECORRIDO DEL ARRAY DE PRODUCTOS
            foreach($productosRequisicion as $prodC){
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
                    $pdf->Cell(29,10,'CLAVE EXTERNA',1,0,'C',true);
                    $pdf->Cell(70, 10, 'DESCRIPCION', 1,0,'C',true);
                    $pdf->Cell(16, 10, 'MEDIDA', 1,0,'C',true);
                    $pdf->Cell(16, 10, 'CANT.', 1,0,'C',true);
                    $pdf->Cell(25, 10, 'MARCA', 1,0,'C',true);
                    $pdf->Cell(34, 10, 'DEPARTAMENTO', 1,0,'C',true);
                    $pdf->Ln(); // Nueva línea3
                }

                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                    $pdf->MultiCell(29,10,$prodC->claveexterna,1,'C',false,0);
                    $pdf->MultiCell(70,10,$prodC->descripcion,1,'C',false,0);
                    $pdf->MultiCell(16,10,$prodC->nombreMedida,1,'C',false,0);
                    $pdf->MultiCell(16,10,$prodC->cantidad,1,'C',false,0);
                    $pdf->MultiCell(25,10,$prodC->marca,1,'C',false,0);
                    $pdf->MultiCell(34,10,$prodC->departamento,1,'C',false,0);
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
            $pdf->setXY(9,$posY+10);
            $pdf->MultiCell(0,10,'OBSERVACIONES: '. $Requisicion->observaciones ,0,'L',false);

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

    public function listaRequisiciones($tipoRequisicion,$str_requisicion){
        

        if(!empty($tipoRequisicion)){
            

             switch ($tipoRequisicion) {
                case 'Recibidas':
                    $idStatus = 29;
                    break;
                
                case 'Rechazadas':
                    $idStatus = 51;
                    break;

                case 'Aceptadas':
                    $idStatus = 37;
                    break;

                case 'Canceladas':
                    $idStatus = 30;
                    break;
                
                //Default Recibidas
                default: 
                    $idStatus = 29;
                    break;
             }

            if($str_requisicion != 'vacio'){
                $Requisicion = DB::table('requisicion')
                ->join('empleado','empleado.idEmpleado','requisicion.idEmpleado')
                ->select('requisicion.*',
                            DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                ->where([
                   [ 'requisicion.idStatus','=',$idStatus],
                    ['requisicion.idReq','=',$str_requisicion]
                ])
                ->orderBy('requisicion.idReq','desc')
                ->paginate(10);
            
            } elseif($str_requisicion == 'vacio' ){
                $Requisicion = DB::table('requisicion')
                ->join('empleado','empleado.idEmpleado','requisicion.idEmpleado')
                ->select('requisicion.*',
                            DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))

                ->where('requisicion.idStatus','=',$idStatus)
                ->orderBy('requisicion.idReq','desc')
                ->paginate(10);

            }
        }else{
            $data= array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }

        // $Requisicion = DB::table('requisicion')
        // ->join('empleado','empleado.idEmpleado','=','requisicion.idEmpleado')
        // ->select('requisicion.*',
        //             DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        // ->where('requisicion.idStatus','=',29)
        // ->orwhere('requisicion.idStatus','=',36)
        // ->orderBy('requisicion.idReq','desc')
        // ->paginate(10);

        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'requisicion'   =>  $Requisicion
        ]);
    }

    public function updateRequisicion($idReq, Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json,true);
        // var_dump($params_array);
        // die();
        if(!empty($params_array)){
            //eliminar espacios vacios
            //$params_array = array_map('trim', $params_array);
            //Validacion de datos
            $validate = Validator::make($params_array, [
                'idReq'         => 'required',
                'idEmpleado'    => 'required',
                'idStatus'      => 'required'
            ]);
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message_system'   =>  'Fallo la validacion de los datos de la requisicion',
                    //'message_validation' => $validate->getMessage(),
                    'errors'    =>  $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();
                    DB::enableQueryLog();

                    //Comparacion de datos para saber que cambios se realizaron
                    $antReq = Requisiciones::find($params_array['idReq']);

                    //actualizamos
                    $Requisicion = Requisiciones::where('idReq',$params_array['idReq'])->update([
                        'idStatus'       => $params_array['idStatus'],
                        'observaciones'  => $params_array['observaciones'],
                        'idProveedor'    => $params_array['idProveedor']??null
                    ]);

                    //consultamos la requisicion que se actualizo
                    $requisicion = Requisiciones::find($params_array['idReq'])  ;

                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];

                    //recorremos la requisicion para ver que atributo cambio y asi guardar la modificacion
                    foreach($antReq->getAttributes() as $clave => $valor){
                           //verificamos que la clave sea igua ejem: claveEx == claveEx
                           // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                            if(array_key_exists($clave,$requisicion->getAttributes()) && $valor != $requisicion->$clave){
                               //insertamos el movimiento realizado
                               $monitoreo = new Monitoreo();
                               $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                               $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$requisicion->$clave." de la requiscion";
                               $monitoreo -> folioNuevo =  $params_array['idReq'];
                               $monitoreo -> pc =  $ip;
                               $monitoreo ->save();
                           }   
                    }


                    //insertamos el movimiento que se hizo
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario = $params_array['idEmpleado'] ;
                    $monitoreo -> accion =  "Modificacion de requisicion";
                    $monitoreo -> folioNuevo =  $params_array['idReq'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    $data = array(
                        'code'        =>  200,
                        'status'      =>  'success',
                        'message'     =>  'Requisicion actualizada',
                        'requisicion' => $requisicion
                    );

                    /****** */
                    DB::commit();
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
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Json vacio'
            );
        }
        return response()->json($data, $data['code']);


    }

    public function updateProductosReq($idReq, Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){//verificamos que no este vacio

            //eliminamos los registros que tengab ese idOrd
            Productos_requisiciones::where('idReq',$idReq)->delete();
            //recorremos el array para asignar todos los productos
            foreach($params_array AS $param => $paramdata){
                $Productos_requisicion = new Productos_requisiciones();//creamos el modelo
                $Productos_requisicion->idReq = $idReq;//asignamos el id desde el parametro que recibimos
                $Productos_requisicion-> idProducto = $paramdata['idProducto'];//asginamos segun el recorrido
                $Productos_requisicion-> idProdMedida = $paramdata['idProdMedida'];
                $Productos_requisicion-> cantidad = $paramdata['cantidad'];

                //Creamos instancia para poder ocupar las funciones
                $clsMedMen = new clsProducto();

                //calculamos medida menor
                $medidaMenor = $clsMedMen->cantidad_En_MedidaMenor($paramdata['idProducto'],$paramdata['idProdMedida'],$paramdata['cantidad']);

                $Productos_requisicion-> igualMedidaMenor = $medidaMenor;
                $Productos_requisicion->save();//guardamos el modelo
                //Si todo es correcto mandamos el ultimo producto insertado
            }
            $data =  array(
                'status'            => 'success',
                'code'              =>  200,
                'message'           =>  'Eliminacion e insercion correcta!',
                'Productos_orden'   =>  $Productos_requisicion
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

    public function deshabilitarReq($idReq,$idEmpleado){
        try{
                DB::beginTransaction();
                DB::enableQueryLog();
                //Comparacion de datos para saber que cambios se realizaron
                $antReq = Requisiciones::find($idReq);

                //actualizamos
                $Requisicion = Requisiciones::where('idReq',$idReq)->update([
                    'idStatus' => 30
                ]);

                //consultamos la requisicion que se actualizo
                $requisicion = Requisiciones::find($idReq)  ;

                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //recorremos el producto para ver que atributo cambio y asi guardar la modificacion
                foreach($antReq->getAttributes() as $clave => $valor){
                       //verificamos que la clave sea igua ejem: claveEx == claveEx
                       // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                       if(array_key_exists($clave,$requisicion->getAttributes()) && $valor != $requisicion->$clave){
                           //insertamos el movimiento realizado
                           $monitoreo = new Monitoreo();
                           $monitoreo -> idUsuario =  $idEmpleado;
                           $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$requisicion->$clave." de la requiscion";
                           $monitoreo -> folioNuevo =  $idReq;
                           $monitoreo -> pc =  $ip;
                           $monitoreo ->save();
                       }
                    
                }

                //insertamos el movimiento que se hizo
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario = $idEmpleado;
                $monitoreo -> accion =  "Cancelación de requisicion";
                $monitoreo -> folioNuevo =  $idReq;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                $data = array(
                    'code'      =>  200,
                    'status'    =>  'success',
                    'message'   =>  'Requisicion cancelada'
                );

                /****** */
                DB::commit();

        }catch (\Exception $e){
            DB::rollBack();
            $data = array(
                'code'      => 400,
                'status'    => 'Error',
                'message'   => $e->getMessage(),
                'error'     => $e
            );
        }

        return response()->json($data, $data['code']);
    }

    public function generarOrden(Request $request){//Recibe un array con uno o varios id de requisicion para generar una orden de compra

        $json = $request -> input('json');//recogemos los datos enviados por post en formato json
        $listaReq = explode(',',$json);
        if(!empty($listaReq)){//verificamos que no este vacio
            $ListaProductos = 0;
            //Obtenemos los productos de la(s) requisiciones
            $ListaProductos = DB::table('productos_requisiciones')
            ->join('producto','producto.idProducto','=','productos_requisiciones.idProducto')
            ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_requisiciones.idProdMedida')
            ->join('marca','marca.idMarca','=','producto.idMarca')
            ->join('departamentos','departamentos.idDep','=','producto.idDep')
            ->select('productos_requisiciones.*','producto.claveEx as claveEx','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida',
                    'marca.nombre as marca','departamentos.nombre as departamento'
                    )
            ->whereIn('productos_requisiciones.idReq', $listaReq)
            ->get();
            $ListaProductos = $ListaProductos->toArray();
            $len = count($ListaProductos);
            //var_dump($ListaProductos);
            $ListaCompras = [];
            if($len > 1){
                do{
                    $primerProducto = $ListaProductos[0];
                    array_splice($ListaProductos,0,1);
                    $len--;
                    foreach($ListaProductos as $clave){
                        $k = 0;
                        //Se buscan los productos iguales en el array para sumarlos
                        if(intval($primerProducto->idProducto) == $clave->idProducto){
                            //echo "sientro"."<br>";
                            //Se suma igualMedidaMenor+igualMedidaMenor
                            $primerProducto->igualMedidaMenor = $primerProducto->igualMedidaMenor + $clave->igualMedidaMenor;
                            //si la medida es la misma sumar cantidad+cantidad
                            if(intval($primerProducto->idProdMedida) == $clave->idProdMedida){
                                //echo "mismo idProdMedida"."<br>";
                                $primerProducto->cantidad = $primerProducto->cantidad + $clave->cantidad;
                            }else{
                                //Si la medida no es la misma entonces se calcula la cantidad con base en igualMedidaMenor

                                //Consulta para saber cuantas medidas tiene un producto
                                $count = Productos_medidas::where([
                                    ['productos_medidas.idProducto','=',$primerProducto->idProducto],
                                    ['productos_medidas.idStatus','=','31']
                                ])->count();
                                //Consulta para obtener la lista de productos_medidas de un producto
                                $listaPM = Productos_medidas::where([
                                        ['productos_medidas.idProducto','=',$primerProducto->idProducto],
                                        ['productos_medidas.idStatus','=','31']
                                    ])->get();

                                if($count == 1){//Si tiene una sola medida se suma directo
                                    //echo "diferente idProdMedida, 1 sola medida"."<br>";
                                    $primerProducto->cantidad = $primerProducto->cantidad + $clave->cantidad;
                                }else{//si tiene 2 medidas o mas se calcula la cantidad en medida mayor
                                    //echo "diferente idProdMedida, 2 o mas medidas"."<br>";
                                    $primerProducto->cantidad = $primerProducto->igualMedidaMenor;
                                    foreach($listaPM as $clave2){
                                        $primerProducto->cantidad = $primerProducto->cantidad / $clave2->unidad;
                                        //echo $primerProducto->cantidad."<br>";
                                    }
                                }
                            }
                            //Una vez terminado el calculo se elimina el producto encontrado y se continua con la busqueda
                            if($k == count($ListaProductos)){
                            }else{
                                array_splice($ListaProductos,$k,1);
                                $len--;
                            }
                            //var_dump($ListaCompras);
                        }else{
                        }
                        $k++;
                    }
                    $ListaCompras[]= $primerProducto;
                    //var_dump($ListaCompras);

                }while($len>0);
            }else{
                $ListaCompras = $ListaProductos;
            }

            //Al terminar la busqueda regresamos la lista de compras
            //var_dump($ListaCompras);
            //die();
            $data =  array(
                    'status'            => 'success',
                    'code'              =>  200,
                    'message'           =>  'Requisicion convertida exitosamente! 2',
                    'ListaCompras'      =>  $ListaCompras
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


    public function updateidOrden(Request $request){//Recibe un array con uno o varios id de requisicion para actualizar sus idOrden y el idOrden
        $json = $request -> input('json');//recogemos los datos enviados por post en formato json
        $listaReq = explode(',',$json);
        if(!empty($listaReq)){//verificamos que no este vacio
            //obtenemos idOrd
            $idOrd = OrdenDeCompra::latest('idOrd')->first()->idOrd;
            foreach($listaReq as $clave){
                $Requisicion = Requisiciones::where('idReq',$clave)->update([
                    'idOrd'       => $idOrd,
                    'idStatus'    => 37
                ]);
            }
            //var_dump($ListaCompras);
            $data =  array(
                    'status'            => 'success!',
                    'code'              =>  200,
                    'message'           =>  'Requisiciones actualizadas exitosamente! idOrd'
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

    public function aceptarReq($idReq,$idEmpleado){
        try{
                DB::beginTransaction();
                DB::enableQueryLog();
                //Comparacion de datos para saber que cambios se realizaron
                $antReq = Requisiciones::find($idReq);

                //actualizamos
                $Requisicion = Requisiciones::where('idReq',$idReq)->update([
                    'idStatus' => 37
                ]);

                //consultamos la requisicion que se actualizo
                $requisicion = Requisiciones::find($idReq)  ;

                //obtenemos direccion ip|
                $ip = $_SERVER['REMOTE_ADDR'];

                //recorremos el producto para ver que atributo cambio y asi guardar la modificacion
                foreach($antReq->getAttributes() as $clave => $valor){
                       //verificamos que la clave sea igua ejem: claveEx == claveEx
                       // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                       if(array_key_exists($clave,$requisicion->getAttributes()) && $valor != $requisicion->$clave){
                           //insertamos el movimiento realizado
                           $monitoreo = new Monitoreo();
                           $monitoreo -> idUsuario =  $idEmpleado;
                           $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$requisicion->$clave." de la requiscion";
                           $monitoreo -> folioNuevo =  $idReq;
                           $monitoreo -> pc =  $ip;
                           $monitoreo ->save();
                       }            
                }

                //insertamos el movimiento que se hizo
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario = $idEmpleado;
                $monitoreo -> accion =  "Acepta la requisicion";
                $monitoreo -> folioNuevo =  $idReq;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                $data = array(
                    'code'      =>  200,
                    'status'    =>  'success',
                    'message'   =>  'Requisicion aceptada'
                );

                /****** */
                DB::commit();

        }catch (\Exception $e){
            DB::rollBack();
            $data = array(
                'code'      => 400,
                'status'    => 'Error',
                'message'   => $e->getMessage(),
                'error'     => $e
            );
        }

        return response()->json($data, $data['code']);
    }

    public function rechazarReq($idReq,$idEmpleado){
        try{
                DB::beginTransaction();
                DB::enableQueryLog();
                //Comparacion de datos para saber que cambios se realizaron
                $antReq = Requisiciones::find($idReq);

                //actualizamos
                $Requisicion = Requisiciones::where('idReq',$idReq)->update([
                    'idStatus' => 51
                ]);

                //consultamos la requisicion que se actualizo
                $requisicion = Requisiciones::find($idReq)  ;

                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //recorremos el producto para ver que atributo cambio y asi guardar la modificacion
                foreach($antReq->getAttributes() as $clave => $valor){
                       //verificamos que la clave sea igua ejem: claveEx == claveEx
                       // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                        if(array_key_exists($clave,$requisicion->getAttributes()) && $valor != $requisicion->$clave){
                            //insertamos el movimiento realizado
                           $monitoreo = new Monitoreo();
                           $monitoreo -> idUsuario =  $idEmpleado;
                           $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$requisicion->$clave." de la requiscion";
                           $monitoreo -> folioNuevo =  $idReq;
                           $monitoreo -> pc =  $ip;
                           $monitoreo ->save();
                       }
                    
                }

                //insertamos el movimiento que se hizo
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario = $idEmpleado;
                $monitoreo -> accion =  "Rechaza la requisicion";
                $monitoreo -> folioNuevo =  $idReq;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                $data = array(
                    'code'      =>  200,
                    'status'    =>  'success',
                    'message'   =>  'Requisicion rechazada'
                );

                /****** */
                DB::commit();

        }catch (\Exception $e){
            DB::rollBack();
            $data = array(
                'code'      => 400,
                'status'    => 'Error',
                'message'   => $e->getMessage(),
                'error'     => $e
            );
        }

        return response()->json($data, $data['code']);
    }

}
