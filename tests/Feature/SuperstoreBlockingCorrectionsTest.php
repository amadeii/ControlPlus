<?php

namespace Tests\Feature;

use App\Models\NaturezaOperacao;
use App\Models\Permission;
use App\Http\Controllers\API\FrontBoxController as ApiFrontBoxController;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SuperstoreBlockingCorrectionsTest extends TestCase
{
    public function test_tradein_and_pdv_permissions_are_declared_for_rbac_sync(): void
    {
        $permissions = collect(Permission::defaultPermissions())->pluck('name')->all();

        $this->assertContains('pdv_edit', $permissions);
        $this->assertContains('tradein_view', $permissions);
        $this->assertContains('tradein_edit', $permissions);
        $this->assertContains('tradein_delete', $permissions);
    }

    public function test_natureza_operacao_has_operation_type_and_flows_filter_by_direction(): void
    {
        $model = file_get_contents(app_path('Models/NaturezaOperacao.php'));
        $compraController = file_get_contents(app_path('Http/Controllers/CompraController.php'));
        $nfeController = file_get_contents(app_path('Http/Controllers/NfeController.php'));
        $migration = file_get_contents(database_path('migrations/2026_10_06_000001_add_tipo_operacao_to_natureza_operacaos_table.php'));

        $this->assertStringContainsString("'tipo_operacao'", $model);
        $this->assertSame(['entrada', 'saida', 'ambos'], array_keys(NaturezaOperacao::tiposOperacao()));
        $this->assertStringContainsString('->entrada()', $compraController);
        $this->assertStringContainsString('->saida()', $nfeController);
        $this->assertStringContainsString('Compra para comercialização', $migration);
    }

    public function test_credit_card_installments_are_collected_validated_and_persisted(): void
    {
        $modal = file_get_contents(resource_path('views/modals/_cartao_credito.blade.php'));
        $multipleModal = file_get_contents(resource_path('views/modals/_pagamento_multiplo.blade.php'));
        $row = file_get_contents(resource_path('views/front_box/partials/row_pagamento_multiplo.blade.php'));
        $js = file_get_contents(public_path('js/frente_caixa.js'));
        $controller = file_get_contents(app_path('Http/Controllers/API/FrontBoxController.php'));
        $model = file_get_contents(app_path('Models/FaturaNfce.php'));

        $this->assertStringContainsString("Form::number('parcelas_cartao'", $modal);
        $this->assertStringContainsString("Form::number('parcelas_cartao_row_input'", $multipleModal);
        $this->assertStringContainsString('name="parcelas_cartao_row[]"', $row);
        $this->assertStringContainsString('json.parcelas_cartao', $js);
        $this->assertStringContainsString('resolveParcelasCartao', $controller);
        $this->assertStringContainsString('parcelasCartaoCredito', $controller);
        $this->assertStringContainsString("'total_parcelas'", $model);
    }

    public function test_multiple_payment_backend_requires_exact_sale_total(): void
    {
        $controller = (new ReflectionClass(ApiFrontBoxController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(ApiFrontBoxController::class, 'validateMultiplePaymentAmountAgainstSale');
        $method->setAccessible(true);

        $valid = Request::create('/', 'POST', [
            'tipo_pagamento_row' => ['01', '01'],
            'valor_integral_row' => ['50,00', '50,00'],
            'valor_total' => '100,00',
        ]);
        $this->assertNull($method->invoke($controller, $valid));

        $validCentAmounts = Request::create('/', 'POST', [
            'tipo_pagamento_row' => ['01', '01'],
            'valor_integral_row' => ['50,00', '49,99'],
            'valor_total' => '99,99',
        ]);
        $this->assertNull($method->invoke($controller, $validCentAmounts));

        $shortByOneCent = Request::create('/', 'POST', [
            'tipo_pagamento_row' => ['01', '01'],
            'valor_integral_row' => ['50,00', '49,99'],
            'valor_total' => '100,00',
        ]);

        try {
            $method->invoke($controller, $shortByOneCent);
            $this->fail('Uma diferença de um centavo deve impedir a venda.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }
}
