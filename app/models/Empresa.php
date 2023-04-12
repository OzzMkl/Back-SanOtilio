<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresa';
    protected $fillable = [
        'nombreCorto','nombreLargo','calle','numero',
        'colonia','cp','ciudad','estado','pais',
        'telefono','telefono2','rfc','correo1','correo2'
    ];
}
