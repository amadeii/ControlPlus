# Workflow De OS - Design

## Fluxo Técnico

- Entrada continua em `OrdemServicoController`.
- O controller deve seguir como boundary HTTP: validação, autorização e montagem de resposta.
- A regra operacional deve ficar concentrada em fluxo de OS, reaproveitando:
  - `OrdemServico`
  - `AssistenciaOsControleTecnicoService`
  - logger/auditoria existente
  - rotas atuais de `ordem-servico`
- OS cliente e OS interna continuam no mesmo modelo, diferenciadas por `escopo_ordem_servico`.
- `ReparoInterno` permanece coexistente, sem receber novas regras de domínio de OS.

## Boundaries

- `OrdemServicoController`: request, permissões, validação superficial.
- `OrdemServico`: estado atual e relacionamentos.
- `AssistenciaOsControleTecnicoService`: fase técnica, checklist e eventos técnicos.
- Estoque/peças não devem ser resolvidos diretamente pelo workflow da OS; devem continuar no boundary de estoque.
- Financeiro não deve ser gerado fora do fluxo formal de finalização.

## Transações

- Abertura de OS deve ser transacional quando envolver:
  - criação da OS;
  - vínculo com trade-in/aparelho interno;
  - evento inicial;
  - checklist inicial.
- Alteração simples de fase pode ser transacional curta.
- Cancelamento e reabertura devem ser transacionais porque podem tocar estado, estoque, financeiro e auditoria.

## Locks

- Lock na linha da OS ao cancelar, reabrir ou finalizar.
- Lock no item de trade-in/produto interno quando OS interna depender desse vínculo.
- Evitar lock amplo em listagens, painéis e relatórios.

## Idempotência

- Abrir OS não deve gerar eventos/checklists duplicados se a criação for reprocessada.
- Cancelar OS já cancelada deve ser operação neutra ou bloqueada com mensagem clara.
- Reabrir OS não finalizada/cancelada deve ser bloqueado.

## Eventos

- `os_aberta`
- `os_fase_tecnica_alterada`
- `os_status_alterado`
- `os_cancelada`
- `os_reaberta`
- `os_finalizada`

## Responsabilidades

- Workflow da OS decide estado e fase.
- Estoque decide baixa/estorno.
- Financeiro decide fatura/conta/garantia.
- Auditoria registra antes/depois e motivo.

## Sequência Operacional

1. Validar permissão e escopo.
2. Validar dados mínimos da OS.
3. Iniciar transação quando houver impacto operacional.
4. Criar/alterar OS.
5. Acionar serviço técnico quando assistência estiver ativa.
6. Registrar evento/auditoria.
7. Confirmar transação.

## Rollback

- Falha ao criar evento inicial deve desfazer abertura se o evento for obrigatório.
- Falha em cancelamento deve desfazer alterações de estado e estornos.
- Falha em reabertura deve manter OS no estado anterior.

## Compatibilidade Legado

- Manter status existentes `pd`, `ap`, `rp`, `fz`.
- Manter fases técnicas atuais.
- Não remover `ReparoInterno`.
- Não mudar rotas públicas existentes inicialmente.

## Pontos Críticos

- String `assistencia técinica`.
- Sequencial de OS por `last + 1`.
- Permissões auxiliares.
- Separação entre status comercial e fase técnica.
