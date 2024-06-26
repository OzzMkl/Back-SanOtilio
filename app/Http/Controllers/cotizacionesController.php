<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\models\Cotizacion;
use App\models\Productos_cotizaciones;
use App\models\Monitoreo;
use App\models\Empresa;
use TCPDF;
use Carbon\Carbon;

class cotizacionesController extends Controller
{

    public function indexCotiza(){
        $Cotizaciones = DB::table('cotizaciones')
        ->join('cliente','cliente.idCliente','=','cotizaciones.idCliente')
        ->join('empleado','empleado.idEmpleado','=','cotizaciones.idEmpleado')
        ->select('cotizaciones.*',
        DB::raw("CONCAT(cliente.nombre,' ',cliente.Apaterno,' ',cliente.Amaterno) as nombreCliente"),
        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        ->where('cotizaciones.idStatus','=',34)
        ->orderBy('cotizaciones.idCotiza','desc')
        ->paginate(10);
        return response()->json([
            'code'          => 200,
            'status'        => 'success',
            'Cotizaciones'  => $Cotizaciones
        ]);
    }

    public function guardarCotizacion(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params) && !empty($params_array)){
            //validamos los datos
            $validate = Validator::make($params_array['ventasg'], [
                'idCliente'       => 'required',
                'idEmpleado'      => 'required',
                'subtotal'   => 'required',
                'total'   => 'required',
            ]);
            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Validacion fallida, la cotizacion no se genero.',
                    'errors'    => $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();
                    
                    $cotizacion = new Cotizacion();
                    $cotizacion->idCliente = $params_array['ventasg']['idCliente'];
                    $cotizacion->cdireccion = $params_array['ventasg']['cdireccion'];
                    $cotizacion->idEmpleado = $params_array['ventasg']['idEmpleado'];
                    $cotizacion->idStatus = 34;
                    $cotizacion->observaciones = $params_array['ventasg']['observaciones'];
                    $cotizacion->descuento = $params_array['ventasg']['descuento'];
                    $cotizacion->subtotal = $params_array['ventasg']['subtotal'];
                    $cotizacion->total = $params_array['ventasg']['total'];
                    $cotizacion->created_at = Carbon::now();
                    $cotizacion->updated_at = Carbon::now();
                    $cotizacion->save();

                    $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);

