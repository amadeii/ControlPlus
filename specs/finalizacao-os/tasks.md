# Finalização Financeira

### FIN-02
**Objetivo:** reforçar validação e bloqueio de reexecução na finalização financeira, cobrindo arrays de parcelas, estado permitido e caixa aberto.  
**Domínio:** finalização financeira.  
**Arquivos prováveis:** [app/Http/Requests/OrdemServico/FinalizeFinanceiroRequest.php](../../app/Http/Requests/OrdemServico/FinalizeFinanceiroRequest.php), [app/Http/Controllers/OrdemServicoController.php](../../app/Http/Controllers/OrdemServicoController.php), [routes/web.php](../../routes/web.php).  
**Risco:** médio a alto.  
**Dependências:** `FIN-01A`, `FIN-01B` e a regra atual de caixa aberto; payload legado da tela de finalização.  
**Critérios de aceite:** submit sem caixa aberto é bloqueado; arrays inconsistentes de pagamento são rejeitados; uma segunda submissão não cria novos registros.  
**Validação manual:** testar sem caixa, com arrays de pagamento desalinhados e com segundo submit após sucesso.  
**Rollback esperado:** voltar à validação inline existente.  
**Impacto operacional:** médio a alto, com foco em reduzir erro humano e duplicidade de cobrança.

### FIN-01A
**Objetivo:** persistir as faturas da OS finalizada em uma transação única, sem alterar o formato legado de parcelas.  
**Domínio:** finalização financeira.  
**Arquivos prováveis:** [app/Http/Controllers/OrdemServicoController.php](../../app/Http/Controllers/OrdemServicoController.php), [app/Services/OrdemServicoFinanceiroService.php](../../app/Services/OrdemServicoFinanceiroService.php), [app/Models/FaturaOrdemServico.php](../../app/Models/FaturaOrdemServico.php).  
**Risco:** alto.  
**Dependências:** `FIN-02`; OS já finalizada em `fz`; caixa aberto quando a finalização exigir caixa.  
**Critérios de aceite:** cada parcela gera uma fatura correta; a quantidade de faturas bate com o payload; o fluxo continua compatível com o legado.  
**Validação manual:** finalizar uma OS com parcelas e conferir se as faturas foram criadas uma vez cada.  
**Rollback esperado:** voltar à criação inline das faturas no controller.  
**Impacto operacional:** alto, porque afeta o registro base da finalização.

### FIN-01B
**Objetivo:** persistir as `ContaReceber` derivadas da finalização da OS sem duplicar lançamentos quando o submit for reenviado.  
**Domínio:** finalização financeira.  
**Arquivos prováveis:** [app/Http/Controllers/OrdemServicoController.php](../../app/Http/Controllers/OrdemServicoController.php), [app/Services/OrdemServicoFinanceiroService.php](../../app/Services/OrdemServicoFinanceiroService.php), [app/Models/ContaReceber.php](../../app/Models/ContaReceber.php).  
**Risco:** alto.  
**Dependências:** `FIN-01A`; formato das faturas já definido; compatibilidade com `ContaReceber::firstOrCreate`.  
**Critérios de aceite:** uma parcela financeira gera uma conta coerente; reprocessar o submit não cria contas duplicadas; o vínculo com a OS permanece correto.  
**Validação manual:** reenviar a finalização e verificar que a conta a receber não duplica.  
**Rollback esperado:** voltar à criação inline atual das contas no controller.  
**Impacto operacional:** alto, por tocar diretamente o contas a receber.

### FIN-01C
**Objetivo:** definir a camada de idempotência em aplicação para a finalização financeira, deixando explícito o identificador de dedupe que será usado antes de qualquer proteção em banco.  
**Domínio:** finalização financeira.  
**Arquivos prováveis:** [app/Http/Controllers/OrdemServicoController.php](../../app/Http/Controllers/OrdemServicoController.php), [app/Services/OrdemServicoFinanceiroService.php](../../app/Services/OrdemServicoFinanceiroService.php), [app/Models/FaturaOrdemServico.php](../../app/Models/FaturaOrdemServico.php), [app/Models/ContaReceber.php](../../app/Models/ContaReceber.php).  
**Risco:** médio.  
**Dependências:** `FIN-01A` e `FIN-01B`; formato final do identificador idempotente precisa ficar estável antes de qualquer constraint de banco.  
**Critérios de aceite:** o fluxo possui uma chave de dedupe clara e reutilizável; submit repetido não avança para criação de novos registros; o formato fica pronto para proteção futura em banco.  
**Validação manual:** repetir a submissão da mesma finalização e confirmar que o guard de idempotência bloqueia a segunda execução.  
**Rollback esperado:** remover a guarda de idempotência em aplicação e voltar ao fluxo atual.  
**Impacto operacional:** alto, pois reduz risco de duplicidade antes da proteção física.

### FIN-03
**Objetivo:** adicionar proteção em banco para impedir que a mesma finalização financeira da OS seja persistida duas vezes em corrida ou retry.  
**Domínio:** finalização financeira.  
**Arquivos prováveis:** [database/migrations/2026_01_22_110521_create_fatura_ordem_servicos_table.php](../../database/migrations/2026_01_22_110521_create_fatura_ordem_servicos_table.php), [database/migrations/2026_01_22_110521_create_conta_recebers_table.php](../../database/migrations/2026_01_22_110521_create_conta_recebers_table.php).  
**Risco:** médio.  
**Dependências:** `FIN-01C`; o formato final de idempotência precisa estar fechado antes de definir qualquer índice ou constraint.  
**Critérios de aceite:** a proteção em banco reflete a chave final definida pela idempotência em aplicação; dados históricos seguem legíveis; a aplicação trata a violação sem quebrar a tela.  
**Validação manual:** em ambiente de teste, forçar reexecução e confirmar que a segunda persistência é rejeitada ou absorvida.  
**Rollback esperado:** remover a constraint/índice novo e manter só a proteção em aplicação.  
**Impacto operacional:** alto, como última linha de defesa contra cobrança dupla.

**Status:** adiada até `FIN-01C` fechar o formato final de idempotência.
