<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Requisiciones extends Model
{
    protected $table = 'requisicion';
    protected $primaryKey = 'idReq';
    protected  $fillable = [
        'observaciones','idEmpleado','idStatus','idOrd'
    ];
}
