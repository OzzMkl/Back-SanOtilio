<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Medidas extends Model
{
    protected $table = 'medidas';
    protected $primaryKey = 'idMedida';
    protected  $fillable = [
        'nombre','claveSat'
    ];
}
