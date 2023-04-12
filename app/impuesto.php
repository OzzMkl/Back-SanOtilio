<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class impuesto extends Model
{
    protected $table = 'impuesto';
    protected $primaryKey = 'idImpuesto';
    protected $fillable = [
        'nombre', 'valor'
    ];
}
