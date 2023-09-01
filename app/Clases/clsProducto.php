<?php

namespace App\Clases;

use Illuminate\Support\Facades\DB;
use App\Producto;
use App\Productos_medidas;


class clsProducto
{
    public function update_ExistenciaG($idProducto,$idProdMedida,$cantidad){
        //Consultamos producto a actualizar
        $Producto = Producto::where('idProducto',$idProducto)->first();
        //Obtenemos la medida menor de la cantidad a ingresar
        //$cantidadMedMenor = $this->cantidad_En_MedidaMenor($idProducto,$idProdMedida,$cantidad);
        //actuañizamos existencia
        $Producto -> existenciaG = $Producto->existenciaG + $cantidad;
        //guardamos
        $Producto->save();

        //retornamos el producto con su existencia actualizada
        return $Producto;
    }

    /**
     * Calcula la cantidad ingresada en la medida menor del producto
     */
    public function cantidad_En_MedidaMenor($idProducto,$idProdMedida,$cantidad){
        //Cpnsultamos medidas del producto
        $listaPM = Productos_medidas::where('idProducto','=',$idProducto)->select('idProdMedida','unidad')->get();
        //cpntamos cuantas medidas tiene el producto
        $count = count($listaPM);

        $igualMedidaMenor = 0;
        $lugar = 0; 

        //verificamos si el producto tiene una medida
        if($count == 1){
            $igualMedidaMenor = $cantidad;
        } else{ //Desoues de dos medidas buscamos la posicion de la memida en la que se ingreso
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
            } elseif($lugar > 0 && $lugar < $count-1){//medidas intermedias
                $igualMedidaMenor = $cantidad;
                $count--;
                while($lugar < $count){
                    $igualMedidaMenor = $igualMedidaMenor * $listaPM[$lugar+1]['attributes']['unidad'];
                    $lugar++;
                }
            }
        }
        return $igualMedidaMenor;
    }
}