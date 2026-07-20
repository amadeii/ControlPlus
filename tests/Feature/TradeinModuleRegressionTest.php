<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TradeinModuleRegressionTest extends TestCase
{
    public function test_tradein_web_creation_scopes_customer_and_product_to_current_company(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TradeinController.php'));

        $this->assertStringContainsString('use Illuminate\Validation\Rule;', $controller);
        $this->assertStringContainsString('$empresaId = (int) $request->empresa_id;', $controller);
        $this->assertStringContainsString("Rule::exists('clientes', 'id')->where('empresa_id', \$empresaId)", $controller);
        $this->assertStringContainsString("Rule::exists('produtos', 'id')->where('empresa_id', \$empresaId)", $controller);
        $this->assertStringContainsString("Produto::where('empresa_id', \$empresaId)->find(\$produtoId)", $controller);
        $this->assertStringContainsString("Tradein::where('empresa_id', \$empresaId)", $controller);
        $this->assertStringContainsString("'empresa_id'        => \$empresaId", $controller);
        $this->assertStringContainsString("'cliente_id'        => \$validated['cliente_id']", $controller);
        $this->assertStringContainsString("'produto_id'        => \$produtoId", $controller);
    }

    public function test_tradein_listing_and_documents_do_not_resolve_cross_company_customers(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TradeinController.php'));

        $this->assertStringContainsString('findClienteDaEmpresa', $controller);
        $this->assertStringContainsString("return Cliente::where('empresa_id', \$empresaId)->find(\$clienteId);", $controller);
        $this->assertStringContainsString("Cliente::where('empresa_id', \$request->empresa_id)", $controller);
        $this->assertStringNotContainsString('$tradein->cliente_id ? Cliente::find($tradein->cliente_id) : null', $controller);
    }

    public function test_tradein_inventory_update_scopes_products_and_keeps_company_guards(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TradeinInventoryController.php'));

        $this->assertStringContainsString('use Illuminate\Validation\Rule;', $controller);
        $this->assertStringContainsString("Produto::where('empresa_id', \$request->empresa_id)->find(\$item->produto_id)", $controller);
        $this->assertStringContainsString("Rule::exists('produtos', 'id')->where('empresa_id', (int) \$request->empresa_id)", $controller);
        $this->assertStringContainsString("Cliente::where('empresa_id', \$request->empresa_id)", $controller);
        $this->assertGreaterThanOrEqual(5, substr_count($controller, '__validaObjetoEmpresa($item);'));
    }

    public function test_tradein_routes_keep_permissions_and_safe_creation_surface(): void
    {
        $store = Route::getRoutes()->getByName('tradein.store');
        $inventoryUpdate = Route::getRoutes()->getByName('tradein.inventory.update');
        $frontBoxJs = file_get_contents(public_path('js/frente_caixa.js'));

        $this->assertNotNull($store);
        $this->assertNotNull($inventoryUpdate);
        $this->assertContains('permission:pdv_edit', $store->gatherMiddleware());
        $this->assertContains('permission:tradein_view', $inventoryUpdate->gatherMiddleware());
        $this->assertStringContainsString('path_url + "trade-in/store"', $frontBoxJs);
        $this->assertStringContainsString('Selecione um cliente para criar o trade-in.', $frontBoxJs);
        $this->assertStringContainsString('Selecione um produto', $frontBoxJs);
        $this->assertStringContainsString('IMEI/serial', $frontBoxJs);
    }
}
