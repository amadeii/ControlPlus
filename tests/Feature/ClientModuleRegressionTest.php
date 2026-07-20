<?php

namespace Tests\Feature;

use App\Rules\ValidaDocumentoCliente;
use Tests\TestCase;

class ClientModuleRegressionTest extends TestCase
{
    public function test_client_document_rule_validates_cpf_and_cnpj_digits(): void
    {
        $this->assertTrue(ValidaDocumentoCliente::documentoValido('52998224725'));
        $this->assertTrue(ValidaDocumentoCliente::documentoValido('11222333000181'));
        $this->assertFalse(ValidaDocumentoCliente::documentoValido('11111111111'));
        $this->assertFalse(ValidaDocumentoCliente::documentoValido('11111111111111'));
        $this->assertFalse(ValidaDocumentoCliente::documentoValido('123'));
    }

    public function test_client_store_and_update_use_document_validation_and_preserve_status_zero(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ClienteController.php'));
        $form = file_get_contents(resource_path('views/clientes/_forms.blade.php'));

        $this->assertStringContainsString('$this->__validate($request);', $controller);
        $this->assertStringContainsString("'cpf_cnpj' => [ 'required', new ValidaDocumentoCliente(\$request->empresa_id, \$id) ],", $controller);
        $this->assertStringContainsString("\$data['status'] = \$request->has('status') ? (int) \$request->input('status') : 1;", $controller);
        $this->assertStringContainsString("\$data['status'] = \$request->has('status') ? (int) \$request->input('status') : (int) \$item->status;", $controller);
        $this->assertStringContainsString("->value(old('status', isset(\$item) ? (string) \$item->status : '1'))", $form);
    }

    public function test_client_duplicate_check_uses_sanitized_document_and_ignores_current_record(): void
    {
        $rule = file_get_contents(app_path('Rules/ValidaDocumentoCliente.php'));

        $this->assertStringContainsString('preg_replace(\'/\D/\', \'\', (string) $value)', $rule);
        $this->assertStringContainsString("where('empresa_id', \$this->empresa_id)", $rule);
        $this->assertStringContainsString("where('id', '<>', \$this->ignoreId)", $rule);
        $this->assertStringContainsString("REPLACE(REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') = ?", $rule);
    }

    public function test_client_delete_confirmation_is_delegated_and_company_scope_is_checked(): void
    {
        $mainJs = file_get_contents(public_path('js/main.js'));
        $controller = file_get_contents(app_path('Http/Controllers/ClienteController.php'));

        $this->assertStringContainsString('$(document).on("click", ".btn-delete"', $mainJs);
        $this->assertGreaterThanOrEqual(3, substr_count($controller, '__validaObjetoEmpresa($item);'));
        $this->assertStringContainsString("\$itens = \$request->input('item_delete', []);", $controller);
    }

    public function test_client_cnpj_autofill_does_not_overwrite_manual_data(): void
    {
        $form = file_get_contents(resource_path('views/clientes/_forms.blade.php'));

        $this->assertStringContainsString('setCnpjValueIfEmpty', $form);
        $this->assertStringContainsString('hasManualData', $form);
        $this->assertStringContainsString('cpf_cnpj.length == 14 && !hasManualData', $form);
        $this->assertStringContainsString("if(!\$('#inp-cidade_id').val())", $form);
    }
}
