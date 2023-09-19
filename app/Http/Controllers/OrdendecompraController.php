<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\OrdenDeCompra;
use App\Productos_ordenes;
use App\models\Monitoreo;
use App\models\Empresa;
use TCPDF;

class OrdendecompraController extends Controller
{
    //
     public function index(){
        $ordencompra = DB::table('ordendecompra')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->select('ordendecompra.*','proveedores.nombre as nombreProveedor')
        ->where('ordendecompra.idStatus','=',45)
        ->orwhere('ordendecompra.idStatus','=',46)
        ->orderBy('ordendecompra.idOrd','desc')
        ->paginate(10);
        
        return response()->json([
            'code'         =>  200,
            'status'       => 'success',
            'ordencompra'   => $ordencompra
        ]);

    }

    public function registerOrdencompra(Request $request){
        $json = $request -> input('json',null);//recogemos los datos enviados por post en formato json
        $params = json_decode($json);
        $params_array = json_decode($json,true);

        if(!empty($params_array)){
            //eliminar espacios vacios
            $params_array = array_map('trim', $params_array);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'idProveedor'       => 'required',
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
                    $Ordencompra->idStatus = 45;

                    $Ordencompra->save();

                    //obtenemos folio
                    $FolioOrden = OrdenDeCompra::latest('idOrd')->first()->idOrd; 
                    $Ordencompra->idOrd=$FolioOrden;
                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];
                    //insertamos el movimiento que se hizo en general
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                    $monitoreo -> accion =  "Alta de orden de compra";
                    $monitoreo -> folioNuevo =  $FolioOrden;
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  200,
                        'message'   =>  'Orden creada pero sin productos'
                    );
                    
                    DB::commit();

                } catch(\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'status'    => 'error',
                        'code'      => 400, 
                        'message'   => 'Fallo al crear la orden compra Rollback!',
                        'errors'    => $e
                    );
                }
                $data =  array(
                    'status'            => 'Success!',
                    'code'              =>  200,
                    'message'           =>  'Orden de compra registrada exitosamente',
                    'Ordencompra'   =>  $Ordencompra
                );
            }
            
        }else{
            $data =  array(
                'status'        => 'error',
                'code'          =>  400,
                'message'       =>  'json vacio'
            );
        }
        return response()->json($data, $data['code']);
    }

    public function registerProductosOrden(Request $req){
         $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
         $params_array = json_decode($json,true);//decodifiamos el json
         if(!empty($params_array)){
                //consultamos la ultima compra para poder asignarla
                $Orden = OrdenDeCompra::latest('idOrd')->first();//la guardamos en orden
                //recorremos el array para asignar todos los productos
                foreach($params_array AS $param => $paramdata){
                            $Productos_orden = new Productos_ordenes();//creamos el modelo
                            $Productos_orden->idOrd = $Orden -> idOrd;//asignamos el ultimo idOrd para todos los productos
                            $Productos_orden-> idProducto = $paramdata['idProducto'];
                            $Productos_orden-> idProdMedida = $paramdata['idProdMedida'];
                            $Productos_orden-> cantidad = $paramdata['cantidad'];
                            
                            $Productos_orden->save();//guardamos el modelo
                            //Si todo es correcto mandamos el ultimo producto insertado
                            $data =  array(
                                'status'        => 'success',
                                'code'          =>  200,
                                'Productos_orden'       =>  $Productos_orden
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

    public function getLastOrder(){
         $ordencompra = OrdenDeCompra::latest('idOrd')->first();
         return response()->json([
             'code'         =>  200,
             'status'       => 'success',
             'ordencompra'   => $ordencompra
         ]);
    }
    
    public function showMejorado($idOrd){
        $ordencompra = DB::table('ordendecompra')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','ordendecompra.idEmpleado')
        ->select('ordendecompra.*','proveedores.nombre as nombreProveedor', DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
        ->where('ordendecompra.idOrd','=',$idOrd)
        ->get();
        $productosOrden = DB::table('productos_ordenes')
        ->join('producto','producto.idProducto','=','productos_ordenes.idProducto')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ordenes.idProdMedida')
        ->select('productos_ordenes.*','producto.claveEx as claveEx','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida')
        ->where([
                    ['productos_ordenes.idOrd','=',$idOrd]
                ])
        ->get();


        if(is_object($ordencompra)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'ordencompra'   =>  $ordencompra,
                'productos'     => $productosOrden
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

    public function updateOrder($idOrd, Request $request){

        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
         if(!empty($params_array)){
             //eliminar espacios vacios
            $params_array = array_map('trim', $params_array);
            //quitamos lo que no queremos actualizar
            unset($params_array['idOrd']);
            unset($params_array['idReq']);
            unset($params_array['created_at']);
            //Asignamos idStatus
            $params_array['idStatus'] = 46;
            //actualizamos
            $Ordencompra = OrdenDeCompra::where('idOrd',$idOrd)->update($params_array);
                //retornamos la respuesta si esta
                 return response()->json([
                    'status'    =>  'success',
                    'code'      =>  200,
                    'message'   =>  'Orden actualizada'
                 ]);

         }else{
            return response()->json([
                'code'      =>  400,
                'status'    => 'Error!',
                'message'   =>  'json vacio'
            ]);   
         }
    }

    public function updateProductsOrder($idOrd,Request $req){
        $json = $req -> input('json',null);//recogemos los datos enviados por post en formato json
        $params_array = json_decode($json,true);//decodifiamos el json
        if(!empty($params_array)){//verificamos que no este vacio

            //eliminamos los registros que tengab ese idOrd
            Productos_ordenes::where('idOrd',$idOrd)->delete();
            //recorremos el array para asignar todos los productos
            foreach($params_array AS $param => $paramdata){
                $Productos_orden = new Productos_ordenes();//creamos el modelo
                $Productos_orden->idOrd = $idOrd;//asignamos el id desde el parametro que recibimos
                $Productos_orden-> idProducto = $paramdata['idProducto'];//asginamos segun el recorrido
                $Productos_orden-> idProdMedida = $paramdata['idProdMedida'];
                $Productos_orden-> cantidad = $paramdata['cantidad'];
                
                $Productos_orden->save();//guardamos el modelo
                //Si todo es correcto mandamos el ultimo producto insertado
            }
            $data =  array(
                'status'            => 'success',
                'code'              =>  200,
                'message'           =>  'Eliminacion e insercion correcta!',
                'Productos_orden'   =>  $Productos_orden
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

    public function generatePDF($idOrd,$idEmpleado){
        $Empresa = Empresa::first();

        $orden = DB::table('ordendecompra')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->join('empleado','empleado.idEmpleado','=','ordendecompra.idEmpleado')
        ->join('statuss','statuss.idStatus','=','ordendecompra.idStatus')
        ->select('ordendecompra.*','proveedores.nombre as nombreProveedor', 'statuss.nombre as statussN',
                    DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"),
                    DB::raw('DATE_FORMAT(ordendecompra.created_at, "%d/%m/%Y") as created_at')
                )
        ->where('ordendecompra.idOrd','=',$idOrd)
        ->first();

        $productosOrden = DB::table('productos_ordenes')
        ->join('producto','producto.idProducto','=','productos_ordenes.idProducto')
        ->join('marca','marca.idMarca','=','producto.idMarca')
        ->join('departamentos','departamentos.idDep','=','producto.idDep')
        ->join('historialproductos_medidas','historialproductos_medidas.idProdMedida','=','productos_ordenes.idProdMedida')
        ->select('productos_ordenes.*','producto.claveEx as claveEx','producto.descripcion as descripcion','historialproductos_medidas.nombreMedida as nombreMedida',
                    'marca.nombre as marcaN','departamentos.nombre as departamentoN'
                )
        ->where([
                    ['productos_ordenes.idOrd','=',$idOrd]
                ])
        ->get();

        if(is_object($orden)){

            //obtenemos direccion ip
            $ip = $_SERVER['REMOTE_ADDR'];
            //insertamos el movimiento que se hizo en general
            $monitoreo = new Monitoreo();
            $monitoreo -> idUsuario =  $idEmpleado;
            $monitoreo -> accion =  "Impresión de PDF, orden de compra";
            $monitoreo -> folioNuevo =  $orden->idOrd;
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
            $pdf->Cell(0, 10, 'ORDEN #'. $orden->idOrd, 0, 1); // Agrega un texto

            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,38);
            $pdf->Cell(0, 10, 'PROVEEDOR: '. strtoupper($orden->nombreProveedor), 0, 1); // Agrega un texto
            
            $pdf->setXY(157,38);
            $pdf->Cell(0, 10, 'FECHA: '. substr($orden->created_at,0,10), 0, 1); // Agrega un texto
            
            $pdf->SetFont('helvetica', '', 9); // Establece la fuente
            $pdf->setXY(60,43);
            $pdf->Cell(0, 10, 'EMPLEADO: '. strtoupper($orden->nombreEmpleado), 0, 1); // Agrega un texto



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
            $numRegistros = count($productosOrden);
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
            foreach($productosOrden as $prodC){
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
                    $pdf->MultiCell(29,10,$prodC->claveEx,1,'C',false,0);
                    $pdf->MultiCell(70,10,$prodC->descripcion,1,'C',false,0);
                    $pdf->MultiCell(16,10,$prodC->nombreMedida,1,'C',false,0);
                    $pdf->MultiCell(16,10,$prodC->cantidad,1,'C',false,0);
                    $pdf->MultiCell(25,10,$prodC->marcaN,1,'C',false,0);
                    $pdf->MultiCell(34,10,$prodC->departamentoN,1,'C',false,0);
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
            $pdf->MultiCell(0,10,'OBSERVACIONES: '. $orden->observaciones ,0,'L',false);

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

    public function cancelarOrden(Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        if( !empty($params_array)){
            $statusOrden = OrdenDeCompra::find($params_array['idOrd'])->idStatus; 
            if($statusOrden == 47){
                $data =  array(
                    'status'        => 'error',
                    'code'          =>  404,
                    'message'       =>  'La orden de compra ya está cancelada'
                );
            }else{
                try{
                    DB::beginTransaction();

                    //Cambio de status de orden de compra
                    $Orden = OrdenDeCompra::where('idOrd',$params_array['idOrd'])->update([
                        'idStatus' => 47
                    ]);
                    
                    //Insertamos en monitoreo la cancelacion con su motivo
                    //obtenemos direccion ip
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario = $params_array['idEmpleado'];
                    $monitoreo -> accion =  "Cancelacion de orden de compra";
                    $monitoreo -> folioNuevo =  $params_array['idOrd'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo -> motivo = $params_array['motivo'];
                    $monitoreo ->save();


                    $data =  array(
                        'status'            => 'success',
                        'code'              =>  200,
                        'message'           =>  'Cancelación de orden de compra correcta!'
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

    public function searchIdOrden($idOrd){
        $ordencompra = DB::table('ordendecompra')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->select('ordendecompra.*','proveedores.nombre as nombreProveedor')
        ->where('ordendecompra.idOrd','=',$idOrd)
        ->where(function($query){
            $query  ->orwhere('ordendecompra.idStatus','=',45)
                    ->orwhere('ordendecompra.idStatus','=',46);
        })
        ->paginate(10);

        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'orden'   =>  $ordencompra
        ]);
    }

    public function searchNombreProveedor($nombreProveedor){
        $ordencompra = DB::table('ordendecompra')
        ->join('proveedores','proveedores.idProveedor','=','ordendecompra.idProveedor')
        ->select('ordendecompra.*','proveedores.nombre as nombreProveedor')
        ->where('proveedores.nombre','like','%'.$nombreProveedor.'%')
        ->where(function($query){
            $query  ->orwhere('ordendecompra.idStatus','=',45)
                    ->orwhere('ordendecompra.idStatus','=',46);
        })
        ->paginate(10);

        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'orden'   =>  $ordencompra
        ]);

    } 



}
