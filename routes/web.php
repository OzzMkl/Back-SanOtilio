<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
//Cargamos las clases
use App\Http\Middleware\ApiAuthMiddleware;



/*Route::get('/', function () {
    return view('welcome');
});

Route::get('/prueba', function(){
    return '<h2>Texto desde una ruta</h2>';
});*/

//RUTAS API
/**
 * GET: Conseguir datos o recursos
 * POST: Guardar datos o recursos hacer logica desde formulario
 * PUT: Actualizar datos o recursos
 * DELETE: Eliminar datos o recurnos
 */
    /*PRUEBAs
    Route::get('/usuario/pruebas', 'UserController@pruebas');*/
    //Route::get('/proveedor/pruebas', 'ProveedoresController@pruebas');

    /****** RUTAS DE CONTROLADOR DE USUARIO ******/
    //elegimos el nombre de la direccion y luego el controlador con el metodo a llamar
    Route::post('/api/register', 'UserController@register');
    Route::post('/api/login', 'UserController@login');
    Route::put('/api/user/update', 'UserController@update');//metodo put especializado para actualizaciones
    Route::post('/api/user/upload', 'UserController@upload')->middleware(App\Http\Middleware\ApiAuthMiddleware::class);//asignamos metodo de autenticacion a traves de middleware
    Route::get('/api/user/avatar/{filename}', 'UserController@getImage');
    Route::get('/api/user/detail/{idEmpleado}', 'UserController@detail');
    Route::get('/api/permisos','UserController@indexPermisos');
    Route::get('/api/RolesBySubmodulo/{idSubModulo}','UserController@RolesBySubmodulo');
    Route::get('/api/PermissionsByRol/{idRol}/{idModulo}/{idSubModulo}','UserController@PermissionsByRol');
    
    /****** RUTAS DE CONTROLADOR DE PROVEEDORES ******/
    Route::get('/api/proveedor/ObtenerLista','ProveedoresController@ObtenerLista');
    Route::post('/api/proveedor/register','ProveedoresController@register');
    Route::get('/api/proveedor/index','ProveedoresController@index');//mostrar proveedores activos
    Route::get('/api/proveedor/proveedoresDes','ProveedoresController@proveedoresDes');//mostrar proveedores deshabilitados
    Route::get('/api/proveedor/{proveedor}','ProveedoresController@show');//sacar proveedor por id
    Route::get('/api/proveedor/provContactos/{proveedor}','ProveedoresController@provContactos');//obtener contactos a partir de idProveedor
    Route::get('/api/proveedor/getNCP/{proveedor}','ProveedoresController@getNCP');//obtener contactos a partir de idProveedor
    Route::get('/api/proveedor/searchNombreProveedor/{nombreProveedor}','ProveedoresController@searchNombreProveedor');//Buscar proveedores por NOMBRE - HABILITADOS
    Route::get('/api/proveedor/searchRFCProveedor/{rfc}','ProveedoresController@searchRFCProveedor');//Buscar proveedores por RFC - HABILITADOS
    Route::get('/api/proveedor/searchNombreProveedorI/{nombreProveedor}','ProveedoresController@searchNombreProveedorI');//Buscar proveedores por NOMBRE - HABILITADOS
    Route::get('/api/proveedor/searchRFCProveedorI/{rfc}','ProveedoresController@searchRFCProveedorI');//Buscar proveedores por RFC - HABILITADOS
    
    Route::put('/api/proveedor/updatestatus/{proveedor}', 'ProveedoresController@updatestatus');//actualizacion de Status del proveedor
    
    
    /*******bancos */
    Route::get('/api/banco/index','BancoController@index');//mostrar BANcos
    /*******PRODUCTOS */
    Route::get('/api/productos/index','ProductoController@index');//mostrar productos activos
    Route::get('/api/productos/indexPV','ProductoController@indexPV');//mostrar productos activos
    Route::get('/api/productos/productosDes','ProductoController@productoDes');//mostrar proveedores deshabilitados
    Route::post('/api/productos/uploadimage', 'ProductoController@uploadimage');
    Route::get('/api/productos/getImageProduc/{filname}', 'ProductoController@getImageProduc');
    Route::post('/api/productos/register','ProductoController@register');
    Route::post('/api/productos/registraPrecioProducto','ProductoController@registraPrecioProducto');
    Route::get('/api/productos/getlastproduct','ProductoController@getlastproduct');
    Route::get('/api/productos/{producto}','ProductoController@show');//sacar producto por id
    Route::get('/api/productos/showTwo/{producto}','ProductoController@showTwo');//sacar producto por id
    Route::put('/api/productos/updatestatus/{producto}', 'ProductoController@updateStatus');//actualizacion de Status del producto
    Route::put('/api/productos/updateProduct/{producto}', 'ProductoController@updateProduct');//actualizacion de los datos del producto
    Route::put('/api/productos/updatePrecioProducto/{idProducto}', 'ProductoController@updatePrecioProducto');//actualizacion de los datos del producto


    Route::get('/api/productos/existencia/{idProducto}', 'ProductoController@existencia');
    
    Route::get('/api/productos/searchclaveEx/{producto}', 'ProductoController@getProductClaveex');
    Route::get('/api/productos/getExistenciaG/{idProducto}/{idProdMedida}/{cantidad}', 'ProductoController@getExistenciaG');
    Route::get('/api/productos/searchClaveExterna/{claveex}', 'ProductoController@searchClaveEx');//buscar por clave externa productos con status 1
    Route::get('/api/productos/searchCodbar/{codbar}', 'ProductoController@searchCodbar');//buscar por codigo de barras productos con status 1
    Route::get('/api/productos/searchDescripcion/{descripcion}', 'ProductoController@searchDescripcion');//buscar por descripcion productos con status 1
    Route::get('/api/productos/searchClaveExInactivos/{claveex}', 'ProductoController@searchClaveExInactivos');//buscar por clave externa productos con status 2
    Route::get('/api/productos/searchCodbarI/{codbar}', 'ProductoController@searchCodbarI');//buscar por codigo de barras productos con status 2
    Route::get('/api/productos/searchDescripcionI/{descripcion}', 'ProductoController@searchDescripcionI');//buscar por descripcion productos con status 2

    Route::get('/api/productos/searchProductoMedida/{idProducto}', 'ProductoController@searchProductoMedida');//Busca las medidas de los productos con status 31
    Route::get('/api/productos/searchProductoMedidaI/{idProducto}', 'ProductoController@searchProductoMedidaI');//buscar las medidas de los productos con status 32
    /************DEPARTAMENTOS*/
    Route::get('/api/departamentos/index','DepartamentoController@index');//mostrar departamentos
    Route::get('/api/departamentos/longitud','DepartamentoController@getLongitud');//mostrar departamentos
    /************CATEGORIAS*/
    Route::get('/api/categoria/index','CategoriaController@index');//mostrar categorias
    Route::get('/api/categoria/getIdDepa/{value}','CategoriaController@getIdDepa');

    /************SUBCATEGORIAS*/
    Route::get('/api/subcategoria/index','SubCategoriaController@index');//mostrar subcategorias
    Route::get('/api/subcategoria/getIdSuca/{value}','SubCategoriaController@getIdSuca');
     /************MARCAS*/
    Route::get('/api/marca/index','MarcaController@index');//mostrar marcas
    /************MEDIDAS*/
    Route::get('/api/medidas/index','MedidaController@index');//mostrar medidas
    /************ALMACENES*/
    Route::get('/api/almacenes/index','AlmacenesController@index');//mostrar almacenes
    /************LOTE */
    Route::post('/api/lote/register','LoteController@register');//registro de lote
    Route::get('/api/lote/index','LoteController@index');// mostrar lotes
    /***********IMPUESTO */
    Route::get('/api/impuesto/index','ImpuestoController@index');
    Route::get('/api/impuesto/show/{idImpuesto}','ImpuestoController@show');//sacar impuesto por id
    /***********Requisicion */
    Route::post('/api/requisicion/register','RequisicionController@registerRequisicion');
    Route::post('/api/requisicion/registerLista','RequisicionController@registerProductosRequisicion');
    Route::get('/api/requisicion/updateidOrden','RequisicionController@updateidOrden');
    Route::get('/api/requisicion/index','RequisicionController@index');
    Route::get('/api/requisicion/getLastReq','RequisicionController@getLastReq');
    Route::get('/api/requisicion/showMejorado/{idReq}','RequisicionController@showMejorado');
    Route::get('/api/requisicion/generatePDF/{idOrd}/{idEmpleado}','RequisicionController@generatePDF');
    Route::get('/api/requisicion/listaRequisiciones','RequisicionController@listaRequisiciones');
    Route::get('/api/requisicion/generarOrden','RequisicionController@generarOrden');
    Route::put('/api/requisicion/updateRequisicion/{idReq}','RequisicionController@updateRequisicion');
    Route::put('/api/requisicion/updateProductosReq/{idReq}','RequisicionController@updateProductosReq');
    Route::put('/api/requisicion/deshabilitarReq/{idReq}/{idEmpleado}','RequisicionController@deshabilitarReq');
    



    /***********Orden de compra */
    Route::post('/api/ordendecompra/register','OrdendecompraController@registerOrdencompra');
    Route::post('/api/ordendecompra/registerLista','OrdendecompraController@registerProductosOrden');
    Route::get('/api/ordendecompra/getLastOrder','OrdendecompraController@getLastOrder');
    ///Route::get('/api/ordendecompra/show/{idOrd}','OrdendecompraController@show');
    Route::get('/api/ordendecompra/showMejorado/{idOrd}','OrdendecompraController@showMejorado');
    Route::get('/api/ordendecompra/index','OrdendecompraController@index');
    Route::get('/api/ordendecompra/generatePDF/{idReq}/{idEmpleado}','OrdendecompraController@generatePDF');
    Route::put('/api/ordendecompra/updateOrder/{idOrd}','OrdendecompraController@updateOrder');
    Route::put('/api/ordendecompra/updateProductsOrder/{idOrd}','OrdendecompraController@updateProductsOrder');
    /***********Compra */
    Route::post('/api/compra/register','CompraController@registerCompra');
    Route::post('/api/compra/registerLista','CompraController@registerProductosCompra');
    Route::post('/api/compra/registerLote','CompraController@registerLote');
    Route::post('/api/compra/updateExistencia','CompraController@updateExistencia');
    Route::post('/api/compra/updateExistenciaFacturable','CompraController@updateExistenciaFacturable');
    Route::post('/api/compra/updateCompra','CompraController@updateCompra');
    Route::post('/api/compra/updateProductosCompra','CompraController@updateProductosCompra');
    Route::post('/api/compra/alterExistencia','CompraController@alterExistencia');
    Route::get('/api/compra/getLastCompra','CompraController@getLastCompra');
    Route::get('/api/compra/showMejorado/{idCompra}','CompraController@showMejorado');
    Route::get('/api/compra/index','CompraController@index');
    Route::get('/api/compra/listaComprasRecibidas','CompraController@listaComprasRecibidas');
    Route::get('/api/compra/searchIdCompra/{idCompra}','CompraController@searchIdCompra');
    Route::get('/api/compra/searchNombreProveedor/{nombreProveedor}','CompraController@searchNombreProveedor');
    Route::get('/api/compra/searchFolioProveedor/{folioProveedor}','CompraController@searchFolioProveedor');
    Route::get('/api/compra/searchTotal/{total}','CompraController@searchTotal');
    Route::get('/api/compra/checkUpdates/{idCompra}','CompraController@checkUpdates');
    Route::get('/api/compra/generatePDF/{idCompra}/{idEmpleado}','CompraController@generatePDF');

    /****clientes */
    Route::get('/api/clientes/index','ClienteController@index');
    Route::get('/api/clientes/indexTipocliente','ClienteController@indexTipocliente');
    Route::post('/api/clientes/register','ClienteController@registerCliente');
    Route::post('/api/clientes/registerCdireccion','ClienteController@registerCdireccion');
    Route::post('/api/clientes/registrarNuevaDireccion','ClienteController@registrarNuevaDireccion');
    Route::get('/api/clientes/getDetallesCliente/{idCliente}','ClienteController@getDetallesCliente');
    Route::get('/api/clientes/getDireccionCliente/{idCliente}','ClienteController@getDireccionCliente');
    Route::put('/api/clientes/updateCliente/{idCliente}','ClienteController@updateCliente');
    Route::put('/api/clientes/updateCdireccion/{idCliente}','ClienteController@updateCdireccion');
    Route::get('/api/clientes/searchNombreCliente/{nombreCliente}','ClienteController@searchNombreCliente');
    
    /****VENTAS */
    Route::get('/api/ventas/indexTP','VentasController@indexTP');
    Route::get('/api/ventas/indexTipoVenta','VentasController@indexTipoVenta');
    Route::get('/api/ventas/indexVentas','VentasController@indexVentas');
    Route::get('/api/ventas/getDetallesVenta/{idVenta}','VentasController@getDetallesVenta');
    Route::post('/api/ventas/guardarVenta','VentasController@guardarVenta');
    Route::post('/api/ventas/guardarProductosVenta','VentasController@guardarProductosVenta');
    //Route::get('/api/ventas/generaTicket','VentasController@generaTicket');
    /*****cotizaciones*/
    Route::get('/api/cotizaciones/indexCotizaciones','cotizacionesController@indexCotiza');
    Route::post('/api/cotizaciones/guardarCotizacion','cotizacionesController@guardarCotizacion');
    Route::post('/api/cotizaciones/guardarProductosCotiza','cotizacionesController@guardarProductosCotiza');
    Route::get('/api/cotizaciones/consultaUltimaCotiza','cotizacionesController@consultaUltimaCotiza');
    Route::get('/api/cotizaciones/detallesCotizacion/{idCotiza}','cotizacionesController@detallesCotizacion');
    Route::put('/api/cotizaciones/actualizaCotizacion/{idCotiza}','cotizacionesController@actualizaCotizacion');
    Route::put('/api/cotizaciones/actualizaProductosCotizacion/{idCotiza}','cotizacionesController@actualizaProductosCotiza');
    
    Route::get('/api/cotizaciones/generatePDF/{idCotiza}','cotizacionesController@generatePDF');
    /*******EMPRESA */
    Route::get('/api/empresa/index','EmpresaController@index');
    /*******CAJAS */
    Route::post('/api/cajas/aperturaCaja','CajasController@aperturaCaja');
    Route::put('/api/cajas/cierreCaja','CajasController@cierreCaja');
    Route::get('/api/cajas/verificarCaja/{idEmpleado}','CajasController@verificarCaja');
    Route::post('/api/cajas/cobroVenta/{idVenta}','CajasController@cobroVenta');
    Route::get('/api/cajas/verificaSesionesCaja','CajasController@verificaSesionesCaja');
    //Route::get('/api/cajas/indexTipoMovimiento','CajasController@indexTipoMovimiento');movimientosSesionCaja
    Route::get('/api/cajas/movimientosSesionCaja/{idCaja}','CajasController@movimientosSesionCaja');
    /******ENTREGAS */
    Route::get('/api/entregas/indexEntregas','VentasController@indexEntregas');
    /******PRODUCTO_PRECIO */
    Route::post('/api/productos_precio/registraPrecio','Producto_precioController@registraPrecio');
/**************************************************************************************+ */


