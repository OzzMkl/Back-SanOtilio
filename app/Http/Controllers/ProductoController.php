<?php

namespace App\Http\Controllers;

use App\Medidas;
use App\models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Validator;
use App\Producto;
use App\Productos_medidas;
use App\models\Monitoreo;
use App\models\historialproductos_medidas;
use Carbon\Carbon;
use App\Clases\clsHelpers;
use App\models\Empresa;
use App\models\inventario\Historial_producto;

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
     * Funciona para los productos del modulo de punto de venta
     * Cotizaciones
     *
     */
    public function indexPV(){
        $productos = DB::table('producto')
        ->join('marca', 'marca.idMarca','=','producto.idMarca')
        ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
        ->select('producto.idProducto','producto.claveEx','producto.cbarras','producto.descripcion',
                    'producto.existenciaG','marca.nombre as nombreMarca',
                    'departamentos.nombre as nombreDep')
        ->where('statuss',31)
        ->paginate(10);
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            'productos'   =>  $productos
        ]);
    }

    public function newIndex($type, $search){
        $productos = DB::table('producto')
            ->join('marca', 'marca.idMarca','=','producto.idMarca')
            ->join('departamentos', 'departamentos.idDep','=','producto.idDep')
            ->select('producto.idProducto','producto.claveEx','producto.cbarras','producto.descripcion',
                        'producto.existenciaG','marca.nombre as nombreMarca',
                        'departamentos.nombre as nombreDep')
            ->where('statuss',31);
            //ClaveExterna
            if($type == 1 && $search != 'null'){
                $productos->where('producto.claveEx','like','%'.$search.'%');
            }
            //Descripcion
            if($type == 2 && $search != 'null'){
                $productos->where('producto.descripcion','like','%'.$search.'%');
            }
            //codigo de barras
            if($type == 3 && $search != 'null'){
                $productos->where('producto.cbarras','like','%'.$search.'%');
            }
            $productos = $productos->paginate(5);
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

        // dd($params_array['producto']);
            //revisamos que no vengan vacios
        if( !empty($params_array)){
            //Eliminamos el array de permisos para que unicamente quede el array que contiene
            //los datos del producto y del usuario sin los permisos claro ...
            // unset($params_array['permisos']);
            //limpiamos los datos
            $params_array['producto'] = array_map('trim', $params_array['producto']);
            //validamos los datos que llegaron
            $validate = Validator::make($params_array['producto'], [
                'idMarca'           =>  'required',
                'idDep'             =>  'required',
                'claveEx'           =>  'required | unique:producto',
                'descripcion'       =>  'required',
                'stockMin'          =>  'required',
                'stockMax'          =>  'required',
                'tEntrega'          =>  'required',
            ]);
            //si falla creamos la respuesta a enviar
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message_system'   =>  'Fallo la validacion de los datos del producto',
                    'errors'    =>  $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();
                    DB::enableQueryLog();

                    //consultamos el ultimo producto registrado y extraemos su codigo de barras
                    $ultimoCbarras = Producto::latest('idProducto')->first()->cbarras;
                    //sumamos +1 AL CODIGO DE BARRAS
                    $ultimoCbarras = $ultimoCbarras +1;

                    //creamos el producto a ingresar
                    $producto = new Producto();
                    $producto -> idMarca = $params_array['producto']['idMarca'];
                    $producto -> idDep = $params_array['producto']['idDep'];
                    $producto -> idCat = $params_array['producto']['idCat'];
                    $producto -> claveEx = $params_array['producto']['claveEx'];
                    $producto -> cbarras = $ultimoCbarras;//aqui ingresamos el codigo de barras consultado
                    $producto -> descripcion = $params_array['producto']['descripcion'];
                    $producto -> stockMin = $params_array['producto']['stockMin'];
                    $producto -> stockMax = $params_array['producto']['stockMax'];
                    if( isset($params_array['producto']['imagen'])){
                        $producto -> imagen = $params_array['producto']['imagen'];
                    }
                    $producto -> statuss = 31;
                    $producto -> ubicacion = $params_array['producto']['ubicacion'];
                    $producto -> claveSat = $params_array['producto']['claveSat'];
                    $producto -> tEntrega = $params_array['producto']['tEntrega'];
                    $producto -> idAlmacen = $params_array['producto']['idAlmacen'];
                    $producto -> existenciaG = $params_array['producto']['existenciaG'];
                    $producto -> created_at = Carbon::now();
                    $producto -> updated_at = Carbon::now();
                    //guardamos
                    $producto->save();
                    //una vez guardado mandamos mensaje de OK

                    //consultamos el ultimo producto ingresado
                    $ultimoProducto = Producto::with('marca','departamento','categoria','status','almacen')
                                        ->find($producto->idProducto);
                    //Insertamos el registro del producto al historial
                    Historial_producto::insertHistorial_producto($ultimoProducto, $params_array['empleado']['sub']);

                    //obtenemos direccion ip
                    $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                    //insertamos el movimiento realizado
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['empleado']['sub'];
                    $monitoreo -> accion =  "Alta de producto";
                    $monitoreo -> folioNuevo =  $ultimoProducto->idProducto;
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    /**** */
                    $dataPrecios = $this->registraPrecioProducto($params_array['empleado']['sub'],$ultimoProducto->idProducto,$params_array['lista_productosMedida']);
                    /**** */

                    /***Empieza registro en otras sucursales */
                    $data_registroMultiSucursal = $this->registraProductoMultiSucursal($ultimoProducto,$params_array['empleado']['sub'],$params_array['lista_productosMedida']);
                    // $data_registroPrecioMultiSucursal = $this->registraPrecioProductoMultiSucursal($params_array['empleado']['sub'],$ultimoProducto->idProducto,$params_array['lista_productosMedida']);

                    //generamos respuesta
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  '200',
                        'message'   =>  'El producto se a guardado correctamente',
                        'producto'  =>  $producto,
                        'data_precios_local'  =>  $dataPrecios,
                        'data_producto_multi'  =>  $data_registroMultiSucursal,
                    );

                    /******GUARDACONSULTA */
                    $file = fopen('queries.txt', 'a');
                    fwrite($file, "--REGISTRA PRODUCTO" . ";\n");
                    $queries = \DB::getQueryLog();
                    foreach($queries as $query) {
                        $bindings = $query['bindings'];
                        $sql = $query['query'];
                        foreach($bindings as $binding) {
                            $value = is_numeric($binding) ? $binding : "'".$binding."'";
                            $sql = preg_replace('/\?/', $value, $sql, 1);
                        }
                        fwrite($file, $sql . ";\n");
                    }
                    fclose($file);
                    /****** */


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
     * Agrega el producto en las diferentes conexiones que se tengan declaradas
     * dentro de la funcion tambien se registran los precios
     */
    function registraProductoMultiSucursal($producto,$idEmpleado,$lista_precios_medida){
        $arr_ProductoMultisucursal_ok = [];
        $arr_ProductoMultisucursal_error = [];
        $sucursal_con = DB::table('sucursal')
                        ->whereNotNull('connection')
                        ->where('connection', '<>', 'matriz')
                        ->get();
                        // dd($sucursal_con);
            for($i=0;$i<count($sucursal_con);$i++){
            // for($i=0;$i<1;$i++){
                // dd($sucursal_con[$i]->connection);
                try{
                    DB::connection($sucursal_con[$i]->connection)->beginTransaction();
                        DB::connection($sucursal_con[$i]->connection)->table('producto')->insert([
                            'idProducto' => $producto->idProducto,
                            'idMarca' => $producto->idMarca,
                            'idDep' => $producto->idDep,
                            'idCat' => $producto->idCat,
                            'claveEx' => $producto->claveEx,
                            'cbarras' => $producto->cbarras,
                            'descripcion' => $producto->descripcion,
                            'stockMin' => $producto->stockMin,
                            'stockMax' => $producto->stockMax,
                            'imagen' => $producto->imagen,
                            'statuss' => $producto->statuss,
                            'ubicacion' => $producto->ubicacion,
                            'claveSat' => $producto->claveSat,
                            'tEntrega' => $producto->tEntrega,
                            'idAlmacen' => $producto->idAlmacen,
                            'existenciaG' => $producto->existenciaG,
                            'created_at' => $producto->created_at,
                            'updated_at' =>  $producto->updated_at,
                        ]);

                        //Registramos historial
                        DB::connection($sucursal_con[$i]->connection)->table('historial_producto')->insert([
                            'idProducto' => $producto->idProducto,
                            'idMarca' => $producto->idMarca,
                            'nombreMarca' => $producto->marca->nombre,
                            'idDep' => $producto->idDep,
                            'nombreDep' => $producto->departamento->nombre,
                            'idCat' => $producto->idCat,
                            'nombreCat' => $producto->categoria->nombre,
                            'claveEx' => $producto->claveEx,
                            'cbarras' => $producto->cbarras,
                            'descripcion' => $producto->descripcion,
                            'stockMin' => $producto->stockMin,
                            'stockMax' => $producto->stockMax,
                            'imagen' => $producto->imagen,
                            'idStatus' => $producto->statuss,
                            'nombreStatus' => $producto->status->nombre,
                            'ubicacion' => $producto->ubicacion,
                            'claveSat' => $producto->claveSat,
                            'tEntrega' => $producto->tEntrega,
                            'idAlmacen' => $producto->idAlmacen,
                            'nombreAlmacen' => $producto->almacen->nombre,
                            'existenciaG' => $producto->existenciaG,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);

                        //registramos precios
                        $data_registroPrecioMultiSucursal = $this->registraPrecioProductoMultiSucursal(
                                                                $idEmpleado,
                                                                $producto->idProducto,
                                                                $lista_precios_medida,
                                                                $sucursal_con[$i]->connection
                                                            );

                        //generamos respuesta
                        $data = array(
                            'status'    =>  'success',
                            'code'      =>  '200',
                            'message'   =>  'El producto se a guardado correctamente',
                            'producto'  =>  $producto,
                            'data_preciosMultiSucursal'  =>  $data_registroPrecioMultiSucursal,
                            'nombre_sucursal' => $sucursal_con[$i]->connection
                        );

                        $arr_ProductoMultisucursal_ok [] = $data;


                        DB::connection($sucursal_con[$i]->connection)->commit();
                } catch (\Exception $e) {
                    DB::connection($sucursal_con[$i]->connection)->rollback();
                    // throw $e;
                    $data =  array(
                        'code'    => 400,
                        'status'  => 'error',
                        'message' => 'Fallo al registrar el producto en la sucursal '.$sucursal_con[$i]->connection,
                        'error'   => $e,
                        'nombre_sucursal' => $sucursal_con[$i]->connection,
                    );

                    $arr_ProductoMultisucursal_error [] = $data;
                    // break;
                }
            }
        return ['sucursales_guardadas' => $arr_ProductoMultisucursal_ok, 'sucursales_fallidas' => $arr_ProductoMultisucursal_error];
    }

    /**
     * Guarda las medidas por producto
     *
     * Recibe los datos de las medidas a ingresar + datos empleado
     */
    public function registraPrecioProducto($idEmpleado, $idProducto, $lista_precios_medida){

        //verificamos que los datos no vengan vacios
        if(!empty($idEmpleado) && !empty($idProducto) && !empty($lista_precios_medida)){
            try{
                DB::beginTransaction();
                DB::enableQueryLog();

                    //obtenemos direccion ip
                    $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                    $dateNow = Carbon::now();
                    foreach($lista_precios_medida AS $param => $paramdata){

                        $productos_medidas = new Productos_medidas();
                        $productos_medidas -> idProducto = $idProducto;
                        $productos_medidas -> idMedida = $paramdata['idMedida'];
                        $productos_medidas -> unidad = $paramdata['unidad'];
                        $productos_medidas -> precioCompra = $paramdata['preciocompra'];

                        $productos_medidas -> porcentaje1 = $paramdata['porcentaje1'];
                        $productos_medidas -> precio1 = $paramdata['precio1'];

                        $productos_medidas -> porcentaje2 = $paramdata['porcentaje2'];
                        $productos_medidas -> precio2 = $paramdata['precio2'];

                        $productos_medidas -> porcentaje3 = $paramdata['porcentaje3'];
                        $productos_medidas -> precio3 = $paramdata['precio3'];

                        $productos_medidas -> porcentaje4 = $paramdata['porcentaje4'];
                        $productos_medidas -> precio4 = $paramdata['precio4'];

                        $productos_medidas -> porcentaje5 = $paramdata['porcentaje5'];
                        $productos_medidas -> precio5 = $paramdata['precio5'];
                        $productos_medidas -> idStatus = 31;
                        $productos_medidas -> created_at = $dateNow;
                        $productos_medidas -> updated_at = $dateNow;
                        $productos_medidas -> save();

                        //consulta la ultima medida ingresada
                        // $ultimaMedida = Productos_medidas::latest('idProdMedida')->first()->idProdMedida;
                        // $nomMedida = DB::table('medidas')->where('idMedida',$paramdata['idMedida'])->get();
                        $nomMedida = Medidas::find($productos_medidas->idMedida)->value('nombre');

                        /**hisotiroa*/
                        $historialPM = new historialproductos_medidas();
                        $historialPM -> idProdMedida = $productos_medidas->idProdMedida;
                        $historialPM -> idEmpleado = $idEmpleado;
                        $historialPM -> idProducto = $idProducto;
                        $historialPM -> idMedida = $paramdata['idMedida'];
                        $historialPM -> nombreMedida = $nomMedida;
                        $historialPM -> unidad = $paramdata['unidad'];
                        $historialPM -> precioCompra = $paramdata['preciocompra'];

                        $historialPM -> porcentaje1 = $paramdata['porcentaje1'];
                        $historialPM -> precio1 = $paramdata['precio1'];

                        $historialPM -> porcentaje2 = $paramdata['porcentaje2'];
                        $historialPM -> precio2 = $paramdata['precio2'];

                        $historialPM -> porcentaje3 = $paramdata['porcentaje3'];
                        $historialPM -> precio3 = $paramdata['precio3'];

                        $historialPM -> porcentaje4 = $paramdata['porcentaje4'];
                        $historialPM -> precio4 = $paramdata['precio4'];

                        $historialPM -> porcentaje5 = $paramdata['porcentaje5'];
                        $historialPM -> precio5 = $paramdata['precio5'];

                        $historialPM -> idStatus = 31;
                        $historialPM -> created_at = $dateNow;
                        $historialPM -> updated_at = $dateNow;
                        $historialPM -> save();
                        /** */

                        //insertamos el movimiento realizado
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $idEmpleado;
                        $monitoreo -> accion =  "Alta de medida ".$productos_medidas->idProdMedida." para el producto";
                        $monitoreo -> folioNuevo =  $idProducto;
                        $monitoreo -> pc =  $ip;
                        $monitoreo ->save();

                    }//fin foreach

                     $data = array(
                         'code' => 200,
                         'status' => 'success',
                         'message' => 'Precios registrados correctamente',
                         'productos_medidas' => $productos_medidas
                     );


                     /******GUARDACONSULTA */
                    $file = fopen('queries.txt', 'a');
                    fwrite($file, "--REGISTRA PRECIOS PRODUCTO" . ";\n");
                    $queries = \DB::getQueryLog();
                    foreach($queries as $query) {
                        $bindings = $query['bindings'];
                        $sql = $query['query'];
                        foreach($bindings as $binding) {
                            $value = is_numeric($binding) ? $binding : "'".$binding."'";
                            $sql = preg_replace('/\?/', $value, $sql, 1);
                        }
                        fwrite($file, $sql . ";\n");
                    }
                    fclose($file);
                    /****** */

                DB::commit();
            } catch(\Exception $e){
                DB::rollback();
                // throw $e;
                $data = array(
                    'code' => 400,
                    'status' => 'error',
                    'message_system' => 'Algo salio mal al guardar los precios.',
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
        return $data;
    }

    /**
     * Registra los precios de los productos registrados en las demas sucursales
     */
    function registraPrecioProductoMultiSucursal($idEmpleado, $idProducto, $lista_precios_medida,$connection){
                try{
                    DB::connection($connection)->beginTransaction();
                    //obtenemos direccion ip
                    $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                    $dateNow = Carbon::now();
                    foreach($lista_precios_medida AS $param => $paramdata){

                        DB::connection($connection)->table('productos_medidas')->insert([
                            'idProducto' => $idProducto,
                            'idMedida' => $paramdata['idMedida'],
                            'unidad' => $paramdata['unidad'],
                            'precioCompra' => $paramdata['preciocompra'],

                            'porcentaje1' => $paramdata['porcentaje1'],
                            'precio1' => $paramdata['precio1'],

                            'porcentaje2' => $paramdata['porcentaje2'],
                            'precio2' => $paramdata['precio2'],

                            'porcentaje3' => $paramdata['porcentaje3'],
                            'precio3' => $paramdata['precio3'],

                            'porcentaje4' => $paramdata['porcentaje4'],
                            'precio4' => $paramdata['precio4'],

                            'porcentaje5' => $paramdata['porcentaje5'],
                            'precio5' => $paramdata['precio5'],

                            'idStatus' => 31,
                            'created_at' => $dateNow,
                            'updated_at' => $dateNow,
                        ]);

                        //consulta la ultima medida ingresada
                        $ultimaMedida = DB::connection($connection)->table('productos_medidas')->latest()->first()->idProdMedida;
                        $nomMedida = DB::connection($connection)->table('medidas')->where('idMedida',$paramdata['idMedida'])->first()->nombre;

                        /**Historial*/
                        DB::connection($connection)->table('historialproductos_medidas')->insert([
                            'idProdMedida'=> $ultimaMedida,
                            'idEmpleado'=> $idEmpleado,
                            'idProducto'=> $idProducto,
                            'idMedida'=> $paramdata['idMedida'],
                            'nombreMedida'=> $nomMedida,
                            'unidad'=> $paramdata['unidad'],
                            'precioCompra'=> $paramdata['preciocompra'],//delete

                            'porcentaje1'=> $paramdata['porcentaje1'],
                            'precio1'=> $paramdata['precio1'],

                            'porcentaje2'=> $paramdata['porcentaje2'],
                            'precio2'=> $paramdata['precio2'],

                            'porcentaje3'=> $paramdata['porcentaje3'],
                            'precio3'=> $paramdata['precio3'],

                            'porcentaje4'=> $paramdata['porcentaje4'],
                            'precio4'=> $paramdata['precio4'],

                            'porcentaje5'=> $paramdata['porcentaje5'],
                            'precio5'=> $paramdata['precio5'],

                            'idStatus' => 31,
                            'created_at' => $dateNow,
                            'updated_at' => $dateNow,
                        ]);

                        //insertamos el movimiento realizado
                        DB::connection($connection)->table('monitoreo')->insert([
                            'idUsuario'=> $ultimaMedida,
                            'accion'=> "Alta de medida ".$ultimaMedida." para el producto",
                            'folioNuevo'=> $idProducto,
                            'pc'=> $ip,
                        ]);

                    }//fin foreach

                    //generamos respuesta
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  '200',
                        'message'   =>  'Precios registrado correctamente',
                    );

                    DB::connection($connection)->commit();

                } catch (\Exception $e) {
                    DB::connection($connection)->rollback();
                    // throw $e;
                    $data =  array(
                        'code'    => 400,
                        'status'  => 'error',
                        'message' => 'Fallo al registrar el producto en la sucursal '.$connection,
                        'error'   => $e
                    );
                    // break;
                }
        return $data;
    }

    /************ */

    /**
     * Actualiza unicamente el status del producto
     * de HABILITADO -> DESHABILITADO  y viceversa
     */
    public function updateStatus($idProducto, Request $request){
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            try{
                DB::beginTransaction();

                //Eliminamos el array de permisos para que unicamente quede el array que contiene
                //los datos del producto y del usuario sin los permisos claro ...
                unset($params_array['permisos']);

                //traemos el status del producto a actualizar
                $statusProd = Producto::find($idProducto)->statuss;
                //obtenemos direccion ip
                $ip = $_SERVER['REMOTE_ADDR'];

                switch($statusProd){
                    case 31:
                            //Si esta habilitado lo deshabilitamos
                            $producto = Producto::where('idProducto',$idProducto)
                                                    ->update([
                                                        'statuss' => 32
                                                    ]);
                            //insertamos el movimiento que se hizo
                            $monitoreo = new Monitoreo();
                            $monitoreo -> idUsuario = $params_array['sub'] ;
                            $monitoreo -> accion =  "Actualizacion de status a deshabilitado del producto";
                            $monitoreo -> folioNuevo =  $idProducto;
                            $monitoreo -> pc =  $ip;
                            $monitoreo ->save();

                            //generamos respuesta del movimiento que se hizo
                            $data = array(
                                'code'      => 200,
                                'status'    => 'success',
                                'message'   =>  'Producto con id: '.$idProducto.' actualizado a idStatus: 32'
                            );
                        break;
                    case 32:
                            //Si esta habilitado lo deshabilitamos
                            $producto = Producto::where('idProducto',$idProducto)
                                                    ->update([
                                                        'statuss' => 31
                                                    ]);
                            //insertamos el movimiento que se hizo
                            $monitoreo = new Monitoreo();
                            $monitoreo -> idUsuario = $params_array['sub'] ;
                            $monitoreo -> accion =  "Actualizacion de status a habilitado del producto";
                            $monitoreo -> folioNuevo =  $idProducto;
                            $monitoreo -> pc =  $ip;
                            $monitoreo ->save();

                            //generamos respuesta del movimiento que se hizo
                            $data = array(
                                'code'      => 200,
                                'status'    => 'success',
                                'message'   =>  'Producto con id: '.$idProducto.' actualizado a idStatus: 31'
                            );
                        break;
                    default:
                        //Si recibimos otra cosa generamos mensaje de error
                        $data = array(
                            'code'      => 400,
                            'status'    => 'error',
                            'message'   =>  'Opcion no valida'
                        );
                    break;
                }

                DB::commit();
            } catch(\Exception $e){
                DB::rollBack();
                    $data = array(
                        'code'      => 400,
                        'status'    => 'Error',
                        'message'   =>  $e->getMessage(),
                        'error' => $e
                    );
            }
        } else{
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
        // dd($params_array['sucursales'][0]['nombre']);
        if(!empty($params_array)){

            //limpiamos los datos
            $params_array['producto'] = array_map('trim', $params_array['producto']);
            //validamos los datos que llegaron
            $validate = Validator::make($params_array['producto'], [
                'idMarca'           =>  'required',
                'idDep'             =>  'required',
                'claveEx'           =>  'required',
                'descripcion'       =>  'required',
                'stockMin'          =>  'required',
                'stockMax'          =>  'required',
                'tEntrega'          =>  'required',
            ]);
            //si falla creamos la respuesta a enviar
            if($validate->fails()){
                $data = array(
                    'status'    =>  'error',
                    'code'      =>  '404',
                    'message_system'   =>  'Fallo la validacion de los datos del producto',
                    'errors'    =>  $validate->errors()
                );
            }else{
                try{
                    DB::beginTransaction();
                    DB::enableQueryLog();
                    
                    //actualizamos
                    $producto = Producto::where('idProducto',$idProducto)->update([
                                    'idMarca' => $params_array['producto']['idMarca'],
                                    'idDep' => $params_array['producto']['idDep'],
                                    'idCat' => $params_array['producto']['idCat'],
                                    'claveEx' => $params_array['producto']['claveEx'],
                                    'cbarras' => $params_array['producto']['cbarras'],
                                    'descripcion' => $params_array['producto']['descripcion'],
                                    'stockMin' => $params_array['producto']['stockMin'],
                                    'stockMax' => $params_array['producto']['stockMax'],
                                    'imagen' => $params_array['producto']['imagen'],
                                    'ubicacion' => $params_array['producto']['ubicacion'],
                                    'claveSat' => $params_array['producto']['claveSat'],
                                    'tEntrega' => $params_array['producto']['tEntrega'],
                                    'idAlmacen' => $params_array['producto']['idAlmacen'],
                                ]);
                    
                    $producto = Producto::with('marca','departamento','categoria','status','almacen')
                                        ->find($idProducto);
                    //agregamos actualizacion a historial
                    Historial_producto::insertHistorial_producto($producto, $params_array['idEmpleado']); 

                    //obtenemos direccion ip
                    $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);

                    //insertamos el movimiento realizado en general del producto modificado
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $params_array['idEmpleado'];
                    $monitoreo -> accion =  "Modificacion de producto";
                    $monitoreo -> folioNuevo =  $params_array['producto']['idProducto'];
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                    $dataPrecios = $this->updatePrecioProducto($params_array['idEmpleado'],$producto->idProducto,$params_array['lista_productosMedida']);

                    $data_ProductosmultisucursalExt = [];
                    if($params_array['update_local'] == false){
                        $data_ProductosmultisucursalExt = $this->updateProductoMultiSuc($producto, $params_array['idEmpleado'],$params_array['sucursales'],$params_array['lista_productosMedida']);
                    }

                    //generamos respuesta
                    $data = array(
                        'status'    =>  'success',
                        'code'      =>  '200',
                        'message'   =>  'El producto se a guardado correctamente',
                        'producto'  =>  $producto,
                        'data_precios_local'  =>  $dataPrecios,
                        'data_productosMulti' => $data_ProductosmultisucursalExt,
                    );

                    /******GUARDACONSULTA */
                    $file = fopen('queries.txt', 'a');
                    fwrite($file, "--MODIFICACION DE PRODUCTO" . ";\n");
                    $queries = \DB::getQueryLog();
                    foreach($queries as $query) {
                        $bindings = $query['bindings'];
                        $sql = $query['query'];
                        foreach($bindings as $binding) {
                            $value = is_numeric($binding) ? $binding : "'".$binding."'";
                            $sql = preg_replace('/\?/', $value, $sql, 1);
                        }
                        fwrite($file, $sql . ";\n");
                    }
                    fclose($file);
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

    public function updateProductoMultiSuc($producto,$idEmpleado,$connections,$lista_precios_medida){
        $arr_ProductoMultisucursal_ok = [];
        $arr_ProductoMultisucursal_error = [];
        
        for($i=0; $i<count($connections); $i++){
            if($connections[$i]['isSelected']){
                try{
                    DB::connection($connections[$i]['connection'])->beginTransaction();
                    DB::connection($connections[$i]['connection'])->table('producto')
                            ->where('idProducto','=', $producto->idProducto)
                            ->update([
                                'idMarca'=> $producto->idMarca,
                                'idDep'=> $producto->idDep,
                                'idCat'=> $producto->idCat,
                                'claveEx'=> $producto->claveEx,
                                'cbarras'=> $producto->cbarras,
                                'descripcion'=> $producto->descripcion,
                                'stockMin'=> $producto->stockMin,
                                'stockMax'=> $producto->stockMax,
                                'ubicacion'=> $producto->ubicacion,
                                'claveSat'=> $producto->claveSat,
                                'tEntrega'=> $producto->tEntrega,
                                'idAlmacen'=> $producto->idAlmacen,
                                'updated_at'=> $producto->updated_at,
                                ]);
    
                        //Registramos historial
                        DB::connection($connections[$i]['connection'])->table('historial_producto')->insert([
                            'idProducto' => $producto->idProducto,
                            'idMarca' => $producto->idMarca,
                            'nombreMarca' => $producto->marca->nombre,
                            'idDep' => $producto->idDep,
                            'nombreDep' => $producto->departamento->nombre,
                            'idCat' => $producto->idCat,
                            'nombreCat' => $producto->categoria->nombre,
                            'claveEx' => $producto->claveEx,
                            'cbarras' => $producto->cbarras,
                            'descripcion' => $producto->descripcion,
                            'stockMin' => $producto->stockMin,
                            'stockMax' => $producto->stockMax,
                            'imagen' => $producto->imagen,
                            'idStatus' => $producto->statuss,
                            'nombreStatus' => $producto->status->nombre,
                            'ubicacion' => $producto->ubicacion,
                            'claveSat' => $producto->claveSat,
                            'tEntrega' => $producto->tEntrega,
                            'idAlmacen' => $producto->idAlmacen,
                            'nombreAlmacen' => $producto->almacen->nombre,
                            'existenciaG' => $producto->existenciaG,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                                
                        //obtenemos direccion ip
                        $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);
    
                        //insertamos el movimiento realizado en general del producto modificado
                        $monitoreo = new Monitoreo();
                        $monitoreo -> idUsuario =  $idEmpleado;
                        $monitoreo -> accion =  "Modificacion de producto";
                        $monitoreo -> folioNuevo =  $producto->idProducto;
                        $monitoreo -> pc =  $ip;
                        $monitoreo ->save();

                        $data_precioMultiSuc = $this->updatePrecioProductoMultiSuc($idEmpleado,$producto->idProducto,$lista_precios_medida,$connections[$i]);
                        //generamos respuesta
                        $data = array(
                            'status'    =>  'success',
                            'code'      =>  200,
                            'message'   =>  'El producto se a guardado correctamente',
                            'producto'  =>  $producto,
                            'data_preciosMultiSucursal' => $data_precioMultiSuc,
                            'nombre_sucursal' => $connections[$i]['nombre'],
                            'isSelected' => true,
                        );
    
                        $arr_ProductoMultisucursal_ok [] = $data;
        
                        DB::connection($connections[$i]['connection'])->commit();
                }catch (\Exception $e){
                    DB::connection($connections[$i]['connection'])->rollback();
                        $data =  array(
                            'code'    => 400,
                            'status'  => 'error',
                            'message' => 'Fallo al registrar el producto en la sucursal '.$connections[$i]['nombre'],
                            'error'   => $e,
                            'nombre_sucursal' => $connections[$i]['nombre'],
                        );
    
                        $arr_ProductoMultisucursal_error [] = $data;
                }
            } else{
                $data =  array(
                    'code'    => 200,
                    'status'  => 'ok',
                    'message' => 'Actualizacion no seleccionada en la sucursal '.$connections[$i]['nombre'],
                    'nombre_sucursal' => $connections[$i]['nombre'],
                    'isSelected' => false
                );

                $arr_ProductoMultisucursal_ok [] = $data;
            }
        }
        return ['sucursales_guardadas' => $arr_ProductoMultisucursal_ok, 'sucursales_fallidas' => $arr_ProductoMultisucursal_error];
    }

    /**
     * Actualiza las medidas por producto
     *
     * Recibe los datos de las medidas a actualizar + datos empleado
     */
    public function updatePrecioProducto($idEmpleado,$idProducto, $lista_precios_medida){

        if(!empty($idEmpleado) && !empty($idProducto) && !empty($lista_precios_medida)){
            try{
                DB::beginTransaction();
                DB::enableQueryLog();

                //obtenemos direccion ip
                $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);

                //eliminamos los registros que tengan ese idProdcuto
                Productos_medidas::where('idProducto',$idProducto)->delete();

                //insertamos las nuevas medidas
                foreach($lista_precios_medida AS $param => $paramdata){
                    
                    $productos_medidas = new Productos_medidas();
                    $productos_medidas -> idProducto = $idProducto;
                    $productos_medidas -> idMedida = $paramdata['idMedida'];
                    $productos_medidas -> unidad = $paramdata['unidad'];
                    $productos_medidas -> precioCompra = $paramdata['preciocompra'] ?? $paramdata['precioCompra'];

                    $productos_medidas -> porcentaje1 = $paramdata['porcentaje1'];
                    $productos_medidas -> precio1 = $paramdata['precio1'];

                    $productos_medidas -> porcentaje2 = $paramdata['porcentaje2'];
                    $productos_medidas -> precio2 = $paramdata['precio2'];

                    $productos_medidas -> porcentaje3 = $paramdata['porcentaje3'];
                    $productos_medidas -> precio3 = $paramdata['precio3'];

                    $productos_medidas -> porcentaje4 = $paramdata['porcentaje4'];
                    $productos_medidas -> precio4 = $paramdata['precio4'];

                    $productos_medidas -> porcentaje5 = $paramdata['porcentaje5'];
                    $productos_medidas -> precio5 = $paramdata['precio5'];
                    $productos_medidas -> idStatus = 31;

                    $productos_medidas -> save();

                    //consulta la ultima medida ingresada
                    $ultimaMedida = Productos_medidas::latest('idProdMedida')->first()->idProdMedida;

                    //insertamos el movimiento realizado
                    $monitoreo = new Monitoreo();
                    $monitoreo -> idUsuario =  $idEmpleado;
                    $monitoreo -> accion =  "Alta de medida ".$ultimaMedida." para el producto";
                    $monitoreo -> folioNuevo =  $idProducto;
                    $monitoreo -> pc =  $ip;
                    $monitoreo ->save();

                }//fin foreach

                //Obtenemos la lista de precios actualizada
                $actListaPrecio = Productos_medidas::select('productos_medidas.*','medidas.nombre as nombreMedida')
                                    ->join('medidas', 'medidas.idMedida','=','productos_medidas.idMedida')
                                    ->where('idProducto','=',$idProducto)
                                    ->orderBy('productos_medidas.idProdMedida','asc')
                                    ->get()
                                    ->map(function ($obj) use($idEmpleado){
                                        $obj->idEmpleado = $idEmpleado;
                                        return $obj;
                                    });

                //insertamos en historial de precios
                $listaPrecioArray = $actListaPrecio->toArray();
                DB::table('historialproductos_medidas')->insert($listaPrecioArray);

                //insertamos el movimiento realizado en general del producto modificado
                $monitoreo = new Monitoreo();
                $monitoreo -> idUsuario =  $idEmpleado;
                $monitoreo -> accion =  "Actualizacion de precios del producto";
                $monitoreo -> folioNuevo =  $idProducto;
                $monitoreo -> pc =  $ip;
                $monitoreo ->save();
                    
                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Precios actualizados correctamente',
                    'productos_medidas' => $productos_medidas,
                    // 'precios_ext'=> $data_updatePreciosMulti,
                );

                /******GUARDACONSULTA */
                $file = fopen('queries.txt', 'a');
                fwrite($file, "--MODIFICACION DE PRECIOS PRODUCTO" . ";\n");
                $queries = \DB::getQueryLog();
                foreach($queries as $query) {
                    $bindings = $query['bindings'];
                    $sql = $query['query'];
                    foreach($bindings as $binding) {
                        $value = is_numeric($binding) ? $binding : "'".$binding."'";
                        $sql = preg_replace('/\?/', $value, $sql, 1);
                    }
                    fwrite($file, $sql . ";\n");
                }
                fclose($file);
                /****** */
                DB::commit();
            } catch(\Exception $e){
                DB::rollback();
                $data = array(
                    'code' => 400,
                    'status' => 'error',
                    'message_system' => 'Algo salio mal al guardar los precios.',
                    'messsage' => $e->getMessage(),
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
        return $data;
    }

    public function updatePrecioProductoMultiSuc($idEmpleado,$idProducto,$lista_precios_medida,$connection){
            try{
                DB::connection($connection['connection'])->beginTransaction();
                //obtenemos direccion ip
                $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);

                //Eliminamos medidas a actualizar
                DB::connection($connection['connection'])->table('productos_medidas')->where('idProducto',$idProducto)->delete();
                //Insertamos nuevas medidas
                foreach($lista_precios_medida as $param => $paramdata){
                    //insermedida
                    DB::connection($connection['connection'])->table('productos_medidas')->insert([
                        'idProducto'=> $idProducto,
                        'idMedida'=> $paramdata['idMedida'],
                        'unidad'=> $paramdata['unidad'],
                        'precioCompra'=> $paramdata['preciocompra'],

                        'porcentaje1'=> $paramdata['porcentaje1'],
                        'precio1'=> $paramdata['precio1'],

                        'porcentaje2'=> $paramdata['porcentaje2'],
                        'precio2'=> $paramdata['precio2'],

                        'porcentaje3'=> $paramdata['porcentaje3'],
                        'precio3'=> $paramdata['precio3'],

                        'porcentaje4'=> $paramdata['porcentaje4'],
                        'precio4'=> $paramdata['precio4'],

                        'porcentaje5'=> $paramdata['porcentaje5'],
                        'precio5'=> $paramdata['precio5'],

                        'idStatus' => 31,

                        'created_at'=> Carbon::now(),
                        'updated_at'=> Carbon::now(),
                    ]);
                    //agregamos monitoreo
                    $ultimaMedida = DB::connection($connection['connection'])
                                        ->table('productos_medidas')
                                            ->latest()->first()->idProdMedida;

                    DB::connection($connection['connection'])->table('monitoreo')->insert([
                        'idUsuario'=> $idEmpleado,
                        'accion'=> "Alta de medida ".$ultimaMedida." para el producto",
                        'folioNuevo'=> $idProducto,
                        'pc'=> $ip,
                        'created_at'=> Carbon::now(),
                        'updated_at'=> Carbon::now(),
                    ]);
                }

                //Obtenemos la lista de precios actualizada
                $actListaPrecio = DB::connection($connection['connection'])
                                    ->table('productos_medidas')
                                    ->select('productos_medidas.*','medidas.nombre as nombreMedida')
                                    ->join('medidas', 'medidas.idMedida','=','productos_medidas.idMedida')
                                    ->where('idProducto','=',$idProducto)
                                    ->orderBy('productos_medidas.idProdMedida','asc')
                                    ->get()
                                    ->map(function ($obj) use($idEmpleado){
                                        $obj->idEmpleado = $idEmpleado;
                                        return $obj;
                                    });
                
                $historialData = [];

                foreach ($actListaPrecio as $item) {
                    $historialData[] = (array) $item; // Convertir cada objeto stdClass a un array asociativo
                }

                DB::connection($connection['connection'])->table('historialproductos_medidas')->insert($historialData);

                DB::connection($connection['connection'])->table('monitoreo')->insert([
                    'idUsuario'=> $idEmpleado,
                    'accion'=> "Actualizacion de precios del producto",
                    'folioNuevo'=> $idProducto,
                    'pc'=> $ip,
                    'created_at'=> Carbon::now(),
                    'updated_at'=> Carbon::now(),
                ]);

                $data = array(
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Precios actualizados correctamente ext',
                );
                DB::connection($connection['connection'])->commit();
            }catch (\Exception $e){
                DB::connection($connection['connection'])->rollback();
                    $data = array(
                        'code' => 400,
                        'status' => 'error',
                        'message' => 'Fallo al registrar los precios en la sucursal'.$connection['nombre'],
                        'error' => $e
                    );
            }
        return $data;
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
        ->where([
            ['idStatus','=','31'],
            ['idProducto','=',$idProducto]
        ])
        ->orderBy('productos_medidas.idProdMedida','asc')
        ->get();

        /***************************************** */
        $medidaMenor= 1;
        $lugar = 0;
        $existencia_por_med = array();
        $existencia_por_med2 = array();
        foreach($producto as $p){
            $existencia = $p->existenciaG;
        }
        //Consulta para saber cuantas medidas tiene un producto
        $count = Productos_medidas::where([
            ['productos_medidas.idProducto','=',$idProducto],
            ['productos_medidas.idStatus','=','31']
            ])->count();

        //Si el producto contiene una sola medida se asigna direcamente la existencia
        if($count == 1){
            foreach ($productos_medidas as $producto_medida) {
                $existencia_por_med['nombreMedida'] = $producto_medida->nombreMedida;
                if($this->cuentaDecimales($existencia) > 5){
                    //delimitamos los decimales a mostrar a solo 5
                    $existencia_por_med['exisCal'] = number_format($existencia, 5);
                }  else {
                    $existencia_por_med['exisCal'] = $existencia;
                }
            }
            $existencia_por_med2[$lugar] = $existencia_por_med;
            //sino
        } else{
            //obtenemos la medida menor multiplicando todas las unidades de las medidas
            foreach ($productos_medidas as $producto_medida) {
                $medidaMenor = $producto_medida->unidad * $medidaMenor;
            }

            //creamos ciclo
            while($lugar < $count){

                /**
                 * En este if verificamos si es la ultima vuelta para asignar decimales
                 * a la existencia ya que si no solo tomamos el entero
                 *
                 * calculamos el total de existencia de acuerdo medida dividiendo entre la existencia y la medida menor
                 *
                 * En el segundo if se llama la funcion cuentaDecimales()
                 * El cual nos regresa el numero de decimales si este es mayor a 5
                 * limitamos los decimales a 5 si no es mayor dejalos los deciamles que tenga
                 * o no tenga
                 */
                if($lugar+1 == $count){
                    $calculaE = $existencia / $medidaMenor;

                    if($this->cuentaDecimales($calculaE) > 5){
                        //delimitamos los decimales a mostrar a solo 5
                        $calculaE = number_format($calculaE, 5);
                    }

                } else {
                    $calculaE = intval($existencia / $medidaMenor);

                }
                //asignamos al array el nomnre de la medida y su existencia
                $existencia_por_med['nombreMedida'] = $productos_medidas[$lugar]->nombreMedida;
                $existencia_por_med['exisCal'] = $calculaE;

                $existencia_por_med2[$lugar] = $existencia_por_med;
                /**
                 * El residuo ahora lo tomamos como la existencia
                 * NOTA: Aqui no ocupamos el % ya que redondea decimales
                 */
                $existencia = fmod($existencia, $medidaMenor);

                //verificamos si contiene mas medidas para dividirlos entre la unidad
                if($lugar+1 < $count){
                    $medidaMenor = $medidaMenor / $productos_medidas[$lugar+1]->unidad;
                } else{
                    $medidaMenor = $medidaMenor / $productos_medidas[$lugar]->unidad;
                }

                $lugar++;
            }

        }

        /***************************************** */

        if(is_object($producto)){
            $data = [
                'code'          => 200,
                'status'        => 'success',
                'producto'   =>  $producto,
                'productos_medidas'   =>  $productos_medidas,
                'existencia_por_med' => $existencia_por_med2
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

    /**
     * Busca el producto por claveEx y retorna solamente su idProducto
     * 
     */
    public function getIdProductByClaveEx($claveExterna){
        //Proximo a modificar
        $status = 31;
        $idProducto = Producto::where('claveEx',$claveExterna)
                            ->where('statuss',$status)
                            ->value('idProducto');                  
        
        if($idProducto){
            // $data_producto = $this->showTwo($idProducto);
            $data = array(
                'code'=> 200,
                'status'=> 'success',
                // 'data'=> $data_producto->getData(),
                'idProducto' => $idProducto,
            );
        } else{
            $data = array(
                'code'=> 400,
                'status'=> 'error',
                'message'=> 'Producto no encontrado'
            );
        }

        return response()->json($data, $data['code']);
    }
    ///////////////////////////////////////////////////////

    /**
     * Trae la existencia del producto
     */
    public function getExistenciaG($idProducto, $idProdMedida, $cantidad){

        //obtenemos existencia del producto
        $existencia = Producto::where('idProducto', '=', $idProducto)->pluck('existenciaG')->first();

        $listaPM = Productos_medidas::where('idProducto','=',$idProducto)->select('idProdMedida','unidad')->get();

        $count = count($listaPM);

        $igualMedidaMenor = 0;
        $lugar = 0;

        /**INICIAMOS CONVERSION */
        //verificamos si el producto tiene una medida
        if($count == 1){
            $igualMedidaMenor = $cantidad;
        } else{ //Desoues de dos medidas buscamos la posicion de la meida en la que se ingreso
            //Recorremos la lista de  productos medidas (listaPM)
            while( $idProdMedida != $listaPM[$lugar]['attributes']['idProdMedida'] ){
                $lugar++;
            }
            if($lugar == $count-1){ //Si la medida a buscar es la mas baja se deja igual
                $igualMedidaMenor = $cantidad;

            } elseif($lugar == 0){//Medida mas alta
                $igualMedidaMenor = $cantidad;
                while($lugar < $count){
                    $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar]['attributes']['unidad'];
                    $lugar++;
                }
            } elseif($lugar > 0 && $lugar < $count-1){//medida intermedias
                $igualMedidaMenor = $cantidad;
                $count--;
                while($lugar < $count){
                    $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]['attributes']['unidad'];
                    $lugar++;
                }
            }
        }
        /*****FIN CONVECION */

        //Ahora verificamos que la cantidad solicita esta disponible
        if($existencia > $igualMedidaMenor){
            $disponible = true;
        } else{
            $disponible = false;
        }


        //obtenemos las medidas
        return response()->json([
            'code'              =>  200,
            'status'            => 'success',
            'existencia'        =>  $existencia,
            'igualMedidaMenor'  => $igualMedidaMenor,
            'disponibilidad'    => $disponible
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
     * Busca las medidas del producto habilitado
     * Busca la imagen del prducto
     */
    public function searchProductoMedida($idProducto){
        try{
            //consultamos las propiedades del producto
            $producto = Producto::find($idProducto);
            //asignamos existencia a variable
            $existencia = $producto->existenciaG;
            //iniciamos variables
            $medidaMenor= 1;
            $lugar = 0;
            $existencia_por_med = array();
            $existencia_por_med2 = array();


            //traemos la informacion de las medidas
            $productos_medidas = DB::table('productos_medidas')
                ->join('medidas', 'medidas.idMedida','=','productos_medidas.idMedida')
                ->select('productos_medidas.*','medidas.nombre as nombreMedida','productos_medidas.precioCompra as preciocompra')
                ->where([
                     ['idStatus','=','31'],
                    ['idProducto','=',$idProducto]
                ])
                ->orderBy('productos_medidas.idProdMedida','asc')
                ->get();

            //Consulta para saber cuantas medidas tiene un producto
            $count = count($productos_medidas);
            //Si el producto contiene una sola medida se asigna direcamente la existencia
            if($count == 1){
                foreach ($productos_medidas as $producto_medida) {
                    $existencia_por_med['nombreMedida'] = $producto_medida->nombreMedida;
                    if($this->cuentaDecimales($existencia) > 5){
                            //delimitamos los decimales a mostrar a solo 5
                            $existencia_por_med['exisCal'] = number_format($existencia, 5);
                    } else {
                        $existencia_por_med['exisCal'] = $existencia;
                    }
                }
                $existencia_por_med2[$lugar] = $existencia_por_med;
                //sino
            } else{
                //obtenemos la medida menor multiplicando todas las unidades de las medidas
                foreach ($productos_medidas as $producto_medida) {
                    $medidaMenor = $producto_medida->unidad * $medidaMenor;
                }

                //creamos ciclo
                while($lugar < $count){

                    /**
                     * En este if verificamos si es la ultima vuelta para asignar decimales
                     * a la existencia ya que si no solo tomamos el entero
                     *
                     * calculamos el total de existencia de acuerdo medida dividiendo entre la existencia y la medida menor
                     *
                     * En el segundo if se llama la funcion cuentaDecimales()
                     * El cual nos regresa el numero de decimales si este es mayor a 5
                     * limitamos los decimales a 5 si no es mayor dejalos los deciamles que tenga
                     * o no tenga
                     */
                    if($lugar+1 == $count){
                        $calculaE = $existencia / $medidaMenor;

                        if($this->cuentaDecimales($calculaE) > 5){
                            //delimitamos los decimales a mostrar a solo 5
                            $calculaE = number_format($calculaE, 5);
                        }

                    } else {
                        $calculaE = intval($existencia / $medidaMenor);

                    }
                    //asignamos al array el nomnre de la medida y su existencia
                    $existencia_por_med['nombreMedida'] = $productos_medidas[$lugar]->nombreMedida;
                    $existencia_por_med['exisCal'] = $calculaE;

                    $existencia_por_med2[$lugar] = $existencia_por_med;
                    /**
                     * El residuo ahora lo tomamos como la existencia
                     * NOTA: Aqui no ocupamos el % ya que redondea decimales
                     */
                    $existencia = fmod($existencia, $medidaMenor);

                    //verificamos si contiene mas medidas para dividirlos entre la unidad
                    if($lugar+1 < $count){
                        $medidaMenor = $medidaMenor / $productos_medidas[$lugar+1]->unidad;
                    } else{
                        $medidaMenor = $medidaMenor / $productos_medidas[$lugar]->unidad;
                    }

                    $lugar++;
                }

            }

            // Inicializamos los arrays para almacenar los nombres de las medidas y los precios
            // $medidas = [];
            // $precios = [];

            // // Recorremos los resultados y construimos los arrays
            // foreach ($productos_medidas as $medida) {
            //     // Agregamos el nombre de la medida al array de medidas
            //     $medidas[] = $medida->nombreMedida;
                
            //     // Agregamos los precios al array de precios correspondiente
            //     $precios['precio1'][] = $medida->precio1;
            //     $precios['precio2'][] = $medida->precio2;
            //     $precios['precio3'][] = $medida->precio3;
            //     $precios['precio4'][] = $medida->precio4;
            //     $precios['precio5'][] = $medida->precio5;
            // }
            
            $imagen = Producto::findOrFail($idProducto)->imagen;
            $data = [
                'code'          =>  200,
                'status'        => 'success',
                'count'         => $count,
                'Producto_cl'   => $producto->claveEx,
                'productoMedida'   =>  $productos_medidas,
                'existencia_por_med' => $existencia_por_med2,
                'imagen'        => $imagen,
                // 'head_nombre_medidas' => $medidas,
                // 'body_precios' => $precios,
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

    /**
     * Busca las medidas del producto deshabilitado
     * Busca la imagen del prducto
     */
    public function searchProductoMedidaI($idProducto){
        try{
            //consultamos las propiedades del producto
            $producto = Producto::find($idProducto);
            //asignamos existencia a variable
            $existencia = $producto->existenciaG;
            //iniciamos variables
            $medidaMenor= 1;
            $lugar = 0;
            $existencia_por_med = array();
            $existencia_por_med2 = array();
            //Consulta para saber cuantas medidas tiene un producto
            $count = Productos_medidas::where([
                ['productos_medidas.idProducto','=',$idProducto],
                ['productos_medidas.idStatus','=',31]
                ])->count();

            //traemos la informacion de las medidas
            $productos_medidas = DB::table('productos_medidas')
                                ->join('medidas','medidas.idMedida','=','productos_medidas.idMedida')
                                ->select('productos_medidas.*','medidas.nombre as nombreMedida')
                                ->where([
                                    ['idStatus','=',31],
                                    ['idProducto','=',$idProducto]
                                ])
                                ->get();
            //Si el producto contiene una sola medida se asigna direcamente la existencia
            if($count == 1){
                foreach ($productos_medidas as $producto_medida) {
                    $existencia_por_med['nombreMedida'] = $producto_medida->nombreMedida;
                    if($this->cuentaDecimales($existencia) > 5){
                        //delimitamos los decimales a mostrar a solo 5
                        $existencia_por_med['exisCal'] = number_format($existencia, 5);
                    } else {
                        $existencia_por_med['exisCal'] = $existencia;
                    }
                }
                $existencia_por_med2[$lugar] = $existencia_por_med;
                //sino
            } else{
                //obtenemos la medida menor multiplicando todas las unidades de las medidas
                foreach ($productos_medidas as $producto_medida) {
                    $medidaMenor = $producto_medida->unidad * $medidaMenor;
                }
                //creamos ciclo
                while($lugar < $count){

                    /**
                     * En este if verificamos si es la ultima vuelta para asignar decimales
                     * a la existencia ya que si no solo tomamos el entero
                     *
                     * calculamos el total de existencia de acuerdo medida dividiendo entre la existencia y la medida menor
                     *
                     * En el segundo if se llama la funcion cuentaDecimales()
                     * El cual nos regresa el numero de decimales si este es mayor a 5
                     * limitamos los decimales a 5 si no es mayor dejalos los deciamles que tenga
                     * o no tenga
                     */
                    if($lugar+1 == $count){
                        $calculaE = $existencia / $medidaMenor;

                        if($this->cuentaDecimales($calculaE) > 5){
                            //delimitamos los decimales a mostrar a solo 5
                            $calculaE = number_format($calculaE, 5);
                        }

                    } else {
                        $calculaE = intval($existencia / $medidaMenor);

                    }
                    //asignamos al array el nomnre de la medida y su existencia
                    $existencia_por_med['nombreMedida'] = $productos_medidas[$lugar]->nombreMedida;
                    $existencia_por_med['exisCal'] = $calculaE;

                    $existencia_por_med2[$lugar] = $existencia_por_med;
                    /**
                     * El residuo ahora lo tomamos como la existencia
                     * NOTA: Aqui no ocupamos el % ya que redondea decimales
                     */
                    $existencia = fmod($existencia, $medidaMenor);

                    //verificamos si contiene mas medidas para dividirlos entre la unidad
                    if($lugar+1 < $count){
                        $medidaMenor = $medidaMenor / $productos_medidas[$lugar+1]->unidad;
                    } else{
                        $medidaMenor = $medidaMenor / $productos_medidas[$lugar]->unidad;
                    }

                    $lugar++;
                }

            }

            $imagen = Producto::findOrFail($idProducto)->imagen;
            $data = [
                'code'          =>  200,
                'status'        => 'success',
                'Producto_cl'   => $producto->claveEx,
                'productoMedida'   =>  $productos_medidas,
                'existencia_por_med' => $existencia_por_med2,
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

    public function existencia($idProducto){
        //consultamos las propiedades del producto
        $producto = Producto::find($idProducto);
        //asignamos existencia a variable
        $existencia = $producto->existenciaG;
        //iniciamos variables
        $medidaMenor= 1;
        $lugar = 0;
        $existencia_por_med = array();
        $existencia_por_med2 = array();

        //Consulta para saber cuantas medidas tiene un producto
        $count = Productos_medidas::where([
            ['productos_medidas.idProducto','=',$idProducto],
            ['productos_medidas.idStatus','=','31']
            ])->count();

        //traemos la informacion de las medidas
        $productos_medidas = DB::table('productos_medidas')
            ->join('medidas', 'medidas.idMedida','=','productos_medidas.idMedida')
            ->select('productos_medidas.*','medidas.nombre as nombreMedida','productos_medidas.precioCompra as preciocompra')
            ->where([
                ['idStatus','=','31'],
                ['idProducto','=',$idProducto]
            ])
            ->orderBy('productos_medidas.idProdMedida','asc')
            ->get();

        //Si el producto contiene una sola medida se asigna direcamente la existencia
        if($count == 1){
            foreach ($productos_medidas as $producto_medida) {
                $existencia_por_med['nombreMedida'] = $producto_medida->nombreMedida;
                $existencia_por_med['exisCal'] = $existencia;
            }
            $existencia_por_med2[$lugar] = $existencia_por_med;
            //sino
        } else{
            //obtenemos la medida menor multiplicando todas las unidades de las medidas
            foreach ($productos_medidas as $producto_medida) {
                $medidaMenor = $producto_medida->unidad * $medidaMenor;
            }

            //creamos ciclo
            while($lugar < $count){

               //calculamos el total de existencia de acuerdo medida dividiendo entre la existencia y la medida menor
                    //y asignamos solo el valor entero, tampoco se redondea
                    $calculaE = intval($existencia / $medidaMenor);
                    //asignamos al array el nomnre de la medida y su existencia
                    $existencia_por_med['nombreMedida'] = $productos_medidas[$lugar]->nombreMedida;
                    $existencia_por_med['exisCal'] = $calculaE;

                    $existencia_por_med2[$lugar] = $existencia_por_med;
                    //reasignamos la existencia el residuo
                    $existencia = $existencia % $medidaMenor;

                    //verificamos si contiene mas medidas para dividirlos entre la unidad
                    if($lugar+1 < $count){
                        $medidaMenor = $medidaMenor / $productos_medidas[$lugar+1]->unidad;
                    } else{
                        $medidaMenor = $medidaMenor / $productos_medidas[$lugar]->unidad;
                    }

                    $lugar++;
            }


            //$existencia_por_med = array("nombre_medida" => 0);
        }



        //->get();
        return response()->json([
            'code'          =>  200,
            'status'        => 'success',
            //'count'        => $count,
            'Producto_cl'   => $producto->claveEx,
            //'medidas' => $productos_medidas,
            'existencia_por_med' => $existencia_por_med
        ]);
    }

    /**
     * Nos muestra todas las existencias de las sucursales disponibles
     * Se omite la coneccion hacia hostinger
     */
    public function getExistenciaMultiSucursal($idProducto){

        $empresa = Empresa::first();

        $sucursal_con = DB::table('sucursal')
                            ->whereNotNull('connection')
                            ->where('idSuc','<>', $empresa->idSuc)
                            ->where('connection','<>', 'hostinger')//Revisar como hacerlo variable
                            ->get();

        $arrExistencias = [];
        for($i = 0; $i < count($sucursal_con); $i++){
            try{
                $e = DB::connection($sucursal_con[$i]->connection)
                            ->table('producto')
                            ->where('idProducto','=', $idProducto)
                            ->first();
                $arrExistencias[$sucursal_con[$i]->connection] = $this->searchProductoMedida($e->idProducto);

            } catch(\Exception $e){
                $data =  array(
                        'code'    => 400,
                        'status'  => 'error',
                        'message' => 'Fallo al obtener la informacion en la sucursal '.$sucursal_con[$i]->connection,
                        'error'   => $e
                    );
                    // break;
            }
        }
        
        $data = array(
            'code'=> 200,
            'status' => 'success',
            'existencias' => $arrExistencias,
            'sucursales' => $sucursal_con
        );

        return response()->json($data);
    }

    /**
     * Esta funcion permite consultar el producto registrado en nuestra nube (hostinger)
     * Retornamos el resultado del producto y producto_medidas
     * Actualmente se utiliza para buscar el producto en la nube y luego actualizar con esta informacion
     */
    public function getProductoNUBE($idProducto){
        $sucursal = Sucursal::where([
                        ['nombre','=','NUBE'],
                        ['connection','=','hostinger']
                    ])
                    ->first();

        if(!empty($sucursal)){
            try{
                $producto = DB::connection($sucursal->connection)
                                ->table('producto')
                                ->join('marca','marca.idMarca','producto.idMarca')
                                ->join('departamentos','departamentos.idDep','producto.idDep')
                                // ->join('categoria','categoria.idCat','producto.idCat')
                                // ->join('statuss','statuss.idStatus','producto.statuss')
                                // ->join('almacenes','almacenes.idAlmacen','producto.idAlmacen')
                                ->where('idProducto','=', $idProducto)
                                // ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep','categoria.nombre as nombreCat','statuss.nombre as nombreStatus','almacenes.nombre as nombreAlmacen')
                                ->select('producto.*','marca.nombre as nombreMarca','departamentos.nombre as nombreDep')
                                ->first();
                $producto_medidas = DB::connection($sucursal->connection)
                                ->table('productos_medidas')
                                ->join('medidas','medidas.idMedida','productos_medidas.idMedida')
                                ->where('idProducto','=', $idProducto)
                                ->select('productos_medidas.*','medidas.nombre as nombreMedida')
                                ->get();
                $data = array(
                    'code' => 200,
                    'status'=> 'success',   
                    'producto'=> $producto,
                    'producto_medidas' => $producto_medidas,
                );
            } catch(\Exception $e){
                $data =  array(
                    'code'    => 400,
                    'status'  => 'error',
                    'message' => 'Fallo al obtener la informacion en la sucursal ',
                    'error'   => $e
                );
            }
        } else{
            $data = array(
                'code'=> 400,
                'status'=> 'error',
                'message'=> 'No se encontro el catalogo'
            );
        }
        return response()->json($data);
    }


    public function getHistorialProducto($idProducto){
        if($idProducto){
            $historial_producto = Historial_producto::join('empleado','empleado.idEmpleado','historial_producto.idEmpleado')
                                    ->select('historial_producto.*',
                                                DB::raw("CONCAT(empleado.nombre,' ',empleado.aPaterno,' ',empleado.aMaterno) as nombreEmpleado"))
                                    ->where('idProducto',$idProducto)
                                    ->orderBy('idHistorialProducto','desc')
                                    ->get();

            $data = array(
                'code' => 200,
                'status' => 'success',
                'historial_producto' => $historial_producto
            );
            

        } else{
            $data = array(
                'code'=> 400,
                'status'=> 'error',
                'message'=> 'El valor recibido es incorrecto'
            );
        }
        return response()->json($data);
    }
    public function getHistorialProductoPrecio($idProducto){
        if($idProducto){
            $historial_producto = historialproductos_medidas::where('idProducto', $idProducto)
                                    ->orderBy('created_at', 'desc')
                                    ->get();

            // Inicializamos un array vacío para almacenar los resultados agrupados por fecha
            $historial_agrupado = [];

            // Iteramos sobre los resultados y organizamos en el nuevo array por fecha
            foreach ($historial_producto as $registro) {
                // Convertimos la fecha de creación a un formato legible
                $fecha_modificacion = date('Y-m-d H:i:s', strtotime($registro->created_at));

                // Si la fecha aún no está presente en el array agrupado, la inicializamos
                if (!isset($historial_agrupado[$fecha_modificacion])) {
                    $historial_agrupado[$fecha_modificacion] = [];
                }

                // Añadimos el registro actual al array agrupado bajo la fecha correspondiente
                $historial_agrupado[$fecha_modificacion][] = $registro;
            }

            $data = [
                'code' => 200,
                'status' => 'success',
                'historial_producto_precio' => $historial_agrupado
            ];
        } else {
            $data = [
                'code'=> 400,
                'status'=> 'error',
                'message'=> 'El valor recibido es incorrecto'
            ];
        }
        return response()->json($data);
    }


     /****Funcion extra */
    function cuentaDecimales($numero){
        //convertimos el numero a string
        $numeroString = strval($numero);
        //buscamos la posicion del "."
        $decimalPosi = strpos($numeroString, '.');

        if($decimalPosi === false){
            //Si el numero dado no cuenta con decimales retornamos cero
            return 0;
        }

        $numDecimales = strlen($numeroString) - $decimalPosi -1;
        return $numDecimales;
    }

}