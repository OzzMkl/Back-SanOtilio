<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'cliente';
    protected $primaryKey = 'idCliente';
    protected $fillable = [
        'nombre', 'aPaterno', 'aMaterno', 'rfc',
        'correo', 'credito', 'idStatus', 'idTipo'
    ];
}
