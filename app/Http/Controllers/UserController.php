<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;//se habilita para la obtencion de la imagen
use Illuminate\Support\Facades\DB;
use Validator;
use App\Empleado;

class UserController extends Controller
{
    // public function pruebas(Request $request){
    //     return "acciond e pruebas de usercontroller";
    // }

    public function register(Request $request){

        //recorger datos de usuario enviados pór POST en formato JSON
        $json = $request->input('json', null);
       //decodificamos el json
       $params = json_decode($json);//esto devuelve un objeto
       $params_array = json_decode($json, true);//esto devuelve un array

       if(!empty($params) && !empty($params_array)){//especificamos que no esten vacios
        //eliminar espacios
        $params_array = array_map('trim', $params_array);

            //validar datos
            $validate = Validator::make($params_array, [
                'name'       => 'required|alpha',
                'surname'    => 'required|alpha',
                'email'      => 'required|email|unique:empleado',//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
                'password'   => 'required'
            ]);

            if($validate->fails()){//si el json esta mal mandamos esto (falta algun dato)
                $data = array(
                    'status'    => 'error',
                    'code'      => 404,
                    'message'   => 'Elusuario no se ha creado',
                    'errors'    => $validate->errors()
                );
            }else{
                //cifrar contraseña
                $pwd = hash('sha256', $params->password);

                //Crear usuario
                $user = new Empleado();
                $user->nombre = $params_array['name'];
                $user->apaterno = $params_array['surname'];
                $user->amaterno = $params_array['amaterno'];
                $user->estado = $params_array['estado'];
                $user->ciudad = $params_array['ciudad'];
                $user->colonia = $params_array['colonia'];
                $user->calle = $params_array['calle'];
                $user->numExt = $params_array['numExt'];
                $user->numInt = $params_array['numInt'];
                $user->cp = $params_array['cp'];
                $user->celular = $params_array['celular'];
                $user->telefono = $params_array['telefono'];
                $user->email = $params_array['email'];
                $user->contrasena = $pwd;

                //guardar el usuario
                $user->save();

                $data = array(//si esta bien el json
                    'status'    => 'success',
                    'code'      => 200,
                    'message'   => 'El usuario se ha creado correctamente'
                );
            }
       }else{//si el json esta vacio mandamos esto
        $data = array(
            'status'    => 'error',
            'code'      => 404,
            'message'   => 'Los datos no son correctos'
        );
       }



      
        //generamos el array
        return response()->json($data, $data['code']);

    }

    
    public function login(Request $request){
        $jwtAuth = new \JwtAuth();//mandamos a traer la libreria de jwt

        //recibimos datos por metodo POST
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        //validar datos
        $validate = Validator::make($params_array, [
            'email'      => 'required|email',//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
            'password'   => 'required'
        ]);
        if($validate->fails()){//si el usuario manda datos incorrectos
            $signup = array(
                'status'    => 'error',
                'code'      => 404,
                'message'   => 'El usuario no se ha podido identificar',
                'errors'    => $validate->errors()
            );
        }else{
            //cifrar contraseña
            $pwd = hash('sha256', $params->password);
            //devolver token o en su caso datos
            $signup = $jwtAuth->signup($params->email, $pwd);

            if(!empty($params->getToken)){
                $signup = $jwtAuth->signup($params->email, $pwd, true);
            }

        }


   
        
        return response()->json($signup, 200);
    }

