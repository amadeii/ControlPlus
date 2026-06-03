# Auditoria Operacional

### AUD-02
**Objetivo:** manter a tela de auditoria operacional consistente entre as abas de ação, estoque e OS, com filtros e paginação preservados.  
**Domínio:** auditoria operacional.  
**Arquivos prováveis:** [app/Http/Controllers/AuditoriaOperacionalController.php](../../app/Http/Controllers/AuditoriaOperacionalController.php), [resources/views/auditoria_operacional/index.blade.php](../../resources/views/auditoria_operacional/index.blade.php), [routes/web.php](../../routes/web.php), [app/Models/AcaoLog.php](../../app/Models/AcaoLog.php), [app/Models/AuditEstoqueDetalhe.php](../../app/Models/AuditEstoqueDetalhe.php).  
**Risco:** baixo a médio.  
**Dependências:** permissão `logs_view` e dados válidos nas tabelas `acao_logs`, `audit_estoque_detalhes` e `audit_ordem_servico_alteracoes`.  
**Critérios de aceite:** cada aba carrega sua fonte correta; filtros permanecem ao paginar; o histórico não mistura tipos de auditoria.  
**Validação manual:** abrir cada aba, filtrar por data/ação/local e navegar pelas páginas conferindo a separação dos dados.  
**Rollback esperado:** retornar à query única e ao comportamento atual da view.  
**Impacto operacional:** baixo, restrito ao back-office de auditoria.

### AUD-01
**Objetivo:** completar a trilha de auditoria da OS com snapshot, diff e contexto de requisição antes de exclusões e ao salvar alterações.  
**Domínio:** auditoria operacional.  
**Arquivos prováveis:** [app/Services/OrdemServicoAuditoriaAlteracaoLogger.php](../../app/Services/OrdemServicoAuditoriaAlteracaoLogger.php), [app/Support/AuditRequestContext.php](../../app/Support/AuditRequestContext.php), [app/Http/Controllers/OrdemServicoController.php](../../app/Http/Controllers/OrdemServicoController.php), [app/Models/AuditOrdemServicoAlteracao.php](../../app/Models/AuditOrdemServicoAlteracao.php), [database/migrations/2026_05_07_180100_auditoria_avancada.php](../../database/migrations/2026_05_07_180100_auditoria_avancada.php).  
**Risco:** médio.  
**Dependências:** `AUD-02` não é bloqueante; a tabela de auditoria da OS e os snapshots atuais do controller precisam estar disponíveis.  
**Critérios de aceite:** alterações e exclusões de OS persistem snapshot completo com usuário/IP/session; exclusão grava a trilha antes do `delete`; sem diff não deve haver linha de auditoria vazia.  
**Validação manual:** editar uma OS e excluir outra, depois conferir a tabela de auditoria e os metadados capturados.  
**Rollback esperado:** suspender a gravação de auditoria nova e manter apenas o `__createLog` legado.  
**Impacto operacional:** médio, com ganho de rastreabilidade e baixo impacto no uso diário.
