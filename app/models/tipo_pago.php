<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class tipo_pago extends Model
{
    protected $table = 'tipo_pago';
    protected $primaryKey = 'idt';
    protected $fillable = [
        'tipo',
        'sat'
    ];
}
