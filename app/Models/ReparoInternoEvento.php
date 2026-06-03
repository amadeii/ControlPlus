<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReparoInternoEvento extends Model
{
    use HasFactory;

    protected $fillable = [
        'reparo_interno_id',
        'tipo',
        'mensagem',
        'user_id',
    ];

    public function reparoInterno()
    {
        return $this->belongsTo(ReparoInterno::class, 'reparo_interno_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
