<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Caja;
use App\Caja_movimientos;
use App\models\Ventasg;
use App\models\Abono_venta;
use Validator;
use App\models\Empresa;
use App\models\Productos_ventasg;
use App\Clases\clsMonedaLiteral;
use TCPDF;

class CajasController extends Controller
{
    //genera insert en la tabla de caja / generamos sesion de caja
    public function aperturaCaja(Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            //eliminamos espacios
            $params_array = array_map('trim', $params_array);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'horaI'        => 'required',
                //'horaF'           => 'required',
                'fondo'        => 'required',
                //'pc'       => 'required',
                'idEmpleado'      => 'required'
            ]);
            //revisamos la validacion
            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo la validacion de los datos del cliente',
                    'errors'    => $validate->errors()
                );
            }else{
                //si no hay errores en la validacion
                //obtenemos el nombre de la maquina
                $nombre_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                //creamos modelo
                $Caja = new Caja();
                //insertamos los datos
                $Caja->horaI = $params_array['horaI'];
                //$Caja->horaF = $params_array['horaF'];
                $Caja->pc = $nombre_host;
                $Caja->fondo = $params_array['fondo'];
                $Caja->idEmpleado = $params_array['idEmpleado'];
                //guardamos
                $Caja->save();

                $data = array(
                    'code'      =>  200,
                    'status'    =>  'success',
                    'message'   =>  'Registro correcto'
                );
            }

        } else{
            $data = array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);
    }
    //finalizamos sesion de caja actualizando el campo horaF con la hora de cierre
    public function cierreCaja(Request $request){
        $json = $request -> input('json', null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            $params_array = array_map('trim',$params_array);

            $validate = Validator::make($params_array,[
                'idCaja'    => 'required'
            ]);

            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo la validacion de los datos del cliente',
                    'errors'    => $validate->errors()
                );
            } else{
                //buscamos
                $caja = Caja::find($params_array['idCaja']);
                //actualizamos el valor
                $caja->horaF = date("Y-m-d H:i:s");
                //guardamos
                $caja->save();

                $data= array(
                    'code'  => 200,
                    'status'    =>'success',
                    'caja' => $caja
                );

            }

        } else{
            $data = array(
                'code'      =>  400,
                'status'    => 'error',
                'caja'   => 'Algo salio mal'
            );
        }
        return response()->json($data, $data['code']);
    }
    //traemos la inforamcion de caja de acuerdo al empleado y la ultima que creo
    public function verificarCaja($idEmpleado){
        if($idEmpleado){
            $Caja = Caja::latest('idCaja')->where('idEmpleado',$idEmpleado)->first();

            if($Caja){
                $data = array(
                    'code'      =>  200,
                    'status'    =>  'success',
                    'caja'      =>  $Caja,
                );
            } else{
                $data = array(
                    'code'      =>  404,
                    'status'    =>  'error',
                    'caja'      =>  null,
                    'message'   =>  'No se encontro ninguna sesion',
                );
            }
        } else{
            $data = array(
                'code'      =>  500,
                'status'    =>  'error',
                'caja'      =>  null,
                'message'   =>  'Parametro no recibido o incorrecto',
            );
        }
        return response()->json($data, $data['code']);
    }
    //generamos cobro de venta / se genera insert en la tabla movimientos_caja
    //registramos que se genero un cobro
    public function cobroVenta($idVenta,Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            
                try{
                    //comenzamos transaccion
                    DB::beginTransaction();

                    //creamos el modelo
                    $caja_movimientos = new Caja_movimientos;
                    //asginamos datos
                    $caja_movimientos->idCaja = $params_array['idCaja'];
                    $caja_movimientos->totalNota = $params_array['totalNota'];
                    $caja_movimientos->idTipoMov = $params_array['idTipoMov'];
                    $caja_movimientos->pagoCliente = $params_array['pagoCliente'];
                
                    //si los siguientes datos existen los guardamos
                    if(isset($params_array['idOrigen'])){
                        $caja_movimientos->idOrigen = $params_array['idOrigen'];
                    }
                    if(isset($params_array['idTipoPago'])){
                        $caja_movimientos->idTipoPago = $params_array['idTipoPago'];
                    }
                    if(isset($params_array['autoriza'])){
                        $caja_movimientos->autoriza = $params_array['autoriza'];
                    }
                    if(isset($params_array['observaciones'])){
                        $caja_movimientos->observaciones = $params_array['observaciones'];
                    }
                    if(isset($params_array['cambioCliente'])){
                        $caja_movimientos->cambioCliente = $params_array['cambioCliente'];
                    }

                    //por ultimo guardamos
                    $caja_movimientos->save();

                    //primero la buscamos
                    $venta = Ventasg::find($idVenta);

                    if($params_array['isSaldo'] == true || $params_array['tieneAbono'] == true){
                        $ultimoAbono = Abono_venta::where('idVenta',$idVenta)
                                                    ->orderBy('idAbonoVentas','desc')
                                                    ->first();

                        $abono_venta = new Abono_venta();
                        $abono_venta->idVenta = $params_array['idOrigen'];

                        if($params_array['saldo_restante'] == 0 ){
                            
                            $abono_venta->abono = $ultimoAbono ? $ultimoAbono->totalActualizado : die();
                            $abono_venta->totalActualizado = $params_array['saldo_restante'];
                            $venta->idStatus = 4; // cobrada, no se envia
                        } else {
                            $abono_venta->abono = $params_array['pagoCliente'];
                            $abono_venta->totalActualizado = $params_array['saldo_restante'];
                            $venta->idStatus = 21; // Cobro parcial, no se envia
                        }

                        $abono_venta->totalAnterior = $ultimoAbono ? $ultimoAbono->totalActualizado : $params_array['totalNota'];
                        $abono_venta->idEmpleado = $params_array['idEmpleado'];
                        $abono_venta->pc = gethostbyaddr($_SERVER['REMOTE_ADDR']);

                        $abono_venta->save();

                        
                    } else{
                        //asignamos status a actualizar
                        $venta->idStatus = 4; // cobrada, no se envia
                    }

                    //guardamos/actualizamos
                    $venta->save();
                    

                    DB::commit();

                    //generamos array de que el proceso fue correcto
                    $data = array(
                        'code'      =>  200,
                        'status'    =>  'success',
                        'message'   =>  'Registro correcto'
                    );
                } catch(\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   =>  'Algo salio mal rollback',
                        'error' => $e
                    );
                }
            
        } else{
            $data = array(
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'datos incorrectos'
            );
        }
        return response()->json($data, $data['code']);
    }
    //trae los id de las cajas que no tienen horafinal registrada
    //dando a entender que la sesion de la caja sigue activa
    public function verificaSesionesCaja(){
        $caja = DB::table('caja')
            ->join('empleado','empleado.idEmpleado','caja.idEmpleado')
            ->select('caja.*',
            DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
            ->where('horaF',null)
            ->get();

        return response()->json([
            'code'  => 200,
            'status'    => 'success',
            'caja'  => $caja
        ]);

    }
    /**Trae los movimientos de caja (cobros,pagos, etc)
     * que se realizaron de acuerdo al idCaja
     */
    public function movimientosSesionCaja($idCaja){
        $caja = DB::table('caja_movimientos')
            ->join('tipo_movimiento','tipo_movimiento.idTipo','caja_movimientos.idTipoMov')
            ->join('tipo_pago','tipo_pago.idt','caja_movimientos.idTipoPago')
            ->select('caja_movimientos.*','tipo_movimiento.nombre as nombreTipoMov','tipo_pago.tipo as nombreTipoPago')
            ->where('idCaja',$idCaja)
            ->get();

        return response()->json([
            'code'  => 200,
            'status'    => 'success',
            'caja'  => $caja
        ]);
    }

    public function abonos_ventas($idVenta){
        $abono_venta = Abono_venta::where('idVenta',$idVenta)
                                    ->select('abonoventas.*',
                                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                                    ->join('empleado','empleado.idEmpleado','=','abonoventas.idEmpleado')
                                    ->get();
                                    // ->map( function($abono_venta){
                                    //         $abono_venta->totalAbono = $abono_venta->abono;
                                    //         return $abono_venta;
                                    //     });
        // Suma todos los abonos
        $totalAbono = $abono_venta->sum('abono');
        // Obtenemos el total actualizado del ultimo abono
        $totalActualizado = Abono_venta::where('idVenta',$idVenta)
                                    ->orderBy('idAbonoVentas','desc')
                                    ->value('totalActualizado');
        // Verificamos que si no esta vacio asigne el valor obtenido si no asignamos cero
        $totalActualizado = !empty($totalActualizado) ? $totalActualizado : 0;

        return response()->json([
                'code' => 200,
                'status' => 'success',
                'abonos' => $abono_venta,
                'total_abono' => $totalAbono,
                'total_actualizado' => $totalActualizado
        ]);    
    }

    public function generatePDF($idVenta){

        if($idVenta){
            $Empresa = Empresa::first();
            $venta = Ventasg::select('ventasg.*',
                                    'tiposdeventas.nombre as nombreTipoVenta',
                                    'statuss.nombre as nombreStatus',
                                    DB::raw("CONCAT(cliente.nombre,' ',cliente.aPaterno,' ',cliente.aMaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
                                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                            ->join('cliente','cliente.idcliente','=','ventasg.idcliente')
                            ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
                            ->join('tiposdeventas','tiposdeventas.idTipoVenta','=','ventasg.idTipoVenta')
                            ->join('statuss','statuss.idStatus','=','ventasg.idStatus')
                            ->join('empleado','empleado.idEmpleado','=','ventasg.idEmpleado')
                            ->where('ventasg.idVenta','=',$idVenta)
                            ->first();

            $productosVenta = Productos_ventasg::select('productos_ventasg.*',
                                                            'productos_ventasg.total as subtotal',
                                                            'producto.claveEx as claveEx',
                                                            'historialproductos_medidas.nombreMedida')
                                ->join('producto','producto.idProducto','=','productos_ventasg.idProducto')
                                ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ventasg.idProdMedida')
                                ->where('productos_ventasg.idVenta','=',$idVenta)
                                ->get();

            if($venta && $productosVenta){
                $monedaLiteral = new clsMonedaLiteral();

                // Opcional: Especifica el formato de moneda si lo deseas
                $currency = [
                    'plural' => 'PESOS',
                    'singular' => 'PESO',
                    'centPlural' => 'CENTAVOS',
                    'centSingular' => 'CENTAVO'
                ];
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
                $pdf->Cell(0, 10, 'VENTA #'. $venta->idVenta, 0, 1); // Agrega un texto
                
                $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                $pdf->setXY(60,38);
                $pdf->Cell(0, 10, 'VENDEDOR: '. strtoupper($venta->nombreEmpleado), 0, 1); // Agrega un texto

                $pdf->setXY(170,38);
                $pdf->Cell(0, 10, 'FECHA: '. substr($venta->created_at,0,10), 0, 1); // Agrega un texto

                $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
                $pdf->SetLineWidth(2.5);//grosor de la linea
                $pdf->Line(10,50,200,50);//X1,Y1,X2,Y2

                $pdf->setXY(9,49);
                $pdf->Cell(0, 10, 'CLIENTE: '. $venta->nombreCliente, 0, 1); // Agrega un texto

                $pdf->setXY(164,49);
                $pdf->Cell(0, 10, 'RFC: '. $venta->clienteRFC, 0, 1); // Agrega un texto

                $pdf->setXY(9,57);
                $pdf->MultiCell(0, 10, 'DIRECCION: '. $venta->cdireccion, 0, 'L'); // Agrega un texto

                $pdf->setXY(9,64);
                $pdf->Cell(0,10, 'EMAIL: '. $venta->clienteCorreo, 0 ,1);

                $pdf->setXY(100,64);
                $pdf->Cell(0,10, 'TIPO CLIENTE: '. $venta->tipocliente, 0 ,1);

                $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
                $pdf->SetLineWidth(2.5);//grosor de la linea
                $pdf->Line(10,75,200,75);//X1,Y1,X2,Y2

                $pdf->SetDrawColor(0,0,0);//insertamos color a pintar en RGB
                $pdf->SetLineWidth(.2);//grosor de la linea

                $pdf->SetFillColor(7, 149, 223  );//Creamos color de relleno para la tabla
                $pdf->setXY(10,78);

                //Contamos el numero de productos
                $numRegistros = count($productosVenta);
                //establecemos limite de productos por pagina
                $RegistroPorPagina = 18;
                //calculamos cuantas paginas van hacer
                $paginas = ceil($numRegistros / $RegistroPorPagina);
                $contRegistros = 0;


                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
                //INSERTAMOS CABECERAS TABLA
                $pdf->Cell(32,10,'CLAVE EXTERNA',1,0,'C',true);
                $pdf->Cell(75, 10, 'DESCRIPCION', 1,0,'C',true);
                $pdf->Cell(16, 10, 'MEDIDA', 1,0,'C',true);
                $pdf->Cell(12, 10, 'CANT.', 1,0,'C',true);
                $pdf->Cell(18, 10, 'PRECIO', 1,0,'C',true);
                $pdf->Cell(16, 10, 'DESC.', 1,0,'C',true);
                $pdf->Cell(20, 10, 'SUBTOTAL', 1,0,'C',true);
                $pdf->Ln(); // Nueva línea3

                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('helvetica', 'B', 10); // Establece la fuente

                //REALIZAMOS RECORRIDO DEL ARRAY DE PRODUCTOS
                foreach($productosVenta as $prodC){
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
                        $pdf->Cell(32,10,'CLAVE EXTERNA',1,0,'C',true);
                        $pdf->Cell(75, 10, 'DESCRIPCION', 1,0,'C',true);
                        $pdf->Cell(16, 10, 'MEDIDA', 1,0,'C',true);
                        $pdf->Cell(12, 10, 'CANT.', 1,0,'C',true);
                        $pdf->Cell(18, 10, 'PRECIO', 1,0,'C',true);
                        $pdf->Cell(16, 10, 'DESC.', 1,0,'C',true);
                        $pdf->Cell(20, 10, 'SUBTOTAL', 1,0,'C',true);
                        $pdf->Ln(); // Nueva línea
                    }
                        
                        $pdf->SetTextColor(0, 0, 0);
                        $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                        $pdf->MultiCell(32,10,$prodC->claveEx,1,'C',false,0);
                        $pdf->MultiCell(75,10,$prodC->descripcion,1,'C',false,0);
                        $pdf->MultiCell(16,10,$prodC->nombreMedida,1,'C',false,0);
                        $pdf->MultiCell(12,10,$prodC->cantidad,1,'C',false,0);
                        $pdf->MultiCell(18,10,'$'. number_format($prodC->precio,2),1,'C',false,0);
                        $pdf->MultiCell(16,10,'$'. number_format($prodC->descuento,2),1,'C',false,0);
                        $pdf->MultiCell(20,10,'$'. number_format($prodC->subtotal,2),1,'C',false,0);
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

                $pdf->setXY(145,$posY+10);
                $pdf->Cell(0,10,'SUBTOTAL:          $'. number_format($venta->subtotal,2),0,1,'L',false);

                $pdf->setXY(145,$posY+15);
                $pdf->Cell(0,10,'DESCUENTO:      $'. number_format($venta->descuento,2),0,1,'L',false);

                $pdf->setXY(145,$posY+20);
                $pdf->Cell(0,10,'TOTAL:                 $'. number_format($venta->total,2),0,1,'L',false);

                $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
                $pdf->setXY(135,$posY+25);
                $pdf->Cell(0,10,'*** TODOS LOS PRECIOS SON NETOS ***',0,1,'L',false);
                
                $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                $pdf->setXY(9,$posY+35);
                $pdf->MultiCell(0,10,'OBSERVACIONES: '. $venta->observaciones ,0,'L',false);

                $posY = $pdf->getY();

                $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
                $pdf->SetLineWidth(2.5);//grosor de la linea
                $pdf->Line(10,$posY+5,200,$posY+5);//X1,Y1,X2,Y2

                //Se reestablecen los estilos para el bordeado
                $pdf->SetDrawColor(0,0,0);//insertamos color a pintar en RGB
                $pdf->SetLineWidth(.5);//grosor de la linea

                $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                $pdf->setXY(10,$posY+8);                                                          // el 'LTR ES EL BORDER L=LEFT, T=TOP, B= BOTTOM, R=RIGHT PUEDEN IR EN CUALQUIE ORDEN
                $pdf->Cell(0,0,'Por medio de este pagare me(nos) obligo(amos) a pagar incondicionalmente en este plazo, el dia     de','LTR',1,'L',false);
                $pdf->Cell(0,0,'a nombre de LUNA PEREZ BENJAMIN por la cantidad de : $'.number_format($venta->total,2),'LR',1,'L',false);
                $pdf->Cell(0,0,$monedaLiteral->numeroALetras($venta->total),'LR',1,'L',false);
                $pdf->Cell(0,0,'Valor recibido en mercancias. En caso de no pagar a su vencimiento causara interes moratorio de     % mensual','LR',1,'L',false);
                $pdf->Cell(0,15,'________________________________','LR',1,'C',false);
                $pdf->Cell(0,0,'Acepto de conformidad','LRB',1,'C',false);
                
                $pdf->Ln(); // Nueva línea
                $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
                $pdf->Cell(0,0,'*TODO CAMBIO CAUSARA UN 10% EN EL IMPORTE TOTAL.',0,1,'L',false);
                $pdf->Cell(0,0,'*TODA CANCELACION SE COBRARA 20% DEL IMPORTE TOTAL SIN EXCEPCION.',0,1,'L',false);
                $pdf->Cell(0,0,'*LA DESCARGA DE MATERIAL SERA EN UN MAXIMO DE 6m.',0,1,'L',false);

                
                $contenido = $pdf->Output('', 'I'); // Descarga el PDF con el nombre 'mi-archivo-pdf.pdf'
                $nombrepdf = 'mipdf.pdf';
            }
            
        } else{
           return response();
        }
        return response($contenido)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"$nombrepdf\"");
    }
}
/********************* */