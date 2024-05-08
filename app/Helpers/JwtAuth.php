<?php

//archivo para autenticacion de usuarios con la libreria JWT

namespace App\Helpers;


use App\models\Empresa;
use Firebase\JWT\JWT;
use Iluminate\Support\Facades\DB;
use App\Empleado;//tabla de empleados
use App\models\Permisos;

class JwtAuth{

    public $key;//creamos la llave publica

    public function __construct(){
        $this->key = 'esta_es_una_clave_secreta-987654321';//llave unica!!
    }


    public function signup($email, $password, $getToken=null){

        //Buscar usuario y comparar sus credeciales (consulta)
        $user = Empleado::where([
            'email' => $email,
            'contrasena' => $password
        ])->first();

        if(is_object($user)){
            //Obtenemos empresa
            $empresa = Empresa::first();
            //traemos los permisos
            $permissions = Permisos::where([
                'idRol' => $user->idRol
            ])->get();
            //comprobar si son correctas
            $signup = false;
            if(is_object($user) && is_object($permissions)){
                $signup= true;
            }

            //Generar el token con los datos del usuario identificado
            if($signup){
                $token = array(
                    'sub'       =>  $user->idEmpleado,
                    'email'     =>  $user->email,
                    'nombre'    =>  $user->nombre,
                    'apellido'  =>  $user->aPaterno,
                    'amaterno'  =>  $user->aMaterno,
                    'idRol'     =>  $user->idRol,
                    'permisos'  =>  $permissions,
                    'empresa'   =>  $empresa,
                    'iat'       =>  time(),
                    'exp'       =>  time() + (7*24*60*60)//durara una semana

                );

                $jwt = JWT::encode($token, $this->key, 'HS256');//creamos el token
                $decoded = JWT::decode($jwt, $this->key, ['HS256']);//deciframos el token
                //devolver los datos decodificados o el token en funcion de los parametros
                if(is_null($getToken)){
                        $data = $jwt;
                }else{
                    $data = $decoded;
                }


            }
        }
        
        else{
            $data = array(//si los datos estan mal o el usuario no exste
                'error' => 404,
                'status' => 'error',
                'message' => 'Los datos ingresados son incorrectos'
            );
        }

        return $data;
    }


    public function checkToken($jwt, $getIdentity = false){//verificamos el token

        $auth = false;//autorizacion falsa por defecto
        //se pone el try-catch por que estos errores son comunes
        try{
            $jwt = str_replace('"', '', $jwt);//reemplazamos las comillas por nada
            $decoded = JWT::decode($jwt, $this->key, ['HS256']);
        }catch(\UnexpectedValueException $e){
            $auth = false;
        }catch(\DomainValueException $e){
            $auth = false;
        }

        //si el objeto no esta vacio y por su puesto viene con forma de objeto y si existe el idEmpleado lo ponemos como verdadero
        //la autentificacion
        if(!empty($decoded) && is_object($decoded) && isset($decoded->sub)){
            $auth = true;
        }else{
            $auth = false;
        }

        if($getIdentity){//flag?
            return $decoded;
        }

        return $auth;

    }

}

?>