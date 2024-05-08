<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Requisiciones extends Model
{
    protected $table = 'requisicion';
    protected $primaryKey = 'idReq';
    protected  $fillable = [
        'idProveedor','observaciones','idEmpleado','idStatus','idOrd'
    ];
}
