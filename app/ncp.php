<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ncp extends Model
{
    //
    protected $table = 'ncp';
    protected $primaryKey = 'idncp';
    protected $fillable = [
        'idProveedor','ncuenta','idBanco','titular','clabe'
    ];
}
