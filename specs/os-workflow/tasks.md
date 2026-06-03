# Workflow de OS

### OS-WF-02
**Objetivo:** reforçar validação explícita das transições e dos campos de estado, mantendo compatibilidade com os payloads antigos da tela de OS.  
**Domínio:** workflow de OS.  
**Arquivos prováveis:** [app/Http/Requests/OrdemServico/UpdateEstadoRequest.php](../../app/Http/Requests/OrdemServico/UpdateEstadoRequest.php), [app/Http/Controllers/OrdemServicoController.php](../../app/Http/Controllers/OrdemServicoController.php), [app/Models/OrdemServico.php](../../app/Models/OrdemServico.php), [routes/web.php](../../routes/web.php).  
**Risco:** baixo a médio.  
**Dependências:** enumeração de estados da [OrdemServico.php](../../app/Models/OrdemServico.php) e payload legado de `faturar`, `tipo_pagamento`, `data_vencimento` e `valor_fatura`.  
**Critérios de aceite:** estado inválido é rejeitado com mensagem clara; campos opcionais seguem opcionais; formulários antigos continuam submetendo sem quebra.  
**Validação manual:** testar estado inválido, finalização sem campos opcionais e submit de formulário legado sem alterações.  
**Rollback esperado:** voltar para validação inline no controller.  
**Impacto operacional:** baixo, restrito à validação da tela.

### OS-WF-01A
**Objetivo:** isolar o fechamento da OS em uma transação única com `lockForUpdate`, evitando corrida e reexecução dupla no ato de mudar o estado final.  
**Domínio:** workflow de OS.  
**Arquivos prováveis:** [app/Http/Controllers/OrdemServicoController.php](../../app/Http/Controllers/OrdemServicoController.php), [app/Services/OrdemServicoWorkflowService.php](../../app/Services/OrdemServicoWorkflowService.php), [app/Models/OrdemServico.php](../../app/Models/OrdemServico.php).  
**Risco:** médio.  
**Dependências:** `OS-WF-02`; OS já precisa estar no conjunto de estados permitido e respeitar a regra de caixa aberto quando aplicável.  
**Critérios de aceite:** uma OS é fechada uma única vez; reenvio do mesmo submit não duplica a mudança de estado; o estado persistido permanece consistente após concorrência.  
**Validação manual:** fechar uma OS, repetir o submit imediatamente e confirmar que o estado final não muda de novo.  
**Rollback esperado:** retornar o fechamento para a lógica inline atual no controller.  
**Impacto operacional:** médio, com efeito direto apenas no fechamento da OS.

### OS-WF-01B
**Objetivo:** separar os efeitos colaterais do fechamento da OS para que logs, eventos de assistência e vínculos operacionais não sejam duplicados em retry.  
**Domínio:** workflow de OS.  
**Arquivos prováveis:** [app/Http/Controllers/OrdemServicoController.php](../../app/Http/Controllers/OrdemServicoController.php), [app/Services/AssistenciaOsControleTecnicoService.php](../../app/Services/AssistenciaOsControleTecnicoService.php), [app/Services/OrdemServicoAuditoriaAlteracaoLogger.php](../../app/Services/OrdemServicoAuditoriaAlteracaoLogger.php).  
**Risco:** médio.  
**Dependências:** `OS-WF-01A`; fechamento já deve estar protegido por transação e o fluxo de assistência técnica precisa continuar aceitando o legado.  
**Critérios de aceite:** o fechamento repetido não duplica logs, eventos de assistência nem vínculos derivados; a trilha operacional permanece legível.  
**Validação manual:** fechar a mesma OS duas vezes em sequência e verificar ausência de duplicidade nos eventos e logs.  
**Rollback esperado:** recolocar os efeitos colaterais no fluxo inline atual.  
**Impacto operacional:** médio, restrito aos efeitos do fechamento.
