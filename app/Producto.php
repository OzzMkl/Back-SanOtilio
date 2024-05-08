<?php

namespace App;

use App\models\Statuss;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    //
    protected $table = 'producto';
    protected $primaryKey = 'idProducto';
    protected  $fillable = [
        'idMarca',
        'idDep',
        'idCat',
        'claveEx',
        'cbarras',
        'descripcion',
        'stockMin',
        'stockMax',
        'imagen',
        'statuss',
        'ubicacion',
        'claveSat',
        'tEntrega',
        'idAlmacen',
        'existenciaG'
    ];

    public function marca(){
        return $this->belongsTo(Marca::class, 'idMarca', 'idMarca');
    }

    public function departamento(){
        return $this->belongsTo(Departamento::class, 'idDep', 'idDep');
    }

    public function categoria(){
        return $this->belongsTo(Categoria::class, 'idCat', 'idCat');
    }

    public function status(){
        return $this->belongsTo(models\Statuss::class, 'statuss', 'idStatus');
    }

    public function almacen(){
        return $this->belongsTo(Almacenes::class, 'idAlmacen', 'idAlmacen');
    }
}
