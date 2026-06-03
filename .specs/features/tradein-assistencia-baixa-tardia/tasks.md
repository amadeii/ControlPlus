# Trade-In + Assistencia Com Baixa Tardia Tasks

**Spec**: `.specs/features/tradein-assistencia-baixa-tardia/spec.md`
**Design**: `.specs/features/tradein-assistencia-baixa-tardia/design.md`
**Status**: In Progress

---

## Execution Plan

### Phase 1: Foundation (Sequential)

```text
T1 -> T2
```

### Phase 2: OS Behavior (Sequential)

```text
T2 -> T3 -> T4
```

### Phase 3: Commit Flow (Sequential)

```text
T4 -> T5 -> T6
```

### Phase 4: Trade-In Connection and UI (Sequential)

```text
T6 -> T7 -> T8 -> T9
```

### Phase 5: Validation (Sequential)

```text
T9 -> T10
```

---

## Task Breakdown

### T1: Criar estrutura de pendencia de baixa
**Status**: Complete

**Objetivo**: Criar tabela/model para registrar pecas apontadas sem baixa fisica imediata.

**Arquivos provaveis**:

- `database/migrations/*_create_assistencia_os_peca_baixas_table.php`
- `app/Models/AssistenciaOsPecaBaixa.php`

**Depends on**: None
**Requirement**: TIAS-01, TIAS-04
**Risco**: Baixo

**Validacao esperada**:

- Migration cria tabela `assistencia_os_peca_baixas`.
- Existe unique em `produto_os_id`.
- Nenhum controller usa a tabela ainda.

**Criterio de pronto**:

- Artefatos de persistencia criados.
- Nenhum comportamento funcional alterado.
- OS normal permanece com baixa imediata.

**Tests**: migration/model smoke via comandos locais disponiveis.
**Gate**: sintaxe PHP/migration quando aplicavel.

---

### T2: Criar service de pendencia sem estoque
**Status**: Complete

**Objetivo**: Encapsular criacao, consulta e cancelamento de pendencias sem chamar baixa/estorno fisico.

**Arquivos provaveis**:

- `app/Services/AssistenciaOsPecaBaixaPendenteService.php`
- `app/Models/AssistenciaOsPecaBaixa.php`

**Depends on**: T1
**Requirement**: TIAS-01, TIAS-02, TIAS-04
**Risco**: Baixo

**Validacao esperada**:

- `criarPendente` e idempotente por `produto_os_id`.
- `cancelarPendente` atua somente em status `pendente`.
- Nenhum registro em `movimentacao_produtos` e criado.

**Criterio de pronto**:

- Service implementado de forma isolada.
- Nao altera `AssistenciaOsEstoqueService`.
- Nao altera `TradeinAssistenciaPecaCustoService`.

**Tests**: smoke/sintaxe e validacao manual controlada.
**Gate**: sintaxe PHP.

---

### T3: Adiar baixa ao adicionar peca em OS interna Trade-In
**Status**: Complete

**Objetivo**: Alterar somente o caminho de `storeProduto` para criar pendencia em OS interna vinculada a Trade-In.

**Arquivos provaveis**:

- `app/Http/Controllers/OrdemServicoController.php`
- `app/Services/AssistenciaOsPecaBaixaPendenteService.php`

**Depends on**: T2
**Requirement**: TIAS-01, TIAS-10
**Risco**: Medio

**Validacao esperada**:

- OS normal continua chamando `AssistenciaOsEstoqueService::aplicarBaixa`.
- OS interna sem Trade-In continua no comportamento atual.
- OS interna com `tradein_inventory_item_id` cria pendencia e nao cria `os_consumo_peca`.

**Criterio de pronto**:

- Condicional restrita a `isOsInterna() && tradein_inventory_item_id`.
- Nenhuma baixa fisica ocorre nesse caminho novo.
- Mensagem ao usuario nao promete estoque baixado quando apenas apontou pendencia.

**Tests**: validacao manual comparando OS normal vs OS interna Trade-In.
**Gate**: sintaxe PHP e teste funcional manual.

---

### T4: Tratar remocao de peca pendente
**Status**: Complete

**Objetivo**: Cancelar/remover pendencia sem estorno fisico quando a peca ainda nao foi baixada.

**Arquivos provaveis**:

- `app/Http/Controllers/OrdemServicoController.php`
- `app/Services/AssistenciaOsPecaBaixaPendenteService.php`

**Depends on**: T3
**Requirement**: TIAS-02, TIAS-10
**Risco**: Medio

**Validacao esperada**:

- Peca pendente em OS interna Trade-In pode ser removida sem `os_estorno_peca`.
- Peca ja baixada no novo fluxo e bloqueada para remocao neste incremento.
- OS normal mantem estorno atual.

**Criterio de pronto**:

- Remocao antes do commit nao altera estoque.
- Remocao apos commit nao gera estorno parcial nao desenhado.
- Legado preservado fora da condicional.