                    //insertamos el movimiento realizado
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['ventasg']['idEmpleado'];
                    $monitoreo -> accion =  "Alta de cotizacion";
                    $monitoreo -> folioNuevo =  $cotizacion->idCotiza;
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    //Insercion de productos
                    $dataProductos = $this->guardarProductosCotizacion($cotizacion->idCotiza,$params_array['lista_productoVentag']);

                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Cotizacion registrada correctamente',
                        'idCotiza'  => $cotizacion->idCotiza,
                        'data_productos' => $dataProductos,
                    );
                
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

    public function guardarProductosCotizacion($idCotiza,$lista_productosVenta){   
        
        if( count($lista_productosVenta) && !empty($lista_productosVenta) && $idCotiza){
            try{
                DB::beginTransaction();
                //recoremos la lista de productos mandada
                foreach($lista_productosVenta as $param => $paramdata){

                    $productos_cotizacion = new Productos_cotizaciones();
                    $productos_cotizacion->idCotiza = $idCotiza;
                    $productos_cotizacion->idProducto = $paramdata['idProducto'];
                    $productos_cotizacion->idProdMedida = $paramdata['idProdMedida'];
                    $productos_cotizacion->precio = $paramdata['precio'];
                    $productos_cotizacion->cantidad = $paramdata['cantidad'];
                    $productos_cotizacion->descuento = $paramdata['descuento'];
                    $productos_cotizacion->subtotal = $paramdata['subtotal'];
                    $productos_cotizacion->created_at = Carbon::now();
                    $productos_cotizacion->updated_at = Carbon::now();

                    //guardamos el producto
                    $productos_cotizacion->save();
                }

                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'code'          =>  200,
                    'status'        => 'success',
                    'mesage'       =>  'Productos registrados correctamente',
                );

                DB::commit();
            } catch (\Exception $e){
                DB::rollBack();
                // propagamos el error
                throw $e;
            }
        }else{
            //Si el array esta vacio o mal echo mandamos mensaje de error
            $data =  array(
                'status'        => 'error',
                'code'          =>  404,
                'message'       =>  'Los datos enviados no son correctos'
            );
        }
        return $data;
    }

    public function consultaUltimaCotiza(){
        $Cotiza = Cotizacion::latest('idCotiza')->pluck('idCotiza')->first();
        return response()->json([
            'code'          => 200,
            'status'        => 'success',
            'Cotizacion'    => $Cotiza
        ]);
    }

    public function detallesCotizacion($idCotiza){
        $Cotiza = DB::table('cotizaciones')
            ->join('cliente','cliente.idCliente','=','cotizaciones.idCliente')
            ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
            ->join('empleado','empleado.idEmpleado','=','cotizaciones.idEmpleado')
            ->join('statuss','statuss.idStatus','=','cotizaciones.idStatus')
            ->select('cotizaciones.*',
            DB::raw("CONCAT(cliente.nombre,' ',cliente.Apaterno,' ',cliente.Amaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
            DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
            ->where('cotizaciones.idCotiza','=',$idCotiza)
            ->first();
        $productosCotiza = DB::table('productos_cotizaciones')
            ->join('producto','producto.idProducto','=','productos_cotizaciones.idProducto')
            ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_cotizaciones.idProdMedida')
            ->select('productos_cotizaciones.*','producto.claveEx as claveEx','producto.descripcion as descripcion', 'historialproductos_medidas.nombreMedida as nombreMedida')
            ->where('productos_cotizaciones.idCotiza','=',$idCotiza)
            ->get();
        if(is_object($Cotiza)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'Cotizacion'   =>  $Cotiza,
                'productos_cotiza'     => $productosCotiza
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

    public function updateCotizacion($idCotiza, Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){

            $validate = Validator::make($params_array['cotizacion'],[
                'idCotiza'      =>  'required',
                'idCliente'     =>  'required',
                'idEmpleado'    =>  'required',
                'idStatus'      =>  'required',
                'subtotal'      =>  'required',
                'total'         =>  'required'
            ]);

            //si falla creamos la respuesta a enviar
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message_system'   =>  'Fallo la validacion de los datos',
                    'errors'    =>  $validate->errors()
                );
            } else{
                try{
                    DB::beginTransaction();

                    //consultamos cotizacion antes de actualizar
                    // $antCotiza = Cotizacion::where('idCotiza',$idCotiza)->first();

                    //actualizamos
                    $cotizacion = Cotizacion::where('idCotiza', $idCotiza)->update([
                        'idCliente'     => $params_array['cotizacion']['idCliente'],
                        'cdireccion'    => $params_array['cotizacion']['cdireccion'],
                        // 'idStatus'      => $params_array['cotizacion']['idStatus'],
                        'observaciones' => $params_array['cotizacion']['observaciones'],
                        'subtotal'      => $params_array['cotizacion']['subtotal'],
                        'descuento'     => $params_array['cotizacion']['descuento'],
                        'total'         => $params_array['cotizacion']['total'],

                    ]);

                    //obtenemos direccion ip
                    $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);

                    //insertamos productos
                    $dataProductos = $this->updateProductosCotizacion($idCotiza, $params_array['lista_productoVentag']);

                    //insertamos el movimiento realizado en general de la cotizacion modificada
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['identity']['sub'];
                    $monitoreo -> accion =  "Modificacion de cotizacion";
                    $monitoreo -> folioNuevo =  $idCotiza;
                    $monitoreo -> pc =  $ip;
                    $monitoreo->created_at = Carbon::now();
                    $monitoreo->updated_at = Carbon::now();
                    $monitoreo ->save();


                    //generemos respuesta
                    $data = array(
                        'code'          =>  200,
                        'status'        =>  'success',
                        'message'       =>  'Cotizacion #'.$idCotiza.' modificada correctamente',
                        'data_productos'=>  $dataProductos
                    );

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
        } else{
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Los valores ingresado no se recibieron correctamente'
            );
        }
        return response()->json($data,$data['code']);
    }

    public function updateProductosCotizacion($idCotiza, $lista_productosVenta){
        
        if( count($lista_productosVenta) > 0 && !empty($lista_productosVenta) && !empty($idCotiza)){
            try{
                DB::beginTransaction();
                
                //obtenemos direccion ip
                $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                
                //eliminamos los registros que tengab ese idOrd
                Productos_cotizaciones::where('idCotiza',$idCotiza)->delete();

                //recorremos el array para asignar todos los productos
                foreach($lista_productosVenta as $param => $paramdata){

                    $productos_cotizacion = new Productos_cotizaciones();
                    $productos_cotizacion->idCotiza = $idCotiza;
                    $productos_cotizacion->idProducto = $paramdata['idProducto'];
                    $productos_cotizacion->idProdMedida = $paramdata['idProdMedida'];
                    $productos_cotizacion->precio = $paramdata['precio'];
                    $productos_cotizacion->cantidad = $paramdata['cantidad'];
                    $productos_cotizacion->descuento = $paramdata['descuento'];
                    $productos_cotizacion->subtotal = $paramdata['subtotal'];
                    $productos_cotizacion->created_at = Carbon::now();
                    $productos_cotizacion->updated_at = Carbon::now();
                    //guardamos el producto
                    $productos_cotizacion->save();
                    
                }

                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'code'          =>  200,
                    'status'        => 'success',
                    'message'       => 'Productos actualizados correctamente'
                );
                DB::commit();

            } catch(\Exception $e){
                DB::rollback();
                //Propagamos el error ocurrido
                throw $e;
            }

        } else{
            //Si el array esta vacio o mal echo mandamos mensaje de error
            $data =  array(
                'code'          =>  400,
                'status'        => 'error',
                'message'       =>  'Los datos enviados son incorrectos'
            );
        }
        return $data;
    }

    public function generatePDF($idCotiza){

        $Empresa = Empresa::first();

        $Cotiza = DB::table('cotizaciones')
        ->join('cliente','cliente.idCliente','=','cotizaciones.idCliente')
        ->join('tipocliente','tipocliente.idTipo','=','cliente.idTipo')
        ->join('empleado','empleado.idEmpleado','=','cotizaciones.idEmpleado')
        ->join('statuss','statuss.idStatus','=','cotizaciones.idStatus')
        ->select('cotizaciones.*',
        DB::raw("CONCAT(cliente.nombre,' ',cliente.Apaterno,' ',cliente.Amaterno) as nombreCliente"),'cliente.rfc as clienteRFC','cliente.correo as clienteCorreo','tipocliente.nombre as tipocliente',
        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        ->where('cotizaciones.idCotiza','=',$idCotiza)
        ->first();

        $productosCotiza = DB::table('productos_cotizaciones')
        ->join('producto','producto.idProducto','=','productos_cotizaciones.idProducto')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_cotizaciones.idProdMedida')
        ->select('productos_cotizaciones.*','producto.claveEx as claveEx','producto.descripcion as descripcion', 'historialproductos_medidas.nombreMedida as nombreMedida')
        ->where('productos_cotizaciones.idCotiza','=',$idCotiza)
        ->get();

        if(is_object($Cotiza)){

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
            $pdf->Cell(0, 10, 'COTIZACION #'. $Cotiza->idCotiza, 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,38);
            $pdf->Cell(0, 10, 'VENDEDOR: '. strtoupper($Cotiza->nombreEmpleado), 0, 1); // Agrega un texto

            $pdf->setXY(170,38);
            $pdf->Cell(0, 10, 'FECHA: '. substr($Cotiza->created_at,0,10), 0, 1); // Agrega un texto

            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,50,200,50);//X1,Y1,X2,Y2

            $pdf->setXY(9,49);
            $pdf->Cell(0, 10, 'CLIENTE: '. $Cotiza->nombreCliente, 0, 1); // Agrega un texto

            $pdf->setXY(164,49);
            $pdf->Cell(0, 10, 'RFC: '. $Cotiza->clienteRFC, 0, 1); // Agrega un texto

            $pdf->setXY(9,57);
            $pdf->MultiCell(0, 10, 'DIRECCION: '. $Cotiza->cdireccion, 0, 'L'); // Agrega un texto

            $pdf->setXY(9,64);
            $pdf->Cell(0,10, 'EMAIL: '. $Cotiza->clienteCorreo, 0 ,1);

            $pdf->setXY(100,64);
            $pdf->Cell(0,10, 'TIPO CLIENTE: '. $Cotiza->tipocliente, 0 ,1);

            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,75,200,75);//X1,Y1,X2,Y2

            $pdf->SetDrawColor(0,0,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(.2);//grosor de la linea

            $pdf->SetFillColor(7, 149, 223  );//Creamos color de relleno para la tabla
            $pdf->setXY(10,78);

            //Contamos el numero de productos
            $numRegistros = count($productosCotiza);
            //establecemos limite de productos por pagina
            $RegistroPorPagina = 18;
            //calculamos cuantas paginas van hacer
            $paginas = ceil($numRegistros / $RegistroPorPagina);
            $contRegistros = 0;


            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            //INSERTAMOS CABECERAS TABLA
            $pdf->Cell(32,10,'CLAVE EXTERNA',1,0,'C',true);
            $pdf->Cell(76, 10, 'DESCRIPCION', 1,0,'C',true);
            $pdf->Cell(16, 10, 'MEDIDA', 1,0,'C',true);
            $pdf->Cell(15, 10, 'CANT.', 1,0,'C',true);
            $pdf->Cell(15, 10, 'PRECIO', 1,0,'C',true);
            $pdf->Cell(16, 10, 'DESC.', 1,0,'C',true);
            $pdf->Cell(20, 10, 'SUBTOTAL', 1,0,'C',true);
            $pdf->Ln(); // Nueva línea3

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 10); // Establece la fuente

            //REALIZAMOS RECORRIDO DEL ARRAY DE PRODUCTOS
            foreach($productosCotiza as $prodC){
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
                    $pdf->Cell(76, 10, 'DESCRIPCION', 1,0,'C',true);
                    $pdf->Cell(16, 10, 'MEDIDA', 1,0,'C',true);
                    $pdf->Cell(15, 10, 'CANT.', 1,0,'C',true);
                    $pdf->Cell(15, 10, 'PRECIO', 1,0,'C',true);
                    $pdf->Cell(16, 10, 'DESC.', 1,0,'C',true);
                    $pdf->Cell(20, 10, 'SUBTOTAL', 1,0,'C',true);
                    $pdf->Ln(); // Nueva línea
                }
                    
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                    $pdf->MultiCell(32,10,$prodC->claveEx,1,'C',false,0);
                    $pdf->MultiCell(76,10,$prodC->descripcion,1,'C',false,0);
                    $pdf->MultiCell(16,10,$prodC->nombreMedida,1,'C',false,0);
                    $pdf->MultiCell(15,10,$prodC->cantidad,1,'C',false,0);
                    $pdf->MultiCell(15,10,'$'. number_format($prodC->precio,2),1,'C',false,0);
                    $pdf->MultiCell(16,10,'$'. number_format($prodC->descuento,2),1,'C',false,0);
                    $pdf->MultiCell(20,10,'$'. number_format($prodC->subtotal,2),1,'C',false,0);
                    $pdf->Ln(); // Nueva línea

                    if($contRegistros == 18){
                        $RegistroPorPagina = 25;
                        $contRegistros = $contRegistros + 7;
                    }

                    $contRegistros++;
            }

            // if($contRegistros % $RegistroPorPagina == 0){
            //     $pdf->AddPage();
            // }

            $posY= $pdf->getY();

            if($posY > 241){
                $pdf->AddPage();
                $posY = 0;
            }

            $pdf->setXY(145,$posY+10);
            $pdf->Cell(0,10,'SUBTOTAL:          $'. number_format($Cotiza->subtotal,2),0,1,'L',false);

            $pdf->setXY(145,$posY+15);
            $pdf->Cell(0,10,'DESCUENTO:      $'. number_format($Cotiza->descuento,2),0,1,'L',false);

            $pdf->setXY(145,$posY+20);
            $pdf->Cell(0,10,'TOTAL:                 $'. number_format($Cotiza->total,2),0,1,'L',false);

            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            $pdf->setXY(135,$posY+25);
            $pdf->Cell(0,10,'*** TODOS LOS PRECIOS SON NETOS ***',0,1,'L',false);
            
            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(9,$posY+35);
            $pdf->MultiCell(0,10,'OBSERVACIONES: '. $Cotiza->observaciones ,0,'L',false);

            $posY = $pdf->getY();

            $pdf->SetDrawColor(255,145,0);//insertamos color a pintar en RGB
            $pdf->SetLineWidth(2.5);//grosor de la linea
            $pdf->Line(10,$posY+5,200,$posY+5);//X1,Y1,X2,Y2

            $pdf->SetFont('helvetica', 'B', 9); // Establece la fuente
            $pdf->setXY(10,$posY+5);
            $pdf->Cell(0,10,'*** PRECIOS SUJETOS A CAMBIOS SIN PREVIO AVISO ***',0,1,'C',false);

            $contenido = $pdf->Output('', 'I'); // Descarga el PDF con el nombre 'mi-archivo-pdf.pdf'
            $nombrepdf = 'mipdf.pdf';

        }else{
            
        }
        
        return response($contenido)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"$nombrepdf\"");
    }

}