    public function update(Request $request){//para actualizar los datos del usuario

        //COMPROBAMOS QUE EL USUARIO ESTE IDENTIFICADO
        //recogemos el token desde la cabezera
        $token = $request->header('Authorization');
        $jwtAuth = new \JwtAuth();
        $checkToken = $jwtAuth->checkToken($token);

        //recoger los datos por post
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        if($checkToken && !empty($params_array)){
            
            
            //sacamos el usuario que se ha logeado
            $user = $jwtAuth->checkToken($token, true);
            //validamos los datos
            $validate = Validator::make($params_array, [
                'nombre'       => 'required|alpha',
                'aPaterno'    => 'required|alpha',
                'email'      => 'required|email|unique:empleado'.$user->sub//comprobar si el usuario existe ya (duplicado) y comparamos con la tabla
            ]);
            //Quitar datos que no quiero actualizar
            unset($params_array['idEmpleado']);
            unset($params_array['idRol']);
            unset($params_array['idsuc']);
            unset($params_array['idStatus']);
            unset($params_array['fechaAlta']);
            unset($params_array['contrasena']);
            unset($params_array['licencia']);
            unset($params_array['created_at']);
            unset($params_array['remember_token']);
            //Actualizar usuario en base de datos
            $user_update = Empleado::where('idEmpleado', $user->sub)->update($params_array);
            //Devolver array con resultado efectuado
            $data = array(
                'code'      =>  200,
                'status'    =>  'success',
                'user'   =>  '$user_update'
            );

        }else{
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'El usuario no esta identificado'
            );
        }
        return response()->json($data, $data['code']);
    }

    public function upload(Request $request){//METODO PARA CARGA DE IMAGEN
        //recoger image
        $image = $request->file('file0');
        //validamos que el archivo que se reciba sea realmente una imagen
        $validate =Validator::make($request->all(), [
            'file0'     =>  'required|image|mimes:jpg,jpeg,png,gif'
        ]);

        //guardar imagen
        if(!$image || $validate->fails()){
            
            $data = array(//mandamos mensaje de error si la carga salio mal
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'Error al subir imagen'
            );

        }else{
            $image_name = time().$image->getClientOriginalName();
            //Para hace esto se creo la carpeta en storage->app y se agrego la ruta en config->filesystems.php
            \Storage::disk('users')->put($image_name, \File::get($image));

            $data = array(//confirmamos de que fue correcta la carga
                'code'      =>  200,
                'status'    =>  'success',
                'image'     =>  $image_name
            );
        }

       

        return response()->json($data, $data['code']);
    }

    public function getImage($filename){
        $isset = \Storage::disk('users')->exists($filename);
        if($isset){
                 //para usar este metodo lo importamos desde arriba
        //y creamos la ruta en web.php
            $file = \Storage::disk('users')->exists($filename);
            /*Esto se hizo para comprobar que la imagen fuera correcta ya que como esta muestra error
            $data = array(
                'code'      =>  200,
                'status'    =>  'bien',
                'image' => base64_encode($file)
            );
            return response()->json($data, $data['code']);
           */
            return new Response($file, 200);
        }else{
            $data = array(
                'code'      =>  404,
                'status'    =>  'error',
                'message'   =>  'La imagen no existe'
            );
            return response()->json($data, $data['code']);
        }
        
    }

    public function detail($idEmpleado){
        $user = Empleado::find($idEmpleado);
        if(is_object($user)){
            $data = array(
                'code'      =>  200,
                'status'    =>  'success',
                'user'      =>  $user
            );
        }else{
            $data = array(
                'code'      =>  404,
                'status'    =>  'error',
                'user'      =>  'El usuario no existe'
            );
        }
        return response()->json($data, $data['code']);
    }

    public function indexPermisos(){
        $permisos = DB::table('permisos')
        ->get();
        return response()->json([
            'code'      =>  200,
            'status'    =>  'success',
            'permisos'       =>  $permisos
        ]);
    }
    public function RolesBySubmodulo($idSubModulo){
        $roles = DB::table('permisos')
                //->select('permisos.idRol')
                ->where('idSubModulo','=',$idSubModulo)
                ->get();
        return response()->json([
            'code'      =>  200,
            'status'    =>  'success',
            'roles'     =>  $roles
        ]);
    }
    public function PermissionsByRol( $idRol,$idModulo,$idSubModulo){

                 $permissions = DB::table('permisos')
                             //->select('permisos.*')
                             ->where('idRol','=',$idRol)
                             ->where('idModulo','=',$idModulo)
                             ->where('idSubModulo','=',$idSubModulo)
                             ->get();

                return response()->json([
                                'code'          =>  200,
                                'status'        => 'success',
                                'permisos'   =>  $permissions
                            ]);
    }

}