**Tests**: validacao manual de remocao antes do commit e OS normal.
**Gate**: sintaxe PHP e teste funcional manual.

---

### T5: Criar service de commit administrativo
**Status**: Complete

**Objetivo**: Baixar pecas pendentes, registrar movimentacao/custo e ativar venda em transacao unica.

**Arquivos provaveis**:

- `app/Services/TradeinAssistenciaFinalizacaoService.php`
- `app/Models/AssistenciaOsPecaBaixa.php`
- `app/Services/AssistenciaOsEstoqueService.php` (somente reuso, evitar alteracao global)
- `app/Services/TradeinAssistenciaPecaCustoService.php` (somente reuso, evitar alteracao global)

**Depends on**: T4
**Requirement**: TIAS-03, TIAS-04, TIAS-05, TIAS-06
**Risco**: Medio/alto

**Validacao esperada**:

- Usa `DB::transaction`.
- Aplica locks nos registros centrais.
- Para cada pendencia, chama baixa real uma vez.
- Registra custo agregado uma vez.
- Marca pendencia como `baixado`.
- Atualiza `tradein_inventory_items.status = transferred`.
- Libera status operacional do aparelho para `ATIVO`.
- Nao altera `ordem_servicos.estado` para `fz` automaticamente.

**Criterio de pronto**:

- Falha parcial gera rollback.
- Reexecucao nao duplica baixa/custo.
- Commit so aceita OS interna vinculada a Trade-In e em estado permitido.

**Tests**: validacao manual transacional e reexecucao/idempotencia.
**Gate**: sintaxe PHP e teste funcional manual.

---

### T6: Expor acao administrativa de aprovacao pos-reparo
**Status**: Implemented / Pending Review

**Objetivo**: Criar rota/controller para acionar o commit administrativo.

**Arquivos provaveis**:

- `routes/web.php`
- `app/Http/Controllers/TradeinInventoryController.php`
- `resources/views/tradein/inventory.blade.php`

**Depends on**: T5
**Requirement**: TIAS-03, TIAS-06
**Risco**: Medio

**Validacao esperada**:

- Acao aparece apenas para `tradein_inventory_items.status = em_assistencia`.
- Acao exige OS interna vinculada e `estado = ap`.
- Duplo clique/reenvio nao duplica commit.

**Criterio de pronto**:

- Usuario administrativo consegue aprovar para venda.
- Mensagens de sucesso/erro sao claras.
- Nenhuma rota de deploy/commit/git e executada.

**Tests**: validacao manual via tela e POST repetido.
**Gate**: sintaxe PHP/Blade e teste funcional manual.

---

### T7: Conectar Trade-In ao envio para OS interna
**Status**: Implemented / Pending Review

**Objetivo**: Permitir que o inventario Trade-In envie o item para assistencia usando OS interna existente.

**Arquivos provaveis**:

- `app/Http/Controllers/TradeinInventoryController.php`
- `app/Http/Controllers/OrdemServicoController.php`
- `routes/web.php`
- `resources/views/tradein/inventory.blade.php`

**Depends on**: T6
**Requirement**: TIAS-07, TIAS-08
**Risco**: Medio

**Validacao esperada**:

- Item `pending_transfer` pode ser enviado para assistencia.
- Status vira `em_assistencia`.
- Duplicidade de OS interna ativa para o mesmo Trade-In e bloqueada.
- Fluxo usa `OrdemServico`, nao `ReparoInterno`.

**Criterio de pronto**:

- Trade-In fica rastreavel na OS interna.
- OS normal nao e afetada.
- Nenhum novo fluxo de assistencia e criado.

**Tests**: validacao manual de envio e bloqueio de duplicidade.
**Gate**: sintaxe PHP/Blade e teste funcional manual.

---

### T8: Exibir status de pendencia/baixa na OS
**Status**: Implemented / Pending Review

**Objetivo**: Mostrar na OS interna Trade-In se cada peca esta pendente ou baixada.

**Arquivos provaveis**:

- `resources/views/ordem_servico/show.blade.php`
- `app/Http/Controllers/OrdemServicoController.php` (se precisar eager load)

**Depends on**: T7
**Requirement**: TIAS-09
**Risco**: Baixo

**Validacao esperada**:

- Pecas pendentes aparecem como pendentes.
- Pecas baixadas aparecem como baixadas.
- OS normal preserva visual atual ou recebe ajuste minimo sem mudar comportamento.

**Criterio de pronto**:

- Tela informa claramente que estoque ainda nao foi baixado antes do commit.
- Nao ha alteracao operacional na renderizacao.

**Tests**: validacao visual manual.
**Gate**: sintaxe Blade.

---

### T9: Revisar relatorios e historico de custo
**Status**: Implemented / Pending Review

**Objetivo**: Confirmar que relatorios continuam considerando apenas baixas reais e que historico Trade-In reflete o commit.

**Arquivos provaveis**:

- `app/Http/Controllers/RelatorioController.php` (preferencialmente sem alteracao)
- `resources/views/tradein/inventory_edit.blade.php` (se precisar exibir novo estado)

**Depends on**: T8
**Requirement**: TIAS-05, TIAS-10
**Risco**: Baixo/medio

**Validacao esperada**:

- Relatorio de pecas por OS continua baseado em `os_consumo_peca`.
- Peca pendente nao aparece como consumida.
- Apos commit, peca aparece nos relatorios existentes.

**Criterio de pronto**:

- Sem mudanca desnecessaria em relatorios.
- Se houver ajuste, deve ser apenas apresentacional ou para expor estado novo.

**Tests**: validacao manual de relatorio antes/depois do commit.
**Gate**: teste funcional manual.

---

### T10: Validacao de regressao e revisao final
**Status**: Pending Validation

**Objetivo**: Validar ponta a ponta o fluxo novo e os caminhos legados antes de qualquer commit.

**Arquivos provaveis**:

- Nenhum obrigatorio.
- Possivel anotacao em resumo de validacao se solicitado.

**Depends on**: T9
**Requirement**: TIAS-01, TIAS-02, TIAS-03, TIAS-04, TIAS-05, TIAS-06, TIAS-07, TIAS-08, TIAS-09, TIAS-10
**Risco**: Baixo

**Validacao esperada**:

- OS normal + peca: baixa imediata.
- OS normal + remover peca: estorna.
- OS interna Trade-In + peca: cria pendencia, sem baixa.
- OS interna Trade-In + remover pendente: cancela/remover sem estorno.
- Commit administrativo: baixa, custo, ativacao e status `transferred`.
- Commit repetido: nao duplica.
- PDV/site so vendem com status operacional `ATIVO`.

**Criterio de pronto**:

- `git diff --name-only` revisado.
- `git diff --stat` revisado.
- Riscos residuais documentados.
- Commit nao executado automaticamente.

**Tests**: suite disponivel do projeto quando viavel + validacao manual do fluxo.
**Gate**: revisao final.

---

## Pre-Approval Checks

### Check 1: Task Granularity

| Task | Atomic? | Notes |
| ---- | ------- | ----- |
| T1 | Yes | Apenas persistencia/model. |
| T2 | Yes | Apenas service de pendencia. |
| T3 | Yes | Apenas inclusao de peca. |
| T4 | Yes | Apenas remocao de peca. |
| T5 | Yes | Apenas service de commit. |
| T6 | Yes | Apenas acao administrativa de aprovacao. |
| T7 | Yes | Apenas envio para OS interna. |
| T8 | Yes | Apenas UI da OS. |
| T9 | Yes | Apenas revisao/ajuste de relatorios/historico. |
| T10 | Yes | Apenas validacao e revisao final. |

### Check 2: Diagram-Definition Cross-Check

| Edge | Covered By | Status |
| ---- | ---------- | ------ |
| Trade-In aceito -> pending_transfer | Existing flow | OK |
| pending_transfer -> em_assistencia | T7 | OK |
| OS interna -> ProdutoOs + pendencia | T3 | OK |
| remover pendencia | T4 | OK |
| pendencia -> commit | T5 | OK |
| commit -> acao administrativa | T6 | OK |
| commit -> baixa real | T5 | OK |
| commit -> custo agregado | T5 | OK |
| commit -> ATIVO/transferred | T5/T6 | OK |
| UI/relatorios | T8/T9 | OK |

### Check 3: Test Co-Location Validation

` .specs/codebase/TESTING.md` ainda nao existe neste reposito. Ate que uma matriz formal seja criada, cada task inclui validacao manual/gate minimo no proprio escopo. Se o usuario desejar, uma task separada futura pode mapear a estrategia de testes do projeto.

| Task | Tests Field Present? | Co-located? | Status |
| ---- | -------------------- | ----------- | ------ |
| T1 | Yes | Yes | OK |
| T2 | Yes | Yes | OK |
| T3 | Yes | Yes | OK |
| T4 | Yes | Yes | OK |
| T5 | Yes | Yes | OK |
| T6 | Yes | Yes | OK |
| T7 | Yes | Yes | OK |
| T8 | Yes | Yes | OK |
| T9 | Yes | Yes | OK |
| T10 | Yes | Yes | OK |

---

## Parallel Execution Map

Por seguranca operacional, todas as tasks estao sequenciais. A area toca estoque, custo, OS e Trade-In; evitar paralelismo reduz risco de conflitos e regressao.

```text
T1 -> T2 -> T3 -> T4 -> T5 -> T6 -> T7 -> T8 -> T9 -> T10
```

---

## Tools For Execution

- MCP: nenhum MCP especifico ativo para este projeto.
- Skills: `tlc-spec-driven`.
- Ferramentas locais: `rg`, leitura de arquivos, `apply_patch`, comandos Laravel/PHP disponiveis quando aprovados.

Execucao aguardando aprovacao explicita do usuario.
