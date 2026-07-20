<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProdutoUnico;
use App\Models\Produto;
use App\Utils\StatusKeyUtil;

class ProdutoConsultaCodigoController extends Controller
{
    public function index(Request $request){
        $produto_id = $request->produto_id;
        $codigo = $request->codigo;
        $produto = null;
        $data = [];

        if($produto_id || $codigo){
            $data = ProdutoUnico::
            where('produtos.empresa_id', $request->empresa_id)
            ->select('produto_unicos.*')
            ->join('produtos', 'produtos.id', '=', 'produto_unicos.produto_id')
            ->when($codigo, function ($q) use ($codigo) {
                return $q->where('produto_unicos.codigo', 'LIKE', "%$codigo%");
            })
            ->when($produto_id, function ($q) use ($produto_id) {
                return $q->where('produto_unicos.produto_id', $produto_id);
            })
            ->groupBy('produto_unicos.codigo')
            ->orderBy('produto_unicos.created_at', 'desc')
            ->get();
        }

        if($produto_id){
            $produto = Produto::findOrFail($produto_id);
        }
        return view('produtos.consulta_codigo', compact('data', 'produto'));
    }

    public function produtos(Request $request)
    {
        $pesquisa = trim((string)$request->pesquisa);

        $data = Produto::where('empresa_id', $request->empresa_id)
        ->where('status', 1)
        ->when($pesquisa !== '', function ($q) use ($pesquisa) {
            $refDigito = substr($pesquisa, 0, 1);

            if($refDigito == '#'){
                $pesquisa = substr($pesquisa, 1);
                return $q->where(function($query) use ($pesquisa) {
                    return $query->where('referencia', 'LIKE', "%$pesquisa%")
                    ->orWhere('sku', 'LIKE', "%$pesquisa%");
                });
            }

            if(is_numeric($pesquisa)){
                return $q->where(function($query) use ($pesquisa) {
                    return $query->where('id', (int)$pesquisa)
                    ->orWhere('codigo_barras', 'LIKE', "%$pesquisa%")
                    ->orWhere('codigo_barras2', 'LIKE', "%$pesquisa%")
                    ->orWhere('codigo_barras3', 'LIKE', "%$pesquisa%")
                    ->orWhere('numero_sequencial', 'LIKE', "%$pesquisa%")
                    ->orWhere('sku', 'LIKE', "%$pesquisa%");
                });
            }

            return $q->where(function($query) use ($pesquisa) {
                return $query->where('nome', 'LIKE', "%$pesquisa%")
                ->orWhere('referencia', 'LIKE', "%$pesquisa%")
                ->orWhere('sku', 'LIKE', "%$pesquisa%")
                ->orWhere('codigo_barras', 'LIKE', "%$pesquisa%");
            });
        })
        ->orderBy('nome')
        ->limit(30)
        ->get(['id', 'nome', 'sku', 'codigo_barras']);

        $results = $data->map(function($item) {
            $complemento = [];
            if($item->sku){
                $complemento[] = 'SKU ' . $item->sku;
            }
            if($item->codigo_barras){
                $complemento[] = $item->codigo_barras;
            }
            $complemento[] = '#' . $item->id;

            return [
                'id' => $item->id,
                'text' => $item->nome . ' - ' . implode(' - ', $complemento)
            ];
        });

        return response()->json(['results' => $results], 200);
    }

    public function codigos(Request $request)
    {
        $pesquisa = trim((string)$request->pesquisa);

        $data = ProdutoUnico::
        where('produtos.empresa_id', $request->empresa_id)
        ->where('produto_unicos.tipo', 'entrada')
        ->where('produto_unicos.em_estoque', 1)
        ->where('produto_unicos.status_key', StatusKeyUtil::DEFAULT_STATUS)
        ->where('produtos.status', 1)
        ->select('produto_unicos.*')
        ->join('produtos', 'produtos.id', '=', 'produto_unicos.produto_id')
        ->when($request->produto_id, function ($q) use ($request) {
            return $q->where('produto_unicos.produto_id', $request->produto_id);
        })
        ->when($pesquisa !== '', function ($q) use ($pesquisa) {
            return $q->where('produto_unicos.codigo', 'LIKE', "%$pesquisa%");
        })
        ->when($request->local_id, function ($q) use ($request) {
            return $q->where(function ($sub) use ($request) {
                return $sub->where('produto_unicos.local_id', $request->local_id)
                ->orWhereNull('produto_unicos.local_id');
            });
        })
        ->groupBy('produto_unicos.codigo')
        ->orderBy('produto_unicos.created_at', 'desc')
        ->limit(30)
        ->get();

        $results = $data->map(function($item) {
            $text = $item->codigo;
            if($item->observacao){
                $text .= ' - ' . $item->observacao;
            }

            return [
                'id' => $item->codigo,
                'text' => $text
            ];
        });

        return response()->json(['results' => $results], 200);
    }
}
