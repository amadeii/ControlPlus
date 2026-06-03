<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReparoInternoLinhaProduto extends Model
{
    use HasFactory;

    protected $table = 'reparo_interno_linha_produtos';

    protected $fillable = [
        'reparo_interno_id',
        'produto_id',
        'quantidade',
        'valor',
        'subtotal',
    ];

    protected $casts = [
        'quantidade' => 'float',
        'valor' => 'float',
        'subtotal' => 'float',
    ];

    public function reparoInterno()
    {
        return $this->belongsTo(ReparoInterno::class, 'reparo_interno_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
