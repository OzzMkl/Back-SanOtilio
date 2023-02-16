<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\Producto;
use App\Productos_precios;

class ProductoController extends Controller
{
    public function index(){
        //GENERAMOS CONSULTA
        config()->set('database.connections.mysql.strict', false);//se agrega este codigo para deshabilitar el forzado de mysql
        ini_set('memory_limit', '-1');// Se agrega para eliminar el limite de memoria asignado
        $productos = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        //->join('subcategoria', 'subcategoria.idSubCat','=','producto.idSubCat')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca',
                 'departamentos.nombre as nombreDep','categoria.nombre as nombreCat')
        ->where('statuss',1)
        ->paginate(10);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }
    //Trae la informacion de los productos para el modulo de punto de venta
    public function indexPV(){
        $productos = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.idProducto','producto.claveEx','producto.cbarras','producto.descripcion','producto.existenciaG','medidas.nombre as nombreMedida','marca.nombre as nombreMarca')
        ->where('statuss',1)
        ->paginate(10);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }
    public function productoDes(){
        config()->set('database.connections.mysql.strict', false);//se agrega este codigo para deshabilitar el forzado de mysql
        $productos = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->join('subcategoria', 'subcategoria.idSubCat','=','producto.idSubCat')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca','departamentos.nombre as nombreDep','categoria.nombre as nombreCat','subcategoria.nombre as nombreSubCat')
        ->where('statuss',2)
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }
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
            }else{
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
     * Recibe los datos del objeto junto con las cabeceras
     */
    public function register(Request $request){
        
        //tomamos solo el json
        $json = $request -> input('json', null);
        //lo codificamos como json
        $params = json_decode($json);
        //se separa y se ponen como array
        $params_array = json_decode($json, true);

            //revisamos que no vengan vacios
        if( !empty($params_array) && !empty($params)){
            //limpiamos los datos
            $params_array = array_map('trim', $params_array);
            //validamos los datos que llegaron
            $validate = Validator::make($params_array, [
                'idMedida'          =>  'required',
                'idMarca'           =>  'required',
                'idDep'             =>  'required',
                'idCat'             =>  'required',
                //'idSubCat'          =>  'required',
                'claveEx'           =>  'required',
                //'cbarras'           =>  'required',
                'descripcion'       =>  'required',
                'stockMin'          =>  'required',
                'stockMax'          =>  'required',
                //'imagen'            =>  'required',
                'statuss'           =>  'required',
                'ubicacion'         =>  'required',
                //'claveSat'          =>  'required',
                'tEntrega'          =>  'required',
                'idAlmacen'         =>  'required',
                //'idProductoS'       =>  'required',
                'factorConv'        =>  'required',
                'existenciaG'       =>  'required'
            ]);
            //si falla creamos la respuesta a enviar
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message'   =>  'Fallo la validacion de los datos del producto',
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
                    $producto -> idMedida = $params_array['idMedida'];
                    $producto -> idMarca = $params_array['idMarca'];
                    $producto -> idDep = $params_array['idDep'];
                    $producto -> idCat = $params_array['idCat'];
                    //$producto -> idSubCat = $params_array['idSubCat'];
                    $producto -> claveEx = $params_array['claveEx'];
                    $producto -> cbarras = $ultimoCbarras;//aqui ingresamos el codigo de barras consultado
                    $producto -> descripcion = $params_array['descripcion'];
                    $producto -> stockMin = $params_array['stockMin'];
                    $producto -> stockMax = $params_array['stockMax'];
                    if( isset($params_array['imagen'])){
                        $producto -> imagen = $params_array['imagen'];
                    }
                    $producto -> statuss = $params_array['statuss'];
                    $producto -> ubicacion = $params_array['ubicacion'];
                    $producto -> claveSat = $params_array['claveSat'];
                    $producto -> tEntrega = $params_array['tEntrega'];
                    $producto -> idAlmacen = $params_array['idAlmacen'];
                    if( $params_array['idProductoS'] != '' || $params_array['idProductoS'] != null){
                        $producto -> idProductoS = $params_array['idProductoS'];
                    }
                    $producto -> factorConv = $params_array['factorConv'];
                    $producto -> existenciaG = $params_array['existenciaG'];
                    //guardamos
                    $producto->save();
                    //una vez guardado mandamos mensaje de OK

                    return $this->registraPrecioProducto($request);

                    // $data = array(
                    //     'status'    =>  'success',
                    //     'code'      =>  '200',
                    //     'message'   =>  'El producto se a guardado correctamente',
                    //     'producto'  =>  $producto
                    //     //'precios'   =>  $precios
                    // );
                    DB::commit();
                } catch (\Exception $e){
                    DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   => 'Algo salio mal rollback',
                        'error'     => $e
                    );
                }
            }

        }else{
            $data =  array(
                'code'          =>  400,
                'status'        => 'error',
                'message'       =>  'Un campo viene vacio'
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
        ->where('allproducts.idProducto',$idProducto)
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
    public function updateProduct($idProducto, Request $request){
        $json = $request -> input('json',null);
        $params_array = json_decode($json, true);
        if(!empty($params_array)){
            unset($params_array['idProducto']);
            unset($params_array['created_at']);
            unset($params_array['statuss']);
            unset($params_array['imagen']);

            if($params_array['idProductoS'] == null){//algo curiso paso aqui pero es que si no se le asigna desde aqui el null, la Api muestra error
                $params_array['idProductoS'] =null;//ya que no mandanada xd
            }

            //actualizamos
            $producto = Producto::where('idProducto', $idProducto)->update($params_array);
             
            $data = array(
                'code'         =>  200,
                'status'       =>  'success',
                'producto'    =>  $params_array
            );
        }
        else{
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Error al procesar'
            );
        }
        return response()->json($data, $data['code']);       
    }
    public function showTwo($idProducto){
        $producto = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        //->join('subcategoria', 'subcategoria.idSubCat','=','producto.idSubCat')
        ->join('almacenes','almacenes.idAlmacen','=','producto.idAlmacen')
        //->join('pelote','pelote.idProducto','=','producto.idProducto')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca','departamentos.nombre as nombreDep',
                 'categoria.nombre as nombreCat','almacenes.nombre as nombreAlmacen')
        ->where('producto.idProducto',$idProducto)
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
    public function getProductClaveex($claveExterna){
        $producto = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->join('categoria', 'categoria.idCat','=','producto.idCat')
        ->join('subcategoria', 'subcategoria.idSubCat','=','producto.idSubCat')
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
    public function registraPrecioProducto(Request $request){

        $json = $request -> input('json', null);
        //echo $json;
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if(!empty($params) && !empty($params_array)){
            $params_array = array_map('trim', $params_array);

            $validate = Validator::make($params_array, [
                'preciocompra'      =>  'required',
                'precio5'           =>  'required',
                'porcentaje5'       =>  'required',
                'precio4'           =>  'required',
                'porcentaje4'       =>  'required',
                'precio3'           =>  'required',
                'porcentaje3'       =>  'required',
            ]);

            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message'   =>  'Fallo la validacion de los datos del producto',
                    'errors'    =>  $validate->errors()
                );
            } else{
                try{
                    DB::beginTransaction();

                    if($params_array['precio5'] <= 0 || $params_array['precio4']  <= 0 || $params_array['precio3'] <= 0){

                        $ultimoProducto = Producto::latest('idProducto')->first()->idProducto;

                        $precios = new Productos_precios();
                        $precios -> idProducto = $ultimoProducto;
                        $precios -> preciocompra = $params_array['preciocompra'];
                        $precios -> precio5 = $params_array['precio5'];
                        $precios -> porcentaje5 = $params_array['porcentaje5'];
                        $precios -> precio4 = $params_array['precio4'];
                        $precios -> porcentaje4 = $params_array['porcentaje4'];
                        $precios -> precio3 = $params_array['precio3'];
                        $precios -> porcentaje3 = $params_array['porcentaje3'];
                        if( isset($params_array['precio2'])){
                            $precios -> precio2 = $params_array['precio2'];
                        }
                        if( isset($params_array['porcentaje2'])){
                            $precios -> porcentaje2 = $params_array['porcentaje2'];
                        }
                        if( isset($params_array['precio1'])){
                            $precios -> precio1 = $params_array['precio1'];
                        }
                        if( isset($params_array['porcentaje1'])){
                            $precios -> porcentaje1 = $params_array['porcentaje1'];
                        }
                        $precios -> save();
                        
                        $data = array(
                            'code' => 200,
                            'status' => 'success',
                            'message' => 'Precios registrados correctamente',
                            'precios' => $precios
                        );
                    } else {
                        $data = array(
                            'code' => 400,
                            'status' => 'error',
                            'message' => 'Los precios de venta no pueden ser menores o iguales a cero',
                        );
                    }
                    
                    DB::commit();
                } catch(\Exception $e){
                    DB::rollback();
                    $data = array(
                        'code' => 400,
                        'status' => 'error',
                        'message' => 'Algo salio mal rollback',
                        'errors' => $e
                    );
                }

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
    /* MODULO INVENTARIO->PRODUCTOS */

    /**
     * Busca a partir de la clave externa de los productos
     * que tengan estatus 1 (activos), ademas trae la informacion pagina
     * y los convertimos en formato json
     */
    public function searchClaveEx($claveExterna){
        //GENERAMOS CONSULTA
        $productos = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca')
        ->where('claveEx','like','%'.$claveExterna.'%')
        ->where('statuss',1)
        ->paginate(10);
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
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca')
        ->where('cbarras','like','%'.$codbar.'%')
        ->where('statuss',1)
        ->paginate(10);
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
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca')
        ->where('descripcion','like','%'.$descripcion.'%')
        ->where('statuss',1)
        ->paginate(10);
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
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca')
        ->where('claveEx','like','%'.$claveExterna.'%')
        ->where('statuss',2)
        ->paginate(10);
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
        //GENERAMOS CONSULTA
        $productos = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca')
        ->where([
            ['statuss','=','2'],
            ['cbarras','like','%'.$codbar.'%']
                ])
        ->paginate(10);
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
        //GENERAMOS CONSULTA
        $productos = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca')
        ->where('descripcion','like','%'.$descripcion.'%')
        ->where('statuss',2)
        ->paginate(10);
        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }
    
}
/**** */
