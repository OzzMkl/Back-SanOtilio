<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pelote extends Model
{
    protected $table = 'pelote';
    protected $fillable = [
        'idProducto','existencia','idLote','caducidad'
    ];
}
