<?php

namespace Tests\Feature;

use App\Models\DiaSemana;
use App\Models\EcommerceConfig;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\ItemNfce;
use App\Models\MarketPlaceConfig;
use App\Models\NaturezaOperacao;
use App\Models\Produto;
use App\Models\ReservaConfig;
use App\Models\TaxaPagamento;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ConfirmedSuperstoreFixesTest extends TestCase
{
    /**
     * @dataProvider removedShowRoutesProvider
     */
    public function test_unused_resource_show_route_is_not_registered(string $showRoute, string $editRoute): void
    {
        $this->assertFalse(Route::has($showRoute));
        $this->assertTrue(Route::has($editRoute));
    }

    public static function removedShowRoutesProvider(): array
    {
        return [
            'clientes' => ['clientes.show', 'clientes.edit'],
            'fornecedores' => ['fornecedores.show', 'fornecedores.edit'],
            'transportadoras' => ['transportadoras.show', 'transportadoras.edit'],
            'categoria-produtos' => ['categoria-produtos.show', 'categoria-produtos.edit'],
            'marcas' => ['marcas.show', 'marcas.edit'],
        ];
    }

    public function test_config_form_renders_when_company_city_is_missing(): void
    {
        $empresa = new Empresa([
            'cidade_id' => 999999,
            'cpf_cnpj' => '00000000000191',
        ]);
        $empresa->setRelation('cidade', null);

        $response = $this->view('config.configuracao', [
            'item' => $empresa,
            'dadosCertificado' => null,
            'naturezas' => collect([new NaturezaOperacao(['descricao' => 'Venda'])]),
        ]);

        $response->assertSee('Cidade');
        $response->assertDontSee('Attempt to read property');
    }

    public function test_atendimento_without_funcionario_shows_safe_label(): void
    {
        $item = new DiaSemana([
            'dia' => json_encode(['segunda']),
            'funcionario_id' => null,
        ]);
        $item->setRelation('funcionario', null);

        $html = Blade::render(
            "{{ \$item->funcionario ? \$item->funcionario->nome : 'Não informado' }}|{{ \$item->diaStr() }}",
            ['item' => $item]
        );

        $this->assertStringContainsString('Não informado', $html);
        $this->assertStringContainsString('Segunda-feira', $html);
    }

    public function test_taxa_pagamento_keeps_valid_codes_and_handles_unknown_codes(): void
    {
        $valid = new TaxaPagamento([
            'tipo_pagamento' => '03',
            'bandeira_cartao' => '01',
        ]);

        $unknown = new TaxaPagamento([
            'tipo_pagamento' => 'XX',
            'bandeira_cartao' => 'YY',
        ]);

        $this->assertSame(TaxaPagamento::tiposPagamento()['03'], $valid->getTipo());
        $this->assertSame(TaxaPagamento::bandeiras()['01'], $valid->getBandeira());
        $this->assertSame('Não informado', $unknown->getTipo());
        $this->assertSame('Não informado', $unknown->getBandeira());
    }

    /**
     * @dataProvider invalidDiaPayloadProvider
     */
    public function test_dia_semana_handles_empty_or_invalid_json(?string $payload): void
    {
        $item = new DiaSemana(['dia' => $payload]);

        $this->assertSame('Não informado', $item->diaStr());
    }

    public static function invalidDiaPayloadProvider(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'invalid-json' => ['{'],
            'empty-array' => ['[]'],
            'unknown-code' => [json_encode(['feriado'])],
        ];
    }

    public function test_dia_semana_keeps_valid_json_days(): void
    {
        $item = new DiaSemana(['dia' => json_encode(['segunda', 'terca'])]);

        $this->assertStringContainsString('Segunda-feira', $item->diaStr());
        $this->assertStringContainsString(DiaSemana::getDias()['terca'], $item->diaStr());
    }

    public function test_address_accessors_handle_missing_city(): void
    {
        $empresa = new Empresa(['rua' => 'Rua A', 'numero' => '1', 'bairro' => 'Centro']);
        $empresa->setRelation('cidade', null);

        $reserva = new ReservaConfig(['rua' => 'Rua B', 'numero' => '2', 'bairro' => 'Bairro']);
        $reserva->setRelation('cidade', null);

        $this->assertStringContainsString('Cidade não informada', $empresa->endereco);
        $this->assertStringContainsString('Cidade não informada', $reserva->endereco);
    }

    public function test_marketplace_and_ecommerce_configs_handle_invalid_json(): void
    {
        $ecommerce = new EcommerceConfig(['tipos_pagamento' => '{']);
        $marketplace = new MarketPlaceConfig([
            'segmento' => '{',
            'tipos_pagamento' => '{',
            'tipo_entrega' => '{',
        ]);

        $this->assertNull($ecommerce->sizeColumn());
        $this->assertSame(0, MarketPlaceConfig::getSegmentoServico($marketplace));
        $this->assertSame(0, $marketplace->aceitaCartao());
        $this->assertSame('Não informado', $marketplace->tiposEntrega());
        $this->assertNull(MarketPlaceConfig::validaCartaoEntrega(null));
    }

    public function test_product_related_descriptions_handle_missing_relationships(): void
    {
        $estoque = new Estoque(['produto_id' => 999]);
        $estoque->setRelation('produto', null);

        $itemNfce = new ItemNfce(['produto_id' => 999]);
        $itemNfce->setRelation('produto', null);
        $itemNfce->setRelation('adicionais', collect());

        $produto = new Produto(['codigo_anp' => 'CODIGO_INVALIDO']);

        $this->assertSame('Produto não informado', $estoque->descricao());
        $this->assertSame('Produto não informado', $itemNfce->descricao());
        $this->assertSame('Não informado', $produto->getDescricaoAnp());
    }
}
