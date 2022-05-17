<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\Producto;

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
        ->join('subcategoria', 'subcategoria.idSubCat','=','producto.idSubCat')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca','departamentos.nombre as nombreDep','categoria.nombre as nombreCat','subcategoria.nombre as nombreSubCat')
        ->where('statuss',1)
        ->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }
    public function indexPV(){
        config()->set('database.connections.mysql.strict', false);//se agrega este codigo para deshabilitar el forzado de mysql
        ini_set('memory_limit', '-1');// Se agrega para eliminar el limite de memoria asignado
        $productos = DB::table('producto')
        ->join('medidas', 'medidas.idMedida','=','producto.idMedida')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->select('producto.idProducto','producto.claveEx','producto.cbarras','producto.descripcion','producto.precioS','producto.precioR','producto.existenciaG','medidas.nombre as nombreMedida','marca.nombre as nombreMarca')
        ->where('statuss',1)
        ->get();
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
    public function register(Request $request){

        $json = $request -> input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if( !empty($params_array) && !empty($params_array)){

            $params_array = array_map('trim', $params_array);//limpiamos los datos

            $validate = Validator::make($params_array, [
                'idMedida'          =>  'required',
                'idMarca'           =>  'required',
                'idDep'             =>  'required',
                'idCat'             =>  'required',
                'idSubCat'          =>  'required',
                'claveEx'           =>  'required',
                'cbarras'           =>  'required',
                'descripcion'       =>  'required',
                'stockMin'          =>  'required',
                'stockMax'          =>  'required',
                //'imagen'            =>  'required',
                'statuss'           =>  'required',
                'ubicacion'         =>  'required',
                'tEntrega'          =>  'required',
                'idAlmacen'         =>  'required',
                'precioR'           =>  'required',
                'precioS'           =>  'required',
                'factorConv'        =>  'required',
                'existenciaG'       =>  'required'
            ]);
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message'   =>  'Fallo la validacion de los datos del producto',
                    'errors'    =>  $validate->errors()
                );
            }else{
                if($params_array['idProductoS']== null || $params_array['idProductoS']== 0){
                    $producto = new Producto();
                    $producto -> idMedida = $params_array['idMedida'];
                    $producto -> idMarca = $params_array['idMarca'];
                    $producto -> idDep = $params_array['idDep'];
                    $producto -> idCat = $params_array['idCat'];
                    $producto -> idSubCat = $params_array['idSubCat'];
                    $producto -> claveEx = $params_array['claveEx'];
                    $producto -> cbarras = $params_array['cbarras'];
                    $producto -> descripcion = $params_array['descripcion'];
                    $producto -> stockMin = $params_array['stockMin'];
                    $producto -> stockMax = $params_array['stockMax'];
                    $producto -> imagen = $params_array['imagen'];
                    $producto -> statuss = $params_array['statuss'];
                    $producto -> ubicacion = $params_array['ubicacion'];
                    $producto -> claveSat = $params_array['claveSat'];
                    $producto -> tEntrega = $params_array['tEntrega'];
                    $producto -> idAlmacen = $params_array['idAlmacen'];
                    $producto -> precioR = $params_array['precioR'];
                    $producto -> precioS = $params_array['precioS'];                    
                    $producto -> factorConv = $params_array['factorConv'];
                    $producto -> existenciaG = $params_array['existenciaG'];
    
                    $producto->save();
    
                    $data = array(//una vez guardado mandamos mensaje de OK
                        'status'    =>  'success',
                        'code'      =>  '200',
                        'message'   =>  'El producto se a guardado correctamente',
                        'producto' =>  $producto
                    );
                }
                else{
                    $producto = new Producto();
                    $producto -> idMedida = $params_array['idMedida'];
                    $producto -> idMarca = $params_array['idMarca'];
                    $producto -> idDep = $params_array['idDep'];
                    $producto -> idCat = $params_array['idCat'];
                    $producto -> idSubCat = $params_array['idSubCat'];
                    $producto -> claveEx = $params_array['claveEx'];
                    $producto -> cbarras = $params_array['cbarras'];
                    $producto -> descripcion = $params_array['descripcion'];
                    $producto -> stockMin = $params_array['stockMin'];
                    $producto -> stockMax = $params_array['stockMax'];
                    $producto -> imagen = $params_array['imagen'];
                    $producto -> statuss = $params_array['statuss'];
                    $producto -> ubicacion = $params_array['ubicacion'];
                    $producto -> claveSat = $params_array['claveSat'];
                    $producto -> tEntrega = $params_array['tEntrega'];
                    $producto -> idAlmacen = $params_array['idAlmacen'];
                    $producto -> precioR = $params_array['precioR'];
                    $producto -> precioS = $params_array['precioS'];
                    $producto -> idProductoS = $params_array['idProductoS'];
                    $producto -> factorConv = $params_array['factorConv'];
                    $producto -> existenciaG = $params_array['existenciaG'];
    
                    $producto->save();
    
                    $data = array(//una vez guardado mandamos mensaje de OK
                        'status'    =>  'success',
                        'code'      =>  '200',
                        'message'   =>  'El producto se a guardado correctamente',
                        'producto' =>  $producto
                    );
                }
                
            }

        }else{
            $data =  array(
                'status'        => 'error',
                'code'          =>  '404',
                'message'       =>  'Los datos enviados no son correctos'
            );
        }
        return response()->json($data, $data['code']);
    }
    public function getLastProduct(){
        $productos = Producto::latest('idProducto')->first();
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
        ->join('subcategoria', 'subcategoria.idSubCat','=','allproducts.idSubCat')
        ->join('almacenes','almacenes.idAlmacen','=','allproducts.idAlmacen')
        ->join('producto', 'producto.idProducto','=','allproducts.idProductoS')
        //->join('pelote','pelote.idProducto','=','allproducts.idProducto')
        ->select('producto.*','allproducts.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca','departamentos.nombre as nombreDep','categoria.nombre as nombreCat','subcategoria.nombre as nombreSubCat','almacenes.nombre as nombreAlmacen','producto.claveEx as claveExProductoSiguiente')
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
        ->join('subcategoria', 'subcategoria.idSubCat','=','producto.idSubCat')
        ->join('almacenes','almacenes.idAlmacen','=','producto.idAlmacen')
        //->join('pelote','pelote.idProducto','=','producto.idProducto')
        ->select('producto.*','medidas.nombre as nombreMedida','marca.nombre as nombreMarca','departamentos.nombre as nombreDep','categoria.nombre as nombreCat','subcategoria.nombre as nombreSubCat','almacenes.nombre as nombreAlmacen')
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
}
/**** */
