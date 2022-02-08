<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SubCategoria extends Model
{
    //
    protected $table = 'subcategoria';
    protected $primaryKey = 'idSubCat';
    protected $fillable = [
        'idCat','nombre'
    ];
}
