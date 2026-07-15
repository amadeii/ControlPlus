<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MetaResultado;
use App\Models\Funcionario;
use Illuminate\Validation\Rule;

class MetaResultadoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:metas_view', ['only' => ['show', 'index']]);
        $this->middleware('permission:metas_create', ['only' => ['create', 'store']]);
        $this->middleware('permission:metas_edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:metas_delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {   
        $data = MetaResultado::where('empresa_id', request()->empresa_id)
        ->when(!empty($request->funcionario_id), function ($q) use ($request) {
            return $q->where('funcionario_id', $request->funcionario_id);
        })
        ->paginate(__itensPagina());

        $funcionario = Funcionario::where('empresa_id', request()->empresa_id)->find($request->funcionario_id);
        return view('metas_resultado.index', compact('data', 'funcionario'));
    }

    public function create()
    {
        return view('metas_resultado.create');
    }

    public function edit($id)
    {
        $item = MetaResultado::findOrFail($id);
        __validaObjetoEmpresa($item);
        return view('metas_resultado.edit', compact('item'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        try {
            
            MetaResultado::create($data);
            __createLog($data['empresa_id'], 'Meta', 'cadastrar', $data['tabela']);
            session()->flash('flash_success', 'Cadastrado com sucesso');
        } catch (\Exception $e) {
            __createLog(request()->empresa_id, 'Meta', 'erro', $e->getMessage());
            session()->flash('flash_error', 'Não foi possível concluir o cadastro' . $e->getMessage());
        }
        return redirect()->route('metas.index');
    }

    public function update(Request $request, $id)
    {
        $item = MetaResultado::findOrFail($id);
        __validaObjetoEmpresa($item);
        $data = $this->validateData($request);

        try {
            
            $item->fill($data)->save();
            __createLog($data['empresa_id'], 'Meta', 'editar', $item->funcionario ? $item->funcionario->nome : 'Meta #' . $item->id);
            session()->flash('flash_success', 'Alterado com sucesso');
        } catch (\Exception $e) {
            __createLog(request()->empresa_id, 'Meta', 'erro', $e->getMessage());
            session()->flash('flash_error', 'Não foi possível alterar o cadastro' . $e->getMessage());
        }
        return redirect()->route('metas.index');
    }

    public function destroy($id)
    {
        $item = MetaResultado::findOrFail($id);
        __validaObjetoEmpresa($item);
        try {
            $descricaoLog = $item->funcionario->nome;
            $item->delete();
            __createLog(request()->empresa_id, 'Meta', 'excluir', $descricaoLog);
            session()->flash('flash_success', 'Deletado com sucesso');
        } catch (\Exception $e) {
            __createLog(request()->empresa_id, 'Meta', 'erro', $e->getMessage());
            session()->flash('flash_error', 'Não foi possível deletar' . $e->getMessage());
        }
        return redirect()->back();
    }

    private function validateData(Request $request): array
    {
        $empresaId = (int) request()->empresa_id;

        $data = $request->validate([
            'funcionario_id' => [
                'required',
                'integer',
                Rule::exists('funcionarios', 'id')->where('empresa_id', $empresaId),
            ],
            'valor' => ['required'],
            'tabela' => ['required', Rule::in(array_keys(MetaResultado::tabelas()))],
            'local_id' => [
                'nullable',
                'integer',
                Rule::exists('localizacaos', 'id')->where('empresa_id', $empresaId),
            ],
        ]);

        $data['empresa_id'] = $empresaId;
        $data['valor'] = __convert_value_bd($data['valor']);
        $data['local_id'] = $data['local_id'] ?? null;

        return $data;
    }
}
