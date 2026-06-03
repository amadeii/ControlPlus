<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdemServico extends Model
{
    use HasFactory;

    public const ESCOPO_CLIENTE = 'cliente';

    public const ESCOPO_INTERNA = 'interna';

    protected $fillable = [
        'descricao', 'cliente_id', 'usuario_id', 'empresa_id', 'valor', 'data_inicio', 'data_entrega', 'data_previsao_entrega', 'funcionario_id',
        'tecnico_responsavel_id', 'assistencia_fase_tecnica',
        'forma_pagamento', 'codigo_sequencial', 'caixa_id', 'local_id', 'adiantamento', 'veiculo_id', 'hash_link',
        'tipo_servico', 'diagnostico_cliente', 'diagnostico_tecnico', 'defeito_encontrado', 'equipamento', 'marca_equipamento', 'modelo_equipamento', 'numero_serie',
        'senha_aparelho', 'acessorios', 'cor', 'tradein_inventory_item_id',
        'escopo_ordem_servico', 'produto_aparelho_id', 'produto_aparelho_unico_id',
    ];

    public function servicos(){
        return $this->hasMany(ServicoOs::class, 'ordem_servico_id', 'id');
    }

    public function empresa(){
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function itens(){
        return $this->hasMany(ProdutoOs::class, 'ordem_servico_id', 'id');
    }

    public function fatura(){
        return $this->hasMany(FaturaOrdemServico::class, 'ordem_servico_id', 'id');
    }

    public function relatorios(){
        return $this->hasMany(RelatorioOs::class, 'ordem_servico_id', 'id');
    }

    public function funcionarios(){
        return $this->hasMany(FuncionarioOs::class, 'ordem_servico_id', 'id');
    } 

    public function funcionario(){
        return $this->belongsTo(Funcionario::class, 'funcionario_id');
    }

    public function tecnicoResponsavel()
    {
        return $this->belongsTo(Funcionario::class, 'tecnico_responsavel_id');
    }

    public function assistenciaEventos()
    {
        return $this->hasMany(OrdemServicoAssistenciaEvento::class, 'ordem_servico_id')->orderByDesc('id');
    }

    public function assistenciaChecklistItens()
    {
        return $this->hasMany(OrdemServicoAssistenciaChecklistItem::class, 'ordem_servico_id')->orderBy('id');
    }

    public function assistenciaChecklistFisicoItens()
    {
        return $this->hasMany(OrdemServicoAssistenciaChecklistFisicoItem::class, 'ordem_servico_id')->orderBy('id');
    }

    public function anexos()
    {
        return $this->hasMany(OrdemServicoAnexo::class, 'ordem_servico_id')->orderBy('id');
    }

    public function documentos()
    {
        return $this->hasMany(OrdemServicoDocumento::class, 'ordem_servico_id')->orderBy('id');
    }

    /**
     * Fases operacionais da bancada (assistência). Independe do estado financeiro pd/ap/rp/fz.
     *
     * @return array<string, string>
     */
    public static function assistenciaFasesTecnicas(): array
    {
        return [
            'fila' => 'Na fila',
            'diagnostico' => 'Diagnóstico / triagem',
            'aguardando_peca' => 'Aguardando peça',
            'em_reparo' => 'Em reparo',
            'controle_qualidade' => 'Controle de qualidade',
            'pronto_retirada' => 'Pronto para retirada',
        ];
    }

    /** @return array<string, string> */
    public static function assistenciaChecklistFisicoDefinicoes(): array
    {
        return [
            'tela' => 'Tela',
            'tampa' => 'Tampa',
            'touch' => 'Touch',
            'cameras' => 'Câmeras',
            'bateria' => 'Bateria',
        ];
    }

    /** @return array<string, string> */
    public static function assistenciaChecklistFisicoEstados(): array
    {
        return [
            'ok' => 'OK',
            'avariado' => 'Avariado',
            'nao_testado' => 'Não testado / não aplicável',
        ];
    }

    public function oticaOs(){
        return $this->hasOne(OticaOs::class, 'ordem_servico_id');
    }

    public function medicaoReceitaOs(){
        return $this->hasOne(MedicaoReceitaOs::class, 'ordem_servico_id');
    }

    public function cliente(){
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function veiculo(){
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function usuario(){
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function tradeinInventoryItem(){
        return $this->belongsTo(TradeinInventoryItem::class, 'tradein_inventory_item_id');
    }

    public function tradeinCustoPecaLancamentos(){
        return $this->hasMany(TradeinInventoryItemCustoPecaOsLancamento::class, 'ordem_servico_id')
            ->orderBy('id');
    }

    public static function escoposOrdemServico(): array
    {
        return [
            self::ESCOPO_CLIENTE => 'Cliente',
            self::ESCOPO_INTERNA => 'Interna (loja)',
        ];
    }

    public function isOsInterna(): bool
    {
        return $this->escopo_ordem_servico === self::ESCOPO_INTERNA;
    }

    public function produtoAparelho()
    {
        return $this->belongsTo(Produto::class, 'produto_aparelho_id');
    }

    public function produtoAparelhoUnico()
    {
        return $this->belongsTo(ProdutoUnico::class, 'produto_aparelho_unico_id');
    }

    public static function estados(){
        return [
            'pd' => 'Pedente',
            'ap' => 'Aprovado',
            'rp' => 'Reprovado',
            'fz' => 'Finalizado',
        ];
    }

    /**
     * Transições explícitas do status comercial da OS.
     *
     * @return array<string, array<int, string>>
     */
    public static function transicoesEstadoPermitidas(): array
    {
        return [
            'pd' => ['ap', 'rp'],
            'ap' => ['fz'],
            'rp' => [],
            'fz' => [],
        ];
    }

    public static function estadoEhValido(string $estado): bool
    {
        return array_key_exists($estado, self::estados());
    }

    /**
     * @return array<int, string>
     */
    public function estadosDestinoPermitidos(): array
    {
        $mapa = self::transicoesEstadoPermitidas();

        return $mapa[$this->estado] ?? [];
    }

    public function podeTransicionarEstadoPara(string $novoEstado): bool
    {
        if ($this->estado === $novoEstado) {
            return true;
        }

        return in_array($novoEstado, $this->estadosDestinoPermitidos(), true);
    }


    public static function tiposDeOrdemServico(){
        return [
            'normal' => 'Normal',
            'assistencia técinica' => 'Assistência técnica',
            'oficina' => 'Oficina',
        ];
    }

    public static function tiposDeServico(){
        return [
            '' => 'Selecione',
            'reparo' => 'Reparo',
            'manutenção' => 'Manutenção',
            'instalação' => 'Instalação',
        ];
    }

}
