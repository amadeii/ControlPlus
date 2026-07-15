<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiaSemana extends Model
{
    use HasFactory;

    protected $fillable = ['dia', 'funcionario_id', 'empresa_id'];

    public function funcionamento()
	{
		return $this->hasOne(Funcionamento::class, 'dia_id');
	}

	public function funcionario()
	{
		return $this->belongsTo(Funcionario::class, 'funcionario_id');
	}

    public function interrupcao()
	{
		return $this->hasOne(Interrupcoes::class, 'dia_id');
	}

	public function diaStr(){
		$dias = json_decode($this->dia, true);
		if (!is_array($dias) || empty($dias)) {
			return 'Não informado';
		}

		$labels = [];
		foreach($dias as $d){
			$labels[] = \App\Models\DiaSemana::getDiaStr($d);
		}

		return implode(', ', $labels);
	}

	public static function getDias(){
		return [
			'domingo' => 'Domingo',
			'segunda' => 'Segunda-feira',
			'terca' => 'Terça-feira', 
			'quarta' => 'Quarta-feira', 
			'quinta' => 'Quinta-feira', 
			'sexta' => 'Sexta-feira',
			'sabado' => 'Sábado',
		];
	}

	public static function getDiaStr($dia){
		$dias = DiaSemana::getDias();
		return $dias[$dia] ?? 'Não informado';
	}

	public static function getDia($n){
		$dias = [
			'domingo',
			'segunda',
			'terca',
			'quarta', 
			'quinta',
			'sexta',
			'sabado',
		];
		return $dias[$n] ?? null;
	}

}
