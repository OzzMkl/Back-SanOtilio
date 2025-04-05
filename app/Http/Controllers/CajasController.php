<?php

namespace App\Http\Controllers;

use App\Clases\clsPDFHelpers;
use App\Empleado;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Caja;
use App\Caja_movimientos;
use App\models\Ventasg;
use App\models\Ventasf;
use App\models\Ventascre;
use App\models\Ventas_corre;
use App\models\Abono_venta;
use Illuminate\Support\Facades\Storage;
use Validator;
use App\models\Empresa;
use App\models\Productos_ventasg;
use App\models\Productos_ventasf;
use App\models\Productos_ventascre;
use App\models\Productos_ventas_corre;
use App\Clases\clsMonedaLiteral;
use TCPDF;
use Carbon\Carbon;
use App\Producto;
use App\models\Monitoreo;

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
        // dd($params_array);
        if(!empty($params_array)){

            $validate = Validator::make($params_array['caja'],[
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
                try{
                    DB::beginTransaction();

                    $caja = Caja::findOrFail($params_array['caja']['idCaja']);
                    
                    if($caja->horaF == null){
                        $caja->horaF = Carbon::now();
                        $caja->save();
                        //Insertamos movimiento
                        Monitoreo::insertMonitoreo(
                            $params_array['idEmpleado'],
                            "Cierre de caja",
                            null,
                            $params_array['caja']['idCaja'],
                            null
                        );
                        //guardamos imagen
                        $img_data = base64_decode($params_array['imgChart']);
                        $img_name = "chart-corte-caja.png";
                        Storage::disk('public')->put('images/'.$img_name,$img_data);
                        //generamos pdf
                        $dataPdf = $this->generatePDF_CorteCajas($params_array['idEmpleado'], $params_array['caja'], $params_array['totales'], $img_name);

                        //mandamos respuesta
                        $data = array(
                            'code' => 200,
                            'status' => 'success',
                            'pdf' => base64_encode($dataPdf)
                        );
                    } else{
                        $data = array(
                            'code' => 400,
                            'status' => 'error',
                        );
                    }
                    
                    DB::commit();
                } catch (\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'error'     => $e,
                    );
                }
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

                    $caja_movimientos->created_at = Carbon::now();
                    $caja_movimientos->updated_at = Carbon::now();

                    //por ultimo guardamos
                    $caja_movimientos->save();

                    $venta = '';

                    //primero la buscamos en creditos
                    if ($params_array['isCredito'] == true) {
                        $venta = Ventascre::find($idVenta);
                        $paso='credito';
                    } else {
                        //si no la encontramos buscamos en ventasg
                            $venta = Ventasg::find($idVenta);
                            $paso='ventasg';
                        //si no la encontramos buscamos en ventas_corre
                        if(!$venta) {
                            $venta = Ventas_corre::where('idVenta', $idVenta)->first();
                            $paso='ventas_corre';
                        }
                    }
                    
                    //Validación de búsqueda
                    if(!$venta){
                        // Si no se encuentra la venta en ninguna tabla, lanzamos una excepción
                        throw new \Exception('La venta no fue encontrada en ninguna tabla.');                            
                    }
                        

                    //Si la venta cuenta con saldo pendiente o ya tenia abonos
                    if($params_array['isSaldo'] == true || $params_array['tieneAbono'] == true){
                        //Se consigue su ultimo abono
                        $ultimoAbono = Abono_venta::where('idVenta',$idVenta)
                                                    ->orderBy('idAbonoVentas','desc')
                                                    ->first();

                        $abono_venta = new Abono_venta();
                        $abono_venta->idVenta = $params_array['idOrigen'];

                        if($params_array['saldo_restante'] == 0 ){
                            //Se asigna lo que se debe abonar desde su ultimo abono
                            $abono_venta->abono = $ultimoAbono ? $ultimoAbono->totalActualizado : die();
                            $abono_venta->totalActualizado = $params_array['saldo_restante'];
                            $venta->idStatusCaja = 3; // cobrada
                        } else {
                            $abono_venta->abono = $params_array['pagoCliente'];
                            $abono_venta->totalActualizado = $params_array['saldo_restante'];
                            $venta->idStatusCaja = 5; // Cobro parcial
                        }

                        //Si ya cuenta con abonos se registra su ultimo total
                        //Si no se registra el total de la nota
                        $abono_venta->totalAnterior = $ultimoAbono ? $ultimoAbono->totalActualizado : $params_array['totalNota'];
                        $abono_venta->idEmpleado = $params_array['idEmpleado'];
                        $abono_venta->pc = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                        $abono_venta->created_at = Carbon::now();
                        $abono_venta->updated_at = Carbon::now();

                        $abono_venta->save();

                        //Registramos acción en monitoreo
                        Monitoreo::insertMonitoreo(
                            $params_array['idEmpleado'],
                            $accion = "Abono a la venta ".$venta->idVenta." con folio de abono: ",
                            null,
                            $abono_venta->idAbonoVentas,
                            null
                        );
                        
                    } else{
                        //asignamos status a actualizar
                        $venta->idStatusCaja = 3; // cobrada

                         //Registramos acción en monitoreo
                         Monitoreo::insertMonitoreo(
                            $params_array['idEmpleado'],
                            $accion = "Cobro de venta: ",
                            null,
                            $venta->idVenta,
                            null
                        );
                    }

                    //actualizamos
                    $venta->updated_at = Carbon::now();
                    $venta->save();

                    /***************
                     * 
                     * 
                     * PROXIMO A ACTUALIZAR PARA ENTREGAS
                     * 
                     * ***************** */
                    $dataProductos = [];
                    $dataVentaf = [];
                    if($venta->idStatusCaja == 3 && ($venta->idStatusEntregas == 6 || $venta->idStatusEntregas == 11)){
                        //guardamos la venta en ventas finalizadas y se elimina de ventasg
                        $dataProductos = $this->guardaProductosVentaFinalizada($venta->idVenta, $params_array['isCredito']);
                        $dataVentaf = $this->guardaVentaFinalizada($venta, $params_array['isCredito']);
                    }

                    DB::commit();

                    //generamos array de que el proceso fue correcto
                    $data = array(
                        'code'      =>  200,
                        'status'    =>  'success',
                        'message'   =>  'Registro correcto',
                        'data_productos' => $dataProductos,
                        'data_venta' => $dataVentaf
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
        $caja = Caja::with('empleado')
                    ->whereNull('horaF')
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
        $caja = Caja::find($idCaja);
        $caja_mov = Caja_movimientos::with('tipo_movimiento','tipo_pago')
                    ->where('idCaja',$caja->idCaja)
                    ->get();

            $totales = array(
                'total_cobros' => 0,

                'total_efectivo' => 0,
                'No_efectivo' => 0,

                'total_tarjeta' => 0,
                'No_tarjeta' => 0,

                'total_transferencia' => 0,
                'No_transferencia' => 0,

                'total_credito' => 0,
                'No_credito' => 0,

                'total_cheque' => 0,
                'No_cheque' => 0,

                'total_deposito' => 0,
                'No_deposito' => 0,

                'total_cambio' => 0,
                'total_final' => 0,
            );

            foreach($caja_mov as $mov){
                if($mov->idTipoMov == 1 || $mov->idTipoMov == 2){
                    $totales['total_cobros'] += $mov->pagoCliente;
                    // $suma_cobros += $mov->pagoCliente;
                }
                if($mov->idTipoPago == 1){
                    $totales['total_efectivo'] += $mov->pagoCliente;
                    $totales['No_efectivo'] ++;
                }
                if($mov->idTipoPago == 2){
                    $totales['total_tarjeta'] += $mov->pagoCliente;
                    $totales['No_tarjeta'] ++;
                }
                if($mov->idTipoPago == 3){
                    $totales['total_transferencia'] += $mov->pagoCliente;
                    $totales['No_transferencia'] ++;
                }
                if($mov->idTipoPago == 4){
                    $totales['total_credito'] += $mov->pagoCliente;
                    $totales['No_credito'] ++;
                }
                if($mov->idTipoPago == 5){
                    $totales['total_cheque'] += $mov->pagoCliente;
                    $totales['No_cheque'] ++;
                }
                if($mov->idTipoPago == 6){
                    $totales['total_deposito'] += $mov->pagoCliente;
                    $totales['No_deposito'] ++;
                }
                if($mov->cambioCliente > 0){
                    $totales['total_cambio'] += $mov->cambioCliente;
                }
            }
            
            $totales['total_final'] =( $caja->fondo + $totales['total_cobros']) - $totales['total_cambio'];

        return response()->json([
            'code'  => 200,
            'status'    => 'success',
            'caja' => $caja,
            'caja_mov'  => $caja_mov,
            'totales' => $totales,
        ]);
    }

    /**
     * @description
     * Busca todos los abonos de la venta
     * Suma el total de los abonos
     */
    public function abonos_ventas($idVenta){
        $abono_venta = Abono_venta::where('idVenta',$idVenta)
                                    ->select('abonoventas.*',
                                        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                                    ->join('empleado','empleado.idEmpleado','=','abonoventas.idEmpleado')
                                    ->get();
                                    
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

    /**
     * Creacion de pdf para las ventas
     * Actualmente su uso aplica solo para el modulo de CAJAS
     */
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
                            ->join('statuss','statuss.idStatus','=','ventasg.idStatusCaja')
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
                //CREACION DEL PDF
                $pdf = new TCPDF('P', 'mm','A4',true,'UTF-8');
                //ELIMINAMOS CABECERAS Y PIE DE PAGINA
                $pdf-> setPrintHeader(false);
                $pdf-> setPrintFooter(false);
                //INSERTAMOS PAGINA
                $pdf->AddPage();
                
                clsPDFHelpers::addHeader($pdf,$Empresa);

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

                clsPDFHelpers::addPDFTableVentas($pdf, $productosVenta);

                $posY= $pdf->getY();

                if($posY > 241){
                    $pdf->AddPage();
                    // $posY = 0;
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
            }
            
        } else{
           return response();
        }
        return response($contenido)
            ->header('Content-Type', 'application/pdf');
    }

    public function generatePDF_CorteCajas($idEmpleadoCierre, $caja, $totales,$img_name){
        // dd( );
        if($idEmpleadoCierre && $caja && $totales && $img_name){
            $file = base64_encode(Storage::disk('public')->get('images/'.$img_name));
            $image = base64_decode($file);
            $Empresa = Empresa::first();

            $nameEmpleadoCierre = Empleado::where('idEmpleado',$idEmpleadoCierre)
                        ->selectRaw("CONCAT(nombre, ' ', aPaterno, ' ', aMaterno) as full_name")
                        ->first();

            $nameCajero = Empleado::where('idEmpleado',$caja['idEmpleado'])
                        ->selectRaw("CONCAT(nombre, ' ', aPaterno, ' ', aMaterno) as full_name")
                        ->first();

            $fecha = Carbon::now()->toDateTimeString();
            // $img_decode = base64_decode($img); 

            $pdf = new TCPDF('P', 'mm','A4',true,'UTF-8');
            //ELIMINAMOS CABECERAS Y PIE DE PAGINA
            $pdf-> setPrintHeader(false);
            $pdf-> setPrintFooter(false);
            //INSERTAMOS PAGINA
            $pdf->AddPage();
            
            clsPDFHelpers::addHeader($pdf,$Empresa);

            $pdf->SetFont('helvetica', 'B', 12); // Establece la fuente
            $pdf->setXY(10,38);
            $pdf->Cell(0, 10, 'CORTE DE CAJA', 0, 1); // Agrega un texto
            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,38);
            $pdf->Cell(0, 10, 'GENERA CORTE: '. strtoupper($nameEmpleadoCierre->full_name), 0, 1); // Agrega un texto
            
            $pdf->setXY(170,38);
            $pdf->Cell(0, 10, 'FECHA: '. substr($fecha,0,10), 0, 1); // Agrega un texto

            $pdf->setXY(60,43);
            $pdf->Cell(0, 10, 'CAJERO: '. strtoupper($nameCajero->full_name), 0, 1); // Agrega un texto

            $pdf->setXY(170,43);
            $pdf->Cell(0, 10, 'HORA: '. substr($fecha, 11, 8), 0, 1); // Agrega un text

            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,52,200,52);//X1,Y1,X2,Y2

            $pdf->Image('@'.$image,95,55,100,80);

            $pdf->SetLineWidth(5); // grosor de la línea
            $pdf->Line(10, 60, 58, 60); // X1, Y1, X2, Y2
            $pdf->setXY(10,55);
            $pdf->Cell(0, 10, 'EFECTIVO', 0, 1); // Agrega un texto
            $pdf->setXY(60,55);
            $pdf->Cell(0, 10, 'TOTAL: $'. number_format($totales['total_efectivo'],2), 0, 1); // Agrega un texto

            $pdf->SetLineWidth(5); // grosor de la línea
            $pdf->Line(10, 70, 58, 70); // X1, Y1, X2, Y2
            $pdf->setXY(10,65);
            $pdf->Cell(0, 10, 'TARJETA', 0, 1); // Agrega un texto
            $pdf->setXY(60,65);
            $pdf->Cell(0, 10, 'TOTAL: $'. number_format($totales['total_tarjeta'],2), 0, 1); // Agrega un texto

            $pdf->SetLineWidth(5); // grosor de la línea
            $pdf->Line(10, 80, 58, 80); // X1, Y1, X2, Y2
            $pdf->setXY(10,75);
            $pdf->Cell(0, 10, 'TRANSFERENCIA', 0, 1); // Agrega un texto
            $pdf->setXY(60,75);
            $pdf->Cell(0, 10, 'TOTAL: $'. number_format($totales['total_transferencia'],2), 0, 1); // Agrega un texto

            $pdf->SetLineWidth(5); // grosor de la línea
            $pdf->Line(10, 90, 58, 90); // X1, Y1, X2, Y2
            $pdf->setXY(10,85);
            $pdf->Cell(0, 10, 'CREDITO', 0, 1); // Agrega un texto
            $pdf->setXY(60,85);
            $pdf->Cell(0, 10, 'TOTAL: $'. number_format($totales['total_credito'],2), 0, 1); // Agrega un texto

            $pdf->SetLineWidth(5); // grosor de la línea
            $pdf->Line(10, 100, 58, 100); // X1, Y1, X2, Y2
            $pdf->setXY(10,95);
            $pdf->Cell(0, 10, 'CHEQUE', 0, 1); // Agrega un texto
            $pdf->setXY(60,95);
            $pdf->Cell(0, 10, 'TOTAL: $'. number_format($totales['total_cheque'],2), 0, 1); // Agrega un texto

            $pdf->SetLineWidth(5); // grosor de la línea
            $pdf->Line(10, 110, 58, 110); // X1, Y1, X2, Y2
            $pdf->setXY(10,105);
            $pdf->Cell(0, 10, 'DEPOSITO', 0, 1); // Agrega un texto
            $pdf->setXY(60,105);
            $pdf->Cell(0, 10, 'TOTAL: $'. number_format($totales['total_deposito'],2), 0, 1); // Agrega un texto

            $pdf->SetLineWidth(5); // grosor de la línea
            $pdf->Line(10, 120, 58, 120); // X1, Y1, X2, Y2
            $pdf->setXY(10,115);
            $pdf->Cell(0, 10, 'FONDO', 0, 1); // Agrega un texto
            $pdf->setXY(60,115);
            $pdf->Cell(0, 10, 'TOTAL: $'. number_format($caja['fondo'],2), 0, 1); // Agrega un texto

            

            $pdf->SetLineWidth(2.5); // grosor de la línea
            $pdf->Line(150, 140, 200, 140); // X1, Y1, X2, Y2

            $pdf->setXY(150,140);
            $pdf->Cell(0, 10, 'Saldo inicial: $'. number_format($caja['fondo'],2), 0, 1); // Agrega un texto
            $pdf->setXY(150,145);
            $pdf->Cell(0, 10, 'Total efectivo: $'. number_format($totales['total_efectivo'],2), 0, 1); // Agrega un texto
            $pdf->setXY(150,150);
            $pdf->Cell(0, 10, 'Total neto: $'. number_format($totales['total_final'],2), 0, 1); // Agrega un texto

            $pdf->SetLineWidth(2.5); // grosor de la línea
            $pdf->Line(150, 160, 200, 160); // X1, Y1, X2, Y2


            $contenido = $pdf->Output('', 'I');
        } else{
            return response();
        }
        return response($contenido)
                ->header('Content-Type', 'application/pdf');
        
        // return $contenido;
    }

    /**
     * @description
     * Registra la venta en ventasf despues la elimina de ventasg
     */
    function guardaVentaFinalizada($objVenta, $isCredito){
        if($objVenta){
            try{
                DB::beginTransaction();

                $venta_finalizada = new Ventasf;
                $venta_finalizada -> idVenta = $objVenta -> idVenta;
                $venta_finalizada -> idCliente = $objVenta -> idCliente;
                $venta_finalizada -> cdireccion = $objVenta -> cdireccion;
                $venta_finalizada -> idTipoVenta = $objVenta -> idTipoVenta;
                $venta_finalizada -> autorizaV = $objVenta -> autorizaV;
                $venta_finalizada -> observaciones = $objVenta -> observaciones;
                $venta_finalizada -> idStatusCaja = $objVenta -> idStatusCaja;
                $venta_finalizada -> idStatusEntregas = $objVenta -> idStatusEntregas;
                $venta_finalizada -> fecha = $objVenta -> created_at;
                $venta_finalizada -> idEmpleado = $objVenta -> idEmpleado;
                $venta_finalizada -> subtotal = $objVenta -> subtotal;
                $venta_finalizada -> descuento = $objVenta -> descuento;
                $venta_finalizada -> total = $objVenta -> total;
                $venta_finalizada -> created_at = Carbon::now();
                $venta_finalizada -> updated_at = Carbon::now();
                if($isCredito){
                    $venta_finalizada->idEmpleadoC = $objVenta->idEmpleadoC;
                    $venta_finalizada->autorizaC = $objVenta->autorizaC;
                }
                $venta_finalizada -> save();

                if($isCredito){
                    Ventascre::where('idVenta',$objVenta->idVenta)->delete();
                } else{
                    Ventasg::where('idVenta',$objVenta->idVenta)->delete();
                }

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Venta finalizada correctamente'
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
                'message'       =>  'El folio de venta es incorrecto'
            );
        }
        return $data;
    }

    /**
     * @description
     * registra los productos en productos_ventasf,
     * luego los elimina de la tabla productos_ventasg
     */
    function guardaProductosVentaFinalizada($idVenta, $isCredito){
        if($idVenta){
            try{
                DB::beginTransaction();

                $lista_prodVen_ant = [];
                if($isCredito){
                    $lista_prodVen_ant = Productos_ventascre::where('idVenta',$idVenta)->get();
                } else{
                    //Consultamos productos a eliminar
                    $lista_prodVen_ant = Productos_ventasg::where('idVenta',$idVenta)->get();
                }

                foreach($lista_prodVen_ant as $param => $paramdata){
                    $claveEx = Producto::select('claveEx')->where('idProducto',$paramdata['idProducto'])->value('claveEx');
                    $producto_ventaf = new Productos_ventasf();
                    $producto_ventaf->idVenta = $paramdata['idVenta'];
                    $producto_ventaf->idProducto = $paramdata['idProducto'];
                    $producto_ventaf->descripcion = $paramdata['descripcion'];
                    $producto_ventaf->claveEx = $claveEx;
                    $producto_ventaf->idProdMedida = $paramdata['idProdMedida'];
                    $producto_ventaf->cantidad = $paramdata['cantidad'];
                    $producto_ventaf->precio = $paramdata['precio'];
                    $producto_ventaf->descuento = $paramdata['descuento'];
                    $producto_ventaf->total = $paramdata['total'];
                    $producto_ventaf->igualMedidaMenor = $paramdata['igualMedidaMenor'];
                    $producto_ventaf-> save();
                }

                if($isCredito){
                    Productos_ventascre::where('idVenta',$idVenta)->delete();
                } else{
                    //Eliminamos de la tabla
                    Productos_ventasg::where('idVenta',$idVenta)->delete();
                }

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Productos registrados correctamente en ventas finalizadas'
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
                'message'       =>  'El folio de venta es incorrecto'
            );
        }
        return $data;
    }

    //Ventas credito
    public function guardaVentaCredito(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            $validate = Validator::make($params_array, [
                'idVenta'       => 'required',
                'idEmpleado'       => 'required',
            ]);

            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Validacion fallida, la venta no se genero.',
                    'errors'    => $validate->errors()
                );
            } else{
                try{
                    DB::beginTransaction();

                    if($params_array['permisos']['editar'] == 1){
                        //Buscamos la venta a mover a credito
                        $ventag = Ventasg::find($params_array['idVenta']);
                        
                        //insertamos valores
                        $ventacre = new Ventascre();
                        $ventacre->idVenta = $ventag->idVenta;
                        $ventacre->idCliente = $ventag->idCliente;
                        $ventacre->cdireccion = $ventag->cdireccion;
                        $ventacre->idTipoVenta = $ventag->idTipoVenta;
                        $ventacre->autorizaV = $ventag->autorizaV;
                        $ventacre->autorizaC = $ventag->autorizaC;
                        $ventacre->observaciones = $ventag->observaciones;
                        $ventacre->idStatusCaja = $ventag->idStatusCaja;
                        $ventacre->idStatusEntregas = $ventag->idStatusEntregas;
                        $ventacre->fecha = $ventag->created_at;
                        $ventacre->idEmpleadoG = $ventag->idEmpleado;
                        $ventacre->idEmpleadoC = $params_array['idEmpleado'];
                        // $ventacre->idEmpleadoF = $ventag->;
                        $ventacre->subtotal = $ventag->subtotal;
                        $ventacre->descuento = $ventag->descuento;
                        $ventacre->total = $ventag->total;
                        $ventacre->save();

                        //Consultamos productos a eliminar
                        $lista_prodVen_ant = Productos_ventasg::where('idVenta',$params_array['idVenta'])->get();
                        //insertamos en la nueva tabla
                        foreach($lista_prodVen_ant as $param => $paramdata){
                            $producto_ventacre = new Productos_ventascre();
                            $producto_ventacre->idVenta = $paramdata['idVenta'];
                            $producto_ventacre->idProducto = $paramdata['idProducto'];
                            $producto_ventacre->descripcion = $paramdata['descripcion'];
                            $producto_ventacre->idProdMedida = $paramdata['idProdMedida'];
                            $producto_ventacre->cantidad = $paramdata['cantidad'];
                            $producto_ventacre->precio = $paramdata['precio'];
                            $producto_ventacre->descuento = $paramdata['descuento'];
                            $producto_ventacre->total = $paramdata['total'];
                            $producto_ventacre->igualMedidaMenor = $paramdata['igualMedidaMenor'];
                            $producto_ventacre-> save();
                        }

                        //Eliminamos de la tabla
                        Productos_ventasg::where('idVenta',$params_array['idVenta'])->delete();
                        Ventasg::where('idVenta',$params_array['idVenta'])->delete();

                        $data = array(
                            'status'    =>  'success',
                            'code'      =>  200,
                            'message'   =>  'La venta '.$params_array['idVenta'].' se movio a credito correctamente',
                        );
                    } else{
                        $data = array(
                            'code'      =>  400,
                            'status'    =>  'error',
                            'message'   =>  'El usuario no cuenta con los permisos necesarios',
                        );
                    }

                    DB::commit();
                } catch (\Exception $e){
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
                'status'    => 'Error!',
                'message'   =>  'Los datos enviados son incorrectos'
            );
        }
        return response()->json($data, $data['code']);
    }
}