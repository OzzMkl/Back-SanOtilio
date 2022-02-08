<?php

namespace App\Http\Middleware;

use Closure;
//esta clase se creo con [php artisan make:middleware ApiAuthMiddleware]
//con esto nos evitamos de andar repitiendo el codigo de abajo para verificar la informacion del usuario
//este se debe agregar en las rutas [web.php] y en [Kernel.php]
class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
         //COMPROBAMOS QUE EL USUARIO ESTE IDENTIFICADO
        //recogemos el token desde la cabezera
        $token = $request->header('Authorization');
        $jwtAuth = new \JwtAuth();
        $checkToken = $jwtAuth->checkToken($token);

        if($checkToken){
            return $next($request);
        }else{
            $data = array(
                'code'      =>  400,
                'status'    =>  'error',
                'message'   =>  'El usuario no esta identificado'
            );
            return response()->json($data, $data['code']);
        }
        
    }
}
