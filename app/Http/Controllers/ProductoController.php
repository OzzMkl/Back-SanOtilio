<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\Producto;
use App\Productos_medidas;
use App\models\Monitoreo;
use App\models\historialproductos_medidas;

class ProductoController extends Controller
{
    /**
     * Trae todo los datos del producto, nombre de la marca
     * nombre de la categoria y nomnbre del departamento
     * Solo para productos con status 31 HABILITADOS
     */
    public function index(){
        //GENERAMOS CONSULTA
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                    'categoria.nombre as nombreCat')
        ->where('statuss',31)
        ->paginate(5);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    /**
     * REVISAR
     * Trae la informacion de los productos para el modulo de punto de venta
     * 
     */
    public function indexPV(){
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.idProducto','producto.claveEx','producto.cbarras','producto.descripcion','producto.existenciaG','marca.nombre as nombreMarca')
        ->where('statuss',31)
        ->paginate(10);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    /**
     * Trae todo los datos del producto, nombre de la marca
     * nombre de la categoria y nombre del departamento
     * Solo para productos con status 32 DESHABILITADOS
     */
    public function productoDes(){
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                    'categoria.nombre as nombreCat')
        ->where('statuss',32)
        ->paginate(5);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    /**
     * Funcion para guardar imagen del modulo producto-agregar
     */
    public function uploadimage(Request $request){
        //recoger la imagen de la peticion
        $image = $request->file('file0');

        //validar la imagen
        $validate =Validator::make($request->all(), [
            'file0'     =>  'required|image|mimes:jpg,jpeg,png'
        ]);
        //guardar la imagen en el disco
        if(!$image || $validate->fails()){
            
            $data = array(//mandamos mensaje de error si la carga salio mal
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Error al subir imagen'
            );

        }else{
            $image_name = time().$image->getClientOriginalName();
            \Storage::disk('imageproductos')->put($image_name, \File::get($image));

            $data = array(//confirmamos de que fue correcta la carga
                'code'      =>  200,
                'status'    =>  'success',
                'image'     =>  $image_name
            );
        }
        //devolver datos
        return response()->json($data, $data['code']);
    }

    /**
     * Busca el nombre de la imagen en la carpeta
     * Si existe devuelve la imagen y si no 
     * regresa un mensaje 
     */
    public function getImageProduc($filename){
        //comprobar si existe la imagen
        $isset = \Storage::disk('imageproductos')->exists($filename);
        if($isset){
            //para usar este metodo lo importamos desde arriba
            //y creamos la ruta en web.php
       $file = \Storage::disk('imageproductos')->get($filename);
       //Esto se hizo para comprobar que la imagen fuera correcta ya que como esta muestra error
            /*$data = array(
                'code'      =>  200,
                'status'    =>  'bien',
                'image' => base64_encode($file)
            );
            return response()->json($data, $data['code']);*/
           
            return new Response($file);
          //return new Response(base64_encode($file));
        } else{
            $data = array(
                'code'      =>  404,
                'status'    =>  'error',
                'message'   =>  'La imagen no existe'
            );

            return response()->json($data, $data['code']);
        }
    }

    /**
     * Da de alta el producto
     * 
     * Recibe los datos del producto + datos empleado
     */
    public function register(Request $request){
        
        //tomamos solo el json
        $json = $request -> input('json', null);
        //lo decodificamos como json
        $params = json_decode($json);
        //se separa y se ponen como array
        $params_array = json_decode($json, true);

            //revisamos que no vengan vacios
        if( !empty($params_array)){
            //limpiamos los datos
            $params_array = array_map('trim', $params_array);
            //validamos los datos que llegaron
            $validate = Validator::make($params_array, [
                'idMarca'           =>  'required',
                'idDep'             =>  'required',
                'idCat'             =>  'required',
                'claveEx'           =>  'required',
                'descripcion'       =>  'required',
                'stockMin'          =>  'required',
                'stockMax'          =>  'required',
                //'statuss'           =>  'required',
                'ubicacion'         =>  'required',
                //'claveSat'          =>  'required',
                'tEntrega'          =>  'required',
                'idAlmacen'         =>  'required',
                'existenciaG'       =>  'required'
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
            }else{
                try{
                    DB::beginTransaction();

                    //consultamos el ultimo producto registrado y extraemos su codigo de barras
                    $ultimoCbarras = Producto::latest('idProducto')->first()->cbarras;
                    //sumamos +1 AL CODIGO DE BARRAS
                    $ultimoCbarras = $ultimoCbarras +1;

                    //creamos el producto a ingresar
                    $producto = new Producto();
                    $producto -> idMarca = $params_array['idMarca'];
                    $producto -> idDep = $params_array['idDep'];
                    $producto -> idCat = $params_array['idCat'];
                    $producto -> claveEx = $params_array['claveEx'];
                    $producto -> cbarras = $ultimoCbarras;//aqui ingresamos el codigo de barras consultado
                    $producto -> descripcion = $params_array['descripcion'];
                    $producto -> stockMin = $params_array['stockMin'];
                    $producto -> stockMax = $params_array['stockMax'];
                    if( isset($params_array['imagen'])){
                        $producto -> imagen = $params_array['imagen'];
                    }
                    $producto -> statuss = 31;
                    $producto -> ubicacion = $params_array['ubicacion'];
                    $producto -> claveSat = $params_array['claveSat'];
                    $producto -> tEntrega = $params_array['tEntrega'];
                    $producto -> idAlmacen = $params_array['idAlmacen'];
                    $producto -> existenciaG = $params_array['existenciaG'];
                    //guardamos
                    $producto->save();
                    //una vez guardado mandamos mensaje de OK
                    
                    //consultamos el ultimo producto ingresado
                    $ultimoProducto = Producto::latest('idProducto')->first()->idProducto;

                    //obtenemos el nombre de la maquina
                    $pc = gethostname();
                    //insertamos el movimiento realizado
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['sub'];
                    $monitoreo -> accion =  "Alta de producto";
                    $monitoreo -> folioNuevo =  $ultimoProducto;
                    $monitoreo -> pc =  $pc;
                    $monitoreo ->save();

                    //generamos respuesta
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  '200',
                        'message'   =>  'El producto se a guardado correctamente',
                        'producto'  =>  $producto
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
            $data =  array(
                'code'          =>  400,
                'status'        => 'error',
                'message'       =>  'Los valores no se recibieron correctamente'
            );
        }
        return response()->json($data, $data['code']);
    }

    /**
     * Guarda las medidas por producto
     * 
     * Recibe los datos de las medidas a ingresar + datos empleado
     */
    public function registraPrecioProducto(Request $request){
        //capturamos el parametro json
        $json = $request -> input('json', null);
        //lo transformamos en un array
        $params_array = json_decode($json, true);

        //verificamos que no venga vacio
        if(!empty($params_array)){
            try{
                DB::beginTransaction();
                    //consultamos el ultimo ingresado para obtener su id
                    $ultimoProducto = Producto::latest('idProducto')->first()->idProducto;

                    //obtemos el id del usuario
                    $idEmpleado = $params_array['sub'];
                    //obtenemos el nombre de la maquina
                    $pc = gethostname();

                    //eliminamos los datos del empleado
                    //o algo mas tecnico: eliminamos los elementos que no son arrays
                    $params_array = array_filter($params_array, function($item) { return is_array($item); });

                    foreach($params_array AS $param => $paramdata){

                        $productos_medidas = new Productos_medidas();
                        $productos_medidas -> idProducto = $ultimoProducto;
                        $productos_medidas -> idMedida = $paramdata['idMedida'];
                        $productos_medidas -> unidad = $paramdata['unidad'];
                        $productos_medidas -> precioCompra = $paramdata['preciocompra'];

                        $productos_medidas -> porcentaje1 = $paramdata['porcentaje1'];
                        if($paramdata['precio1'] > 0 ){
                            $productos_medidas -> precio1 = $paramdata['precio1'];
                        }

                        $productos_medidas -> porcentaje2 = $paramdata['porcentaje2'];
                        if($paramdata['precio2'] > 0){
                            $productos_medidas -> precio2 = $paramdata['precio2'];
                        }

                        $productos_medidas -> porcentaje3 = $paramdata['porcentaje3'];
                        if($paramdata['precio3'] > 0){
                            $productos_medidas -> precio3 = $paramdata['precio3'];
                        }

                        $productos_medidas -> porcentaje4 = $paramdata['porcentaje4'];
                        if($paramdata['precio4'] > 0){
                            $productos_medidas -> precio4 = $paramdata['precio4'];
                        }

                        $productos_medidas -> porcentaje5 = $paramdata['porcentaje5'];
                        if($paramdata['precio5'] > 0){
                            $productos_medidas -> precio5 = $paramdata['precio5'];
                        }

                        $productos_medidas -> save();

                        //consulta la ultima medida ingresada
                        $ultimaMedida = Productos_medidas::latest('idProdMedida')->first()->idProdMedida;

                        //insertamos el movimiento realizado
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $idEmpleado;
                        $monitoreo -> accion =  "Alta de medida ".$ultimaMedida." para el producto";
                        $monitoreo -> folioNuevo =  $ultimoProducto;
                        $monitoreo -> pc =  $pc;
                        $monitoreo ->save();

                    }//fin foreach

                     $data = array(
                         'code' => 200,
                         'status' => 'success',
                         'message' => 'Precios registrados correctamente',
                         'productos_medidas' => $productos_medidas
                     );
                
                DB::commit();
            } catch(\Exception $e){
                DB::rollback();
                $data = array(
                    'code' => 400,
                    'status' => 'error',
                    'message_system' => 'Algo salio mal rollback precios',
                    'messsage_exception' => $e->getMessage(),
                    'errors' => $e
                );
            }
        } else {
            $data = array(
                'code' => 400,
                'status' => 'error',
                'message' => 'Un campo viene vacio / mal'
            );
        }
        return response()->json($data, $data['code']);
    }

    public function getLastProduct(){
        $productos = Producto::latest('idProducto')->first()->cbarras;
        $productos = $productos+1;
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }
    /**
     * CONSULTA MAL ECHA REVISAR EL WHERE
     */
    public function show($idProducto){

        $producto = DB::table('producto as allproducts')
        ->join('medidas', 'medidas.idMedida','=','allproducts.idMedida')
        ->join('marca', 'marca.idMarca','=','allproducts.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','allproducts.idDep')
        ->join('categoria', 'categoria.idCat','=','allproducts.idCat')
        //->join('subcategoria', 'subcategoria.idSubCat','=','allproducts.idSubCat')
        ->join('almacenes','almacenes.idAlmacen','=','allproducts.idAlmacen')
        ->join('producto', 'producto.idProducto','=','allproducts.idProductoS')
        //->join('pelote','pelote.idProducto','=','allproducts.idProducto')
        ->select('producto.*','allproducts.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca',
                 'departamentos.nombre as nombreDep','categoria.nombre as nombreCat',
                 'almacenes.nombre as nombreAlmacen','producto.claveEx as claveExProductoSiguiente')
        ->where('allproducts.idProducto','=',$idProducto)
        ->get();

        if(is_object($producto)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'producto'   =>  $producto
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
    public function updateStatus($idProducto, Request $request){
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);
        if(!empty($params_array)){
        //quitamos valores que no queremos actualizar
             unset($params_array['idProducto']);
             unset($params_array['idMedida']);
             unset($params_array['idMarca']);
             unset($params_array['idDep']);
             unset($params_array['idCat']);
             unset($params_array['idSubCat']);
             unset($params_array['claveEx']);
             unset($params_array['cbarras']);
             unset($params_array['descripcion']);
             unset($params_array['stockMin']);
             unset($params_array['stockMax']);
             unset($params_array['imagen']);
             unset($params_array['ubicacion']);
             unset($params_array['claveSat']);
             unset($params_array['tEntrega']);
             unset($params_array['idAlmacen']);
             unset($params_array['precioR']);
             unset($params_array['precioS']);
             unset($params_array['idProductoS']);
             unset($params_array['factorConv']);
             unset($params_array['existenciaG']);
             unset($params_array['created_at']);

             //actualizamos
             $producto = Producto::where('idProducto', $idProducto)->update($params_array);
             
             $data = array(
                 'code'         =>  200,
                 'status'       =>  'success',
                 'producto'    =>  $params_array
             );
            }else{
                $data = array(
                    'code'         =>  200,
                    'status'       =>  'error',
                    'message'      =>  'Error al procesar'
                );
            }
            return response()->json($data,$data['code']);
    }
    /**
     * Actualizacion del producto
     */
    public function updateProduct($idProducto, Request $request){
        
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){

            //limpiamos los datos
            $params_array = array_map('trim', $params_array);
            //validamos los datos que llegaron
            $validate = Validator::make($params_array, [
                'idMarca'           =>  'required',
                'idDep'             =>  'required',
                'idCat'             =>  'required',
                'claveEx'           =>  'required',
                'descripcion'       =>  'required',
                'stockMin'          =>  'required',
                'stockMax'          =>  'required',
                //'statuss'           =>  'required',
                'ubicacion'         =>  'required',
                //'claveSat'          =>  'required',
                'tEntrega'          =>  'required',
                'idAlmacen'         =>  'required',
                'existenciaG'       =>  'required'
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
            }else{
                try{
                    DB::beginTransaction();
                    //consultamos el producto antes de actualizarlo
                    $antProducto= Producto::where('idProducto',$params_array['idProducto'])->get();
                    //actualizamos
                    $producto = Producto::where('idProducto',$params_array['idProducto'])->update([
                                    'idMarca' => $params_array['idMarca'],
                                    'idDep' => $params_array['idDep'],
                                    'idCat' => $params_array['idCat'],
                                    'claveEx' => $params_array['claveEx'],
                                    'cbarras' => $params_array['cbarras'],
                                    'descripcion' => $params_array['descripcion'],
                                    'stockMin' => $params_array['stockMin'],
                                    'stockMax' => $params_array['stockMax'],
                                    'imagen' => $params_array['imagen'],
                                    //'statuss' => $params_array['statuss'],
                                    'ubicacion' => $params_array['ubicacion'],
                                    'claveSat' => $params_array['claveSat'],
                                    'tEntrega' => $params_array['tEntrega'],
                                    'idAlmacen' => $params_array['idAlmacen'],
                                ]);
                    //consultamos el producto que se actualizo                                
                    $producto = Producto::where('idProducto',$params_array['idProducto'])->get();
                    
                    //obtenemos el nombre de la maquina
                    $pc = gethostname();
                    
                    //recorremos el producto para ver que atributo cambio y asi guardar la modificacion
                     foreach($antProducto[0]['attributes'] as $clave => $valor){
                         foreach($producto[0]['attributes'] as $clave2 => $valor2){
                            //verificamos que la clave sea igua ejem: claveEx == claveEx
                            // y que los valores sean diferentes para guardar el movimiento Ejem: comex != comex-verde
                            if($clave == $clave2 && $valor !=  $valor2){
                                //insertamos el movimiento realizado
                                $monitoreo = new Monitoreo();
                                $monitoreo -> idUsuario =  $params_array['sub'];
                                $monitoreo -> accion =  "Modificacion de ".$clave." anterior: ".$valor." nueva: ".$valor2." del producto";
                                $monitoreo -> folioNuevo =  $params_array['idProducto'];
                                $monitoreo -> pc =  $pc;
                                $monitoreo ->save();
                            }
                         }
                     }
                    //insertamos el movimiento realizado en general del producto modificado
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['sub'];
                    $monitoreo -> accion =  "Modificacion de producto";
                    $monitoreo -> folioNuevo =  $params_array['idProducto'];
                    $monitoreo -> pc =  $pc;
                    $monitoreo ->save();
                    //generamos respuesta
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  '200',
                        'message'   =>  'El producto se a guardado correctamente',
                        'producto'  =>  $producto
                    );
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
        }
        else{
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Los valores no se recibieron correctamente'
            );
        }
        return response()->json($data, $data['code']);       
    }
    public function updatePrecioProducto($idProducto, Request $request){
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);
        
        if(!empty($params_array)){
            try{
                DB::beginTransaction();
                $antListaPrecio = Productos_medidas::select('productos_medidas.*','medidas.nombre as nombreMedida')
                                    ->join('medidas', 'medidas.idMedida','=','productos_medidas.idMedida')
                                    ->where('idProducto','=',$idProducto)
                                    ->orderBy('productos_medidas.idProdMedida','asc')
                                    ->get();                                               
                //obtemos el id del usuario
                $idEmpleado = $params_array['sub'];
                //obtenemos el nombre de la maquina
                $pc = gethostname();
                //eliminamos los datos del empleado
                //o algo mas tecnico: eliminamos los elementos que no son arrays
                $params_array = array_filter($params_array, function($item) { return is_array($item); });

                //actualizamos precios
                foreach($params_array AS $param => $paramdata){
                    $productos_medidas = Productos_medidas::where('idProdMedida', $paramdata['idProdMedida'])->update($paramdata);
                }

                //insertamos en historial de precios
                $listaPrecioArray = $antListaPrecio->toArray();

                DB::table('historialproductos_medidas')->insert($listaPrecioArray);

                //insertamos el movimiento realizado en general del producto modificado
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario =  $idEmpleado;
                $monitoreo -> accion =  "Actualizacion de precios del producto";
                $monitoreo -> folioNuevo =  $idProducto;
                $monitoreo -> pc =  $pc;
                $monitoreo ->save();


                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Precios actualizados correctamente',
                    'productos_medidas' => $productos_medidas
                );
                DB::commit();
            } catch(\Exception $e){
                DB::rollback();
                $data = array(
                    'code' => 400,
                    'status' => 'error',
                    'message_system' => 'Algo salio mal rollback',
                    'messsage_exception' => $e->getMessage(),
                    'errors' => $e
                );
            }
        } else{
            $data = array(
                'code' => 400,
                'status' => 'error',
                'message' => 'Un campo viene vacio / mal'
            );
        }
        return response()->json($data,$data['code']);
    }
    /**
     * Muestra los detalles del producto con sus medidas
     */
    public function showTwo($idProducto){
        $producto = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        //->join('subcategoria', 'subcategoria.idSubCat','=','producto.idSubCat')
        ->join('almacenes','almacenes.idAlmacen','=','producto.idAlmacen')
        //->join('pelote','pelote.idProducto','=','producto.idProducto')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                 'categoria.nombre as nombreCat','almacenes.nombre as nombreAlmacen')
        ->where('producto.idProducto',$idProducto)
        ->get();
        $productos_medidas = DB::table('productos_medidas')
        ->join('medidas', 'medidas.idMedida','=','productos_medidas.idMedida')
        ->select('productos_medidas.*','medidas.nombre as nombreMedida','productos_medidas.precioCompra as preciocompra')
        ->where('idProducto','=',$idProducto)
        ->orderBy('productos_medidas.idProdMedida','asc')
        ->get();

        if(is_object($producto)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'producto'   =>  $producto,
                'productos_medidas'   =>  $productos_medidas
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
    public function getProductClaveex($claveExterna){
        $producto = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        //->join('subcategoria', 'subcategoria.idSubCat','=','producto.idSubCat')
        ->join('almacenes','almacenes.idAlmacen','=','producto.idAlmacen')
        //->join('pelote','pelote.idProducto','=','producto.idProducto')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca','departamentos.nombre as nombreDep','categoria.nombre as nombreCat','subcategoria.nombre as nombreSubCat','almacenes.nombre as nombreAlmacen')
        ->where('producto.claveEx',$claveExterna)
        ->get();
        if(is_object($producto)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'producto'   =>  $producto
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
    public function getExistenciaG($idProducto){
        $producto = DB::table('producto')
        ->select('idProducto','existenciaG')
        ->where('idProducto',$idProducto)
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'producto'   =>  $producto
        ]);
    }
    
    /* MODULO INVENTARIO->PRODUCTOS */

    /**
     * Busca a partir de la clave externa de los productos
     * que tengan estatus 1 (activos), ademas trae la informacion pagina
     * y los convertimos en formato json
     */
    public function searchClaveEx($claveExterna){
        //GENERAMOS CONSULTA
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                 'categoria.nombre as nombreCat')
        ->where([
            ['claveEx','like','%'.$claveExterna.'%'],
            ['statuss','=',31]
                ])
        ->paginate(5);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    /**
     * Busca a partir del codigo de barras de los productos
     * que tengan estatus 1 (activos)
     */
    public function searchCodbar($codbar){
        //GENERAMOS CONSULTA
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                 'categoria.nombre as nombreCat')
        ->where([
            ['cbarras','like','%'.$codbar.'%'],
            ['statuss','=',31]
                ])
        ->paginate(5);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    /**
     * Busca a partir de su descripcion de los productos
     * que tengan estatus 1 (activos)
     */
    public function searchDescripcion($descripcion){
        //GENERAMOS CONSULTA
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                 'categoria.nombre as nombreCat')
        ->where([
            ['descripcion','like','%'.$descripcion.'%'],
            ['statuss','=',31]
                ])
        ->paginate(5);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    /**
     * Busca a partir de la clave externa de los productos
     * que tengan estatus 2 (inactivos)
     */
    public function searchClaveExInactivos($claveExterna){
        //GENERAMOS CONSULTA
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                 'categoria.nombre as nombreCat')
        ->where('claveEx','like','%'.$claveExterna.'%')
        ->where('statuss',2)
        ->where([
            ['claveEx','like','%'.$claveExterna.'%'],
            ['statuss','=',32]
                ])
        ->paginate(5);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    /**
     * Busca a partir del codigo de barras de los productos
     * que tengan estatus 2 (inactivos)
     */
    public function searchCodbarI($codbar){
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                 'categoria.nombre as nombreCat')
        ->where([
            ['cbarras','like','%'.$codbar.'%'],
            ['statuss','=',32]
                ])
        ->paginate(5);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    /**
     * Busca a partir de su descripcion de los productos
     * que tengan estatus 2 (inactivos)
     */
    public function searchDescripcionI($descripcion){
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                 'categoria.nombre as nombreCat')
        ->where([
            ['descripcion','like','%'.$descripcion.'%'],
            ['statuss','=',32]
                ])
        ->paginate(5);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    public function searchProductoMedida($idProducto){
        try{
            $productoMedida = DB::table('productos_medidas')
                                ->join('medidas','medidas.idMedida','=','productos_medidas.idMedida')
                                ->select('productos_medidas.*','medidas.nombre as nombreMedida')
                                ->where('productos_medidas.idProducto','=',$idProducto)
                                ->get();
                                
            $imagen = Producto::findOrFail($idProducto)->imagen;
            $data = [
                'code'          =>  200,
                'status'        => 'success',
                'productoMedida'   =>  $productoMedida,
                'imagen'        => $imagen
            ];
        } catch(\Exception $e){
            $data = [
                'code' => 200,
                'status' => 'success',
                'message_system' => 'Test',
                'message_Error' => $e->getMessage(),
                'error' => $e

            ];
        }

        return response()->json($data, $data['code']);
    }
    
}
