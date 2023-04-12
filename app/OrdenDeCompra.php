<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrdenDeCompra extends Model
{
    protected $table = 'ordendecompra';
    protected $primaryKey = 'idOrd';
    protected  $fillable = [
        'idReq','idProveedor','observaciones','fecha','idEmpleado','idStatus'
    ];
}
