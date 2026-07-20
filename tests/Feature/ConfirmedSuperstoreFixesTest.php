<?php

namespace Tests\Feature;

use App\Models\DiaSemana;
use App\Models\EcommerceConfig;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\ItemNfce;
use App\Models\MarketPlaceConfig;
use App\Models\NaturezaOperacao;
use App\Models\Permission;
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

    public function test_assistance_stock_adjustment_no_longer_requires_assistance_os_type(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AssistenciaEstoqueAjusteManualController.php'));
        $service = file_get_contents(app_path('Services/AssistenciaEstoqueAjusteManualService.php'));

        $this->assertStringNotContainsString('integraEstoqueParaEmpresa', $controller);
        $this->assertStringNotContainsString('Fluxo dispon', $controller);
        $this->assertStringNotContainsString('integraEstoqueParaEmpresa', $service);
        $this->assertStringNotContainsString('tipo de OS', $service);
    }

    public function test_assistance_stock_adjustment_routes_keep_permission_middleware(): void
    {
        $create = Route::getRoutes()->getByName('assistencia-estoque-ajuste.create');
        $store = Route::getRoutes()->getByName('assistencia-estoque-ajuste.store');
        $index = Route::getRoutes()->getByName('assistencia-estoque-ajuste.index');
        $show = Route::getRoutes()->getByName('assistencia-estoque-ajuste.show');

        $this->assertNotNull($create);
        $this->assertNotNull($store);
        $this->assertNotNull($index);
        $this->assertNotNull($show);

        $this->assertContains('permission:assistencia_estoque_ajuste_create', $create->gatherMiddleware());
        $this->assertContains('permission:assistencia_estoque_ajuste_create', $store->gatherMiddleware());
        $this->assertContains('permission:assistencia_estoque_ajuste_view', $index->gatherMiddleware());
        $this->assertContains('permission:assistencia_estoque_ajuste_view', $show->gatherMiddleware());
    }

    public function test_assistance_stock_adjustment_still_validates_company_scope(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AssistenciaEstoqueAjusteManualController.php'));

        $this->assertStringContainsString("Rule::exists('produtos', 'id')->where('empresa_id', \$empresaId)", $controller);
        $this->assertStringContainsString("Rule::exists('depositos', 'id')->where('empresa_id', \$empresaId)", $controller);
        $this->assertStringContainsString("Rule::exists('localizacaos', 'id')", $controller);
        $this->assertStringContainsString('__validaObjetoEmpresa($item)', $controller);
    }

    public function test_goals_resource_uses_expected_permissions(): void
    {
        $routes = [
            'metas.index' => 'permission:metas_view',
            'metas.create' => 'permission:metas_create',
            'metas.store' => 'permission:metas_create',
            'metas.edit' => 'permission:metas_edit',
            'metas.update' => 'permission:metas_edit',
            'metas.destroy' => 'permission:metas_delete',
        ];

        foreach ($routes as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} should exist.");
            $this->assertContains($middleware, $route->gatherMiddleware(), "Route {$routeName} should require {$middleware}.");
        }
    }

    public function test_goals_permissions_are_available_for_master_and_admin_sync(): void
    {
        $permissions = collect(Permission::defaultPermissions())->pluck('name')->all();

        $this->assertContains('metas_view', $permissions);
        $this->assertContains('metas_create', $permissions);
        $this->assertContains('metas_edit', $permissions);
        $this->assertContains('metas_delete', $permissions);
    }

    public function test_pdv_create_warns_without_natureza_but_finalization_keeps_guard(): void
    {
        $webController = file_get_contents(app_path('Http/Controllers/FrontBoxController.php'));
        $apiController = file_get_contents(app_path('Http/Controllers/API/FrontBoxController.php'));

        $this->assertStringContainsString('Configure a natureza de operacao padrao antes de finalizar a venda.', $webController);
        $this->assertStringContainsString('empresaPossuiNaturezaPdv', $apiController);
        $this->assertStringContainsString('naturezaPdvNaoConfiguradaResponse', $apiController);
        $this->assertGreaterThanOrEqual(4, substr_count($apiController, 'empresaPossuiNaturezaPdv($request->empresa_id)'));
        $this->assertStringContainsString('Configure a natureza de operacao padrao para finalizar a venda.', $apiController);
    }

    public function test_product_search_respects_selected_deposito_with_legacy_local_fallback(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/API/ProdutoController.php'));

        $this->assertStringContainsString('$deposito_id = $request->filled(\'deposito_id\')', $controller);
        $this->assertStringContainsString("where('estoques.deposito_id', \$deposito_id)", $controller);
        $this->assertStringContainsString("whereNull('estoques.deposito_id')", $controller);
        $this->assertStringContainsString("where('deposito_id', \$deposito_id)", $controller);
        $this->assertStringContainsString("whereNull('deposito_id')", $controller);
        $this->assertStringContainsString("->where('produtos.status', 1)", $controller);
        $this->assertStringContainsString("if(\$estoque == null && \$local_id != null)", $controller);
    }

    public function test_devolucao_resource_does_not_expose_missing_create_or_store_actions(): void
    {
        $this->assertFalse(Route::has('devolucao.create'));
        $this->assertFalse(Route::has('devolucao.store'));
        $this->assertTrue(Route::has('devolucao.index'));
        $this->assertTrue(Route::has('devolucao.xml'));
        $this->assertTrue(Route::has('devolucao.store-xml'));

        foreach ($this->projectSourceFiles() as $file => $contents) {
            $this->assertStringNotContainsString('devolucao.create', $contents, $file);
            $this->assertStringNotContainsString('/devolucao/create', $contents, $file);

            if (str_contains($contents, 'devolucao.store')) {
                $this->assertStringContainsString('devolucao.store-xml', $contents, $file);
            }
        }
    }

    public function test_troca_create_permissions_are_declared_and_routes_keep_middleware(): void
    {
        $permissions = collect(Permission::defaultPermissions())->pluck('name')->all();
        $route = Route::getRoutes()->getByName('trocas.create');
        $view = file_get_contents(resource_path('views/trocas/index.blade.php'));

        $this->assertContains('troca_view', $permissions);
        $this->assertContains('troca_create', $permissions);
        $this->assertContains('troca_delete', $permissions);
        $this->assertNotNull($route);
        $this->assertContains('permission:troca_create', $route->gatherMiddleware());
        $this->assertStringContainsString("@can('troca_create')", $view);
    }

    public function test_troca_permissions_are_synced_to_existing_admin_roles_by_existing_flows(): void
    {
        $empresaUtil = file_get_contents(app_path('Utils/EmpresaUtil.php'));
        $loginController = file_get_contents(app_path('Http/Controllers/Auth/LoginController.php'));
        $permissionController = file_get_contents(app_path('Http/Controllers/PermissionController.php'));

        $this->assertStringContainsString('ensureDefaultPermissionsInDatabase', $empresaUtil);
        $this->assertStringContainsString('$baseAdmin->permissions()->sync(Permission::all())', $empresaUtil);
        $this->assertStringContainsString('$companyRole->permissions()->sync($permissionIds)', $empresaUtil);
        $this->assertStringContainsString('$this->empresaUtil->syncCompanyPermissions($empresa_id)', $loginController);
        $this->assertStringContainsString('app(EmpresaUtil::class)->syncCompanyPermissions((int) $empresaId)', $permissionController);
    }

    private function projectSourceFiles(): array
    {
        $files = [];
        foreach (['app', 'resources', 'routes', 'public'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $extension = $file->getExtension();
                if (!in_array($extension, ['php', 'blade.php', 'js'], true)) {
                    continue;
                }

                $path = $file->getPathname();
                $files[$path] = file_get_contents($path);
            }
        }

        return $files;
    }

    public function test_goals_controller_keeps_company_scope(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MetaResultadoController.php'));

        $this->assertStringContainsString("MetaResultado::where('empresa_id', request()->empresa_id)", $controller);
        $this->assertStringContainsString("Funcionario::where('empresa_id', request()->empresa_id)->find", $controller);
        $this->assertStringContainsString("Rule::exists('funcionarios', 'id')->where('empresa_id', \$empresaId)", $controller);
        $this->assertStringContainsString("Rule::exists('localizacaos', 'id')->where('empresa_id', \$empresaId)", $controller);
        $this->assertStringContainsString('__validaObjetoEmpresa($item)', $controller);
        $this->assertStringContainsString("\$data['empresa_id'] = \$empresaId;", $controller);
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
