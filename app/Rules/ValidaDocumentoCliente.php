<?php

namespace App\Rules;

use App\Models\Cliente;
use Illuminate\Contracts\Validation\Rule;

class ValidaDocumentoCliente implements Rule
{
    protected $empresa_id = null;
    protected $nome = null;
    protected $ignoreId = null;
    protected $message = 'Documento invalido';

    public function __construct($empresa_id, $ignoreId = null)
    {
        $this->empresa_id = $empresa_id;
        $this->ignoreId = $ignoreId;
    }

    public function passes($attribute, $value)
    {
        $documento = preg_replace('/\D/', '', (string) $value);

        if (!self::documentoValido($documento)) {
            $this->message = 'Documento invalido';
            return false;
        }

        $cliente = Cliente::where('empresa_id', $this->empresa_id)
            ->when($this->ignoreId, function ($query) {
                return $query->where('id', '<>', $this->ignoreId);
            })
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') = ?", [$documento])
            ->first();

        if (empty($cliente)) {
            return true;
        }

        $this->nome = $cliente->razao_social;
        $this->message = "Documento ja cadastrado para $this->nome";
        return false;
    }

    public function message()
    {
        return $this->message;
    }

    public static function documentoValido(string $documento): bool
    {
        if (strlen($documento) === 11) {
            return self::cpfValido($documento);
        }

        if (strlen($documento) === 14) {
            return self::cnpjValido($documento);
        }

        return false;
    }

    private static function cpfValido(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($digito = 9; $digito < 11; $digito++) {
            $soma = 0;
            for ($i = 0; $i < $digito; $i++) {
                $soma += (int) $cpf[$i] * (($digito + 1) - $i);
            }

            $resultado = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$digito] !== $resultado) {
                return false;
            }
        }

        return true;
    }

    private static function cnpjValido(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        for ($digito = 12; $digito < 14; $digito++) {
            $soma = 0;
            foreach ($pesos[$digito - 12] as $i => $peso) {
                $soma += (int) $cnpj[$i] * $peso;
            }

            $resto = $soma % 11;
            $resultado = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[$digito] !== $resultado) {
                return false;
            }
        }

        return true;
    }
}
