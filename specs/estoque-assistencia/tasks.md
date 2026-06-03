# Estoque e Peças

### EST-02
**Objetivo:** endurecer a validação do ajuste manual de estoque da assistência para impedir depósito/local inválidos, produto serializado indevido e duplicidade de ajuste.  
**Domínio:** estoque e peças.  
**Arquivos prováveis:** [app/Http/Controllers/AssistenciaEstoqueAjusteManualController.php](../../app/Http/Controllers/AssistenciaEstoqueAjusteManualController.php), [app/Http/Requests/AssistenciaEstoqueAjusteManualStoreRequest.php](../../app/Http/Requests/AssistenciaEstoqueAjusteManualStoreRequest.php), [app/Services/AssistenciaEstoqueAjusteManualService.php](../../app/Services/AssistenciaEstoqueAjusteManualService.php), [routes/web.php](../../routes/web.php).  
**Risco:** médio.  
**Dependências:** `AssistenciaOsEstoqueService::integraEstoqueParaEmpresa`, resolução de depósitos e locais ativos do usuário.  
**Critérios de aceite:** produto, depósito e local passam por validação antes de tocar estoque; produto serializado é recusado; o ajuste grava uma única movimentação auditável.  
**Validação manual:** testar depósito inválido, local inválido, produto serializado e um ajuste válido em sequência.  
**Rollback esperado:** voltar para a validação inline atual do controller.  
**Impacto operacional:** médio, porque evita baixa indevida de peças na assistência.

### EST-01A
**Objetivo:** tornar a baixa de peças da assistência idempotente e transacional, garantindo que a inclusão da linha gere só uma movimentação de consumo.  
**Domínio:** estoque e peças.  
**Arquivos prováveis:** [app/Services/AssistenciaOsEstoqueService.php](../../app/Services/AssistenciaOsEstoqueService.php), [app/Utils/EstoqueUtil.php](../../app/Utils/EstoqueUtil.php), [app/Models/MovimentacaoProduto.php](../../app/Models/MovimentacaoProduto.php).  
**Risco:** alto.  
**Dependências:** `EST-02`; enum de `movimentacao_produtos` já precisa aceitar `os_consumo_peca`; depósito/local do item devem estar resolvidos antes da gravação.  
**Critérios de aceite:** uma peça inserida na OS gera uma única baixa; reprocessamento não duplica movimentação; o saldo cai exatamente uma vez.  
**Validação manual:** adicionar a peça na OS duas vezes por retry e confirmar que a baixa ocorre apenas uma vez.  
**Rollback esperado:** retornar à baixa direta atual no service.  
**Impacto operacional:** alto, limitado ao consumo de peças na assistência.

### EST-01B
**Objetivo:** tornar o estorno de peças da assistência idempotente, garantindo reversão única ao remover linha ou excluir a OS.  
**Domínio:** estoque e peças.  
**Arquivos prováveis:** [app/Services/AssistenciaOsEstoqueService.php](../../app/Services/AssistenciaOsEstoqueService.php), [app/Utils/EstoqueUtil.php](../../app/Utils/EstoqueUtil.php), [app/Models/MovimentacaoProduto.php](../../app/Models/MovimentacaoProduto.php), [app/Services/TradeinAssistenciaPecaCustoService.php](../../app/Services/TradeinAssistenciaPecaCustoService.php).  
**Risco:** alto.  
**Dependências:** `EST-01A`; a movimentação de consumo correspondente já deve existir para permitir o retorno.  
**Critérios de aceite:** o mesmo item removido não estorna mais de uma vez; o saldo volta exatamente ao estado anterior; o custo agregado ligado à peça também é revertido uma única vez.  
**Validação manual:** excluir a mesma linha da OS duas vezes em sequência e confirmar que só há um estorno.  
**Rollback esperado:** voltar ao estorno direto atual no service.  
**Impacto operacional:** alto, limitado à reversão de peças na assistência.
