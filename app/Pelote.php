<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pelote extends Model
{
    protected $table = 'lote';
    protected $primaryKey = 'idLote';
    protected $fillable = [
        'codigo','caducidad'
    ];
}
