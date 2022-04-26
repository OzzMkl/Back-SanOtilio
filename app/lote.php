<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class lote extends Model
{
    protected $table = 'lote';
    protected $primaryKey = 'idLote';
    protected $fillable = [
        'codigo'
    ];
}
