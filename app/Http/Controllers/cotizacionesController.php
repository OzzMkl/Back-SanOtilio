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

class cotizacionesController extends Controller
{

    public function indexCotiza(){
        $Cotizaciones = DB::table('cotizaciones')
        ->join('cliente','cliente.idCliente','=','cotizaciones.idCliente')
        ->join('empleado','empleado.idEmpleado','=','cotizaciones.idEmpleado')
        ->select('cotizaciones.*',
        DB::raw("CONCAT(cliente.nombre,' ',cliente.Apaterno,' ',cliente.Amaterno) as nombreCliente"),
        DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
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
            //eliminamos espacios vacios
            $params_array = array_map('trim',$params_array);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'idCliente'       => 'required',
                'idEmpleado'      => 'required',//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
                'idStatus'   => 'required',
                'subtotal'   => 'required',
                'total'   => 'required',
            ]);
            if($validate->fails()){
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Fallo! La orden de compra no se ha creado',
                    'errors'    => $validate->errors()
                );
            }else{
                $cotizacion = new Cotizacion();
                $cotizacion->idCliente = $params_array['idCliente'];
                if(isset($params_array['cdireccion'])){
                    $cotizacion->cdireccion = $params_array['cdireccion'];
                }
                $cotizacion->idEmpleado = $params_array['idEmpleado'];
                $cotizacion->idStatus = $params_array['idStatus'];
                if(isset($params_array['observaciones'])){
                    $cotizacion->observaciones = $params_array['observaciones'];
                }
                if(isset($params_array['descuento'])){
                    $cotizacion->descuento = $params_array['descuento'];
                }
                $cotizacion->subtotal = $params_array['subtotal'];
                $cotizacion->total = $params_array['total'];

                $cotizacion->save();

                //consultamos la cotizacion ingresada
                $idCotiza = Cotizacion::latest('idCotiza')->first()->idCotiza;
                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                //insertamos el movimiento realizado
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                $monitoreo -> accion =  "Alta de cotizacion";
                $monitoreo -> folioNuevo =  $idCotiza;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();

                $data = array(
                    'status'    =>  'success',
                    'code'      =>  200,
                    'message'   =>  'Cotizacion creada pero sin productos'
                );
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

    public function guardarProductosCotiza(Request $request){   
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){
            //consultamos la ulitma cotizacion que se reazalizo
            $Cotizacion = Cotizacion::latest('idCotiza')->first();
            //recoremos la lista de productos mandada
            foreach($params_array as $param => $paramdata){
                $productos_cotizacion = new Productos_cotizaciones();
                $productos_cotizacion->idCotiza = $Cotizacion->idCotiza;
                $productos_cotizacion->idProducto = $paramdata['idProducto'];
                $productos_cotizacion->idProdMedida = $paramdata['idProdMedida'];
                $productos_cotizacion->precio = $paramdata['precio'];
                $productos_cotizacion->cantidad = $paramdata['cantidad'];
                if(isset($paramdata['descuento'])){
                    $productos_cotizacion->descuento = $paramdata['descuento'];
                }
                $productos_cotizacion->subtotal = $paramdata['subtotal'];
                //guardamos el producto
                $productos_cotizacion->save();
                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'status'        => 'success',
                    'code'          =>  200,
                    'Productos_cotizacion'       =>  $productos_cotizacion
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
        ->get();
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

    public function actualizaCotizacion($idCotiza, Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            //eliminar espacios vacios
            $params_array = array_map('trim', $params_array);
            

            $validate = Validator::make($params_array,[
                'idCotiza'      =>  'required',
                'idCliente'     =>  'required',
                'idEmpleado'    =>  'required',
                'idStatus'      =>  'required',
                'subtotal'      =>  'required',
                'descuento'      =>  'required',
                'total'         =>  'required'
            ]);

            //si falla creamos la respuesta a enviar
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message_system'   =>  'Fallo la validacion de los datos del producto',
                    'message_validation' => $validate->getMessage(),
                    'errors'    =>  $validate->errors()
                );
            } else{
                try{
                    DB::beginTransaction();

                    //consultamos cotizacion antes de actualizar
                    $antCotiza = Cotizacion::where('idCotiza',$idCotiza)->first();

                    //actualizamos
                    $cotizacion = Cotizacion::where('idCotiza', $idCotiza)->update([
                        'idCliente'     => $params_array['idCliente'],
                        'cdireccion'    => $params_array['cdireccion'],
                        'idEmpleado'    => $params_array['idEmpleado'],
                        'idStatus'      => $params_array['idStatus'],
                        'observaciones' => $params_array['observaciones'],
                        'subtotal'      => $params_array['subtotal'],
                        'descuento'     => $params_array['descuento'],
                        'total'         => $params_array['total'],

                    ]);

                    //consultamos la cotizacion actualizada
                    $newCotiza = Cotizacion::where('idCotiza',$idCotiza)->first();

                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];

                    //insertamos el movimiento realizado en general de la cotizacion modificada
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                    $monitoreo -> accion =  "Modificacion de cotizacion";
                    $monitoreo -> folioNuevo =  $params_array['idCotiza'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    //Verificamos los cambios que se realizaron para insertar el antes y el despues
                    foreach($antCotiza->getAttributes() as $clave => $valor){
                        foreach($newCotiza->getAttributes() as $clave2 => $valor2){
                            //verifica,os que la clave sea igual
                            //y que los valores sean diferentes para guardar el movimiento
                            if($clave == $clave2 && $valor != $valor2){
                                //insertamos el movimiento realizado
                                $monitoreo = new Monitoreo();
                                $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                                $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$valor2." de cotizacion";
                                $monitoreo -> folioNuevo =  $params_array['idCotiza'];
                                $monitoreo -> pc =  $ip;
                                $monitoreo ->save();
                            }

                        }
                    }

                    //generemos respuesta
                    $data = array(
                        'code'          =>  200,
                        'status'        =>  'success',
                        'message'       =>  'Cotizacion modificada correctamente',
                        'cotizacion'    => $newCotiza
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

    public function actualizaProductosCotiza($idCotiza, Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){
            //eliminamos los registros que tengab ese idOrd
            Productos_cotizaciones::where('idCotiza',$idCotiza)->delete();
            //recorremos el array para asignar todos los productos
            foreach($params_array as $param => $paramdata){
                $productos_cotizacion = new Productos_cotizaciones();
                $productos_cotizacion->idCotiza = $idCotiza;
                $productos_cotizacion->idProducto = $paramdata['idProducto'];
                //$productos_cotizacion->descripcion = $paramdata['descripcion'];
                $productos_cotizacion->precio = $paramdata['precio'];
                $productos_cotizacion->cantidad = $paramdata['cantidad'];
                if(isset($paramdata['descuento'])){
                    $productos_cotizacion->descuento = $paramdata['descuento'];
                }
                $productos_cotizacion->subtotal = $paramdata['subtotal'];
                //guardamos el producto
                $productos_cotizacion->save();
                //Si todo es correcto mandamos el ultimo producto insertado
                $data =  array(
                    'status'        => 'success',
                    'code'          =>  200,
                    'Productos_cotizacion'       =>  $productos_cotizacion
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
            $pdf->Line(10,43,55,43);//X1,Y1,X2,Y2

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
            $pdf->Cell(15, 10, 'PRECIO', 1,0,'C',true);
            $pdf->Cell(15, 10, 'CANT.', 1,0,'C',true);
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
                    $pdf->Cell(15, 10, 'PRECIO', 1,0,'C',true);
                    $pdf->Cell(15, 10, 'CANT.', 1,0,'C',true);
                    $pdf->Cell(16, 10, 'DESC.', 1,0,'C',true);
                    $pdf->Cell(20, 10, 'SUBTOTAL', 1,0,'C',true);
                    $pdf->Ln(); // Nueva línea
                }
                    
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 9); // Establece la fuente
                    $pdf->MultiCell(32,10,$prodC->claveEx,1,'C',false,0);
                    $pdf->MultiCell(76,10,$prodC->descripcion,1,'C',false,0);
                    $pdf->MultiCell(16,10,$prodC->nombreMedida,1,'C',false,0);
                    $pdf->MultiCell(15,10,'$'. $prodC->precio,1,'C',false,0);
                    $pdf->MultiCell(15,10,$prodC->cantidad,1,'C',false,0);
                    $pdf->MultiCell(16,10,'$'. $prodC->descuento,1,'C',false,0);
                    $pdf->MultiCell(20,10,'$'. $prodC->subtotal,1,'C',false,0);
                    $pdf->Ln(); // Nueva línea

                    $contRegistros++;
            }

            $posY= $pdf->getY();

            $pdf->setXY(145,$posY+10);
            $pdf->Cell(0,10,'SUBTOTAL:          $'. $Cotiza->subtotal,0,1,'L',false);

            $pdf->setXY(145,$posY+15);
            $pdf->Cell(0,10,'DESCUENTO:      $'. $Cotiza->descuento,0,1,'L',false);

            $pdf->setXY(145,$posY+20);
            $pdf->Cell(0,10,'TOTAL:                 $'. $Cotiza->total,0,1,'L',false);

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
            ->header('Content-Disposition', "attachment; filename=\"$nombreArchivo\"");
    }

}
