<?php

namespace App\Clases;

use Illuminate\Support\Facades\DB;

class clsHelpers
{
    /**
     * @param all: True o false
     * @param connection_name: string sucursal a omitir por defecto matriz
     */
    public function getConnections($all = true,$connection_name = 'matriz'){
        $connections = DB::table('sucursal')->whereNotNull('connection');

        if($all){
            $connections = $connections->get();
        } else{
            $connections = $connections->where('connection', '<>', $connection_name)->get();
        }

        return $connections;
    }
}
