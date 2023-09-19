<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Statuss extends Model
{
    protected $table = 'statuss';
    protected $primaryKey = 'idStatus';
    protected  $fillable = [
        'nombre',
        'idModulo',
        'idSubModulo',
    ];
}
