<?php

namespace Tests\Feature;

use App\Models\DiaSemana;
use App\Models\Empresa;
use App\Models\NaturezaOperacao;
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
}
