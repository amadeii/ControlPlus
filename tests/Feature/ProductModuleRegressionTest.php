<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductModuleRegressionTest extends TestCase
{
    public function test_product_index_search_includes_sku_and_all_barcodes(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ProdutoController.php'));

        $this->assertStringContainsString("->orWhere('sku', 'LIKE', \"%{\$nome}%\")", $controller);
        $this->assertStringContainsString("->orWhere('codigo_barras2', 'LIKE', \"%{\$nome}%\")", $controller);
        $this->assertStringContainsString("->orWhere('codigo_barras3', 'LIKE', \"%{\$nome}%\")", $controller);
        $this->assertStringContainsString("->orWhere('codigo_barras2', 'LIKE', \"%{\$codigoBarras}%\")", $controller);
        $this->assertStringContainsString("->orWhere('codigo_barras3', 'LIKE', \"%{\$codigoBarras}%\")", $controller);
    }

    public function test_product_filters_submit_to_clean_index_route(): void
    {
        $view = file_get_contents(resource_path('views/produtos/index.blade.php'));

        $this->assertStringContainsString("->route('produtos.index')", $view);
        $this->assertStringContainsString("->get()->attrs(['class' => 'filtros-container'])", $view);
    }

    public function test_product_delete_buttons_are_non_submit_until_confirmation(): void
    {
        $table = file_get_contents(resource_path('views/produtos/partials/tabela.blade.php'));
        $card = file_get_contents(resource_path('views/produtos/partials/card.blade.php'));
        $mainJs = file_get_contents(public_path('js/main.js'));

        $this->assertStringContainsString('type="button" class="dropdown-item text-danger btn-delete"', $table);
        $this->assertStringContainsString('type="button" class="btn btn-delete btn-sm btn-danger', $card);
        $this->assertStringContainsString('swal({', $mainJs);
        $this->assertStringContainsString('document.getElementById(form).submit();', $mainJs);
    }

    public function test_product_money_fields_select_current_value_before_typing(): void
    {
        $productJs = file_get_contents(public_path('js/produto.js'));

        $this->assertStringContainsString('#inp-valor_compra, #inp-valor_unitario, #inp-valor_minimo_venda, #inp-valor_prazo', $productJs);
        $this->assertStringContainsString('this.select();', $productJs);
        $this->assertStringContainsString('currentValue === "0,00"', $productJs);
    }

    public function test_product_update_preserves_zero_status_values(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ProdutoController.php'));
        $form = file_get_contents(resource_path('views/produtos/_forms.blade.php'));

        $this->assertStringContainsString("\$item->status = \$request->has('status') ? (int) \$request->input('status') : (int) \$item->status;", $controller);
        $this->assertStringContainsString("\$item->gerenciar_estoque = \$request->has('gerenciar_estoque') ? (int) \$request->input('gerenciar_estoque') : (int) \$item->gerenciar_estoque;", $controller);
        $this->assertStringContainsString("->value(old('status', isset(\$item) ? (string) \$item->status : '1'))", $form);
    }
}
