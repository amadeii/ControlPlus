<?php

namespace Tests\Feature;

use Tests\TestCase;

class SuperstoreSerialRegressionTest extends TestCase
{
    public function test_purchase_serial_form_only_requires_serial_and_links_purchase_item(): void
    {
        $view = file_get_contents(resource_path('views/compras/set_codigo_unico.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/CompraController.php'));
        $model = file_get_contents(app_path('Models/ProdutoUnico.php'));

        $this->assertStringContainsString('name="codigo[]" required', $view);
        $this->assertStringContainsString('name="observacao[]" class="form-control"', $view);
        $this->assertStringContainsString('name="item_nfe_id[]"', $view);
        $this->assertStringContainsString("'item_nfe_id' => \$itemCompra->id", $controller);
        $this->assertStringContainsString("'deposito_id' => \$depositoId", $controller);
        $this->assertStringContainsString('Serial {$codigo} duplicado nesta compra.', $controller);
        $this->assertStringContainsString('Serial {$codigo} jÃ¡ cadastrado para este produto.', $controller);
        $this->assertStringContainsString("'item_nfe_id'", $model);
    }

    public function test_purchase_stock_and_movement_stay_single_in_nfe_store(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/NfeController.php'));

        $this->assertStringContainsString('$this->util->incrementaEstoque($product->id, __convert_value_bd($request->quantidade[$i]),', $controller);
        $this->assertStringContainsString("\$tipo_transacao = 'compra';", $controller);
        $this->assertSame(1, substr_count($controller, "\$tipo_transacao = 'compra';"));
    }

    public function test_manual_stock_entry_and_exit_validate_serials_and_are_transactional(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/EstoqueController.php'));
        $entryView = file_get_contents(resource_path('views/estoque/_forms.blade.php'));
        $exitView = file_get_contents(resource_path('views/estoque/retirada.blade.php'));

        $this->assertStringContainsString('serialEntradaDuplicado', $controller);
        $this->assertStringContainsString('serialDisponivelParaSaida', $controller);
        $this->assertStringContainsString('Serial inexistente, indisponivel, vendido, reservado ou de outro produto.', $controller);
        $this->assertStringContainsString('Produto serializado deve movimentar quantidade 1 por serial.', $controller);
        $this->assertGreaterThanOrEqual(2, substr_count($controller, 'DB::beginTransaction();'));
        $this->assertGreaterThanOrEqual(2, substr_count($controller, 'DB::commit();'));
        $this->assertGreaterThanOrEqual(2, substr_count($controller, 'DB::rollBack();'));
        $this->assertMatchesRegularExpression("/'tipo'\s*=>\s*'entrada'/", $controller);
        $this->assertMatchesRegularExpression("/'tipo'\s*=>\s*'saida'/", $controller);
        $this->assertStringContainsString("'serial', 'Serial'", $entryView);
        $this->assertStringContainsString("'serial', 'Serial'", $exitView);
    }

    public function test_pdv_serial_endpoint_filters_available_serials_by_product_company_local_and_deposito(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/API/ProdutoController.php'));
        $publicJs = file_get_contents(public_path('js/frente_caixa.js'));
        $sourceJs = file_get_contents(base_path('js/frente_caixa.js'));

        $this->assertStringContainsString("where('produtos.empresa_id', \$request->empresa_id)", $controller);
        $this->assertStringContainsString("where('produto_unicos.tipo', 'entrada')", $controller);
        $this->assertStringContainsString("where('produto_unicos.em_estoque', 1)", $controller);
        $this->assertStringContainsString("where('produto_unicos.status_key', StatusKeyUtil::DEFAULT_STATUS)", $controller);
        $this->assertStringContainsString("where('produto_unicos.produto_id', \$request->produto_id)", $controller);
        $this->assertStringContainsString("where('produto_unicos.local_id', \$localId)", $controller);
        $this->assertStringContainsString("where('produto_unicos.deposito_id', \$depositoId)", $controller);
        $this->assertStringContainsString('local_id: $("#local_id").val()', $publicJs);
        $this->assertStringContainsString('local_id: $("#local_id").val()', $sourceJs);
    }

    public function test_pdv_sale_consumes_serial_once_links_item_and_uses_transaction(): void
    {
        $webController = file_get_contents(app_path('Http/Controllers/API/FrontBoxController.php'));
        $apiController = file_get_contents(app_path('Http/Controllers/API/PDV/VendaController.php'));

        foreach ([$webController, $apiController] as $controller) {
            $this->assertStringContainsString('DB::transaction(function', $controller);
            $this->assertStringContainsString("'item_nfce_id'", $controller);
            $this->assertStringContainsString("'deposito_id' =>", $controller);
            $this->assertMatchesRegularExpression("/'tipo'\s*=>\s*'saida'/", $controller);
            $this->assertMatchesRegularExpression("/'em_estoque'\s*=>\s*0/", $controller);
            $this->assertStringContainsString("StatusKeyUtil::DEFAULT_STATUS", $controller);
        }

        $this->assertStringContainsString('->where(\'em_estoque\', 1)', $apiController);
        $this->assertStringContainsString('$serial->em_estoque = 0;', $apiController);
        $this->assertStringContainsString('!$produtoUnicoId && !$codigoUnico', $apiController);
        $this->assertStringContainsString('$this->util->reduzEstoque($product->id, $item[\'quantidade\'], $produtoVariacaoId, $local_id);', $apiController);
        $this->assertStringContainsString('$this->util->movimentacaoProduto($product->id, $item[\'quantidade\']', $apiController);
    }
}
