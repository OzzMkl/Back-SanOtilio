<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    protected $table = 'marca';
    protected $primaryKey = 'idMarca';
    protected  $fillable = [
        'nombre'
    ];
}
