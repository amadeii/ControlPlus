# Auditoria Operacional - Design

## Fluxo Técnico

- Reaproveitar estruturas existentes:
  - `__createLog`
  - `AcaoLog`
  - `AuditRequestContext`
  - `MovimentacaoProdutoObserver`
  - `AuditEstoqueDetalhe`
  - `OrdemServicoAuditoriaAlteracaoLogger`
  - `AuditoriaOperacionalController`
- Padronizar quais eventos são obrigatórios e quais campos precisam constar no payload.
- Auditoria deve ser chamada pelos services/fluxos críticos, não apenas por controller.

## Boundaries

- Controller não deve montar payload complexo de auditoria.
- Serviços críticos emitem eventos/auditorias de domínio.
- Observer de movimentação continua capturando detalhes de estoque.
- Tela/log atual continua como consumidor.

## Transações

- Auditoria de evento crítico deve participar da mesma transação da operação.
- Eventos apenas informativos podem continuar best-effort.
- Estoque, custo, cancelamento e finalização devem ter auditoria transacional.

## Locks

- Auditoria em si não deve impor lock de negócio.
- Deve registrar entidades já bloqueadas pela operação principal.
- Para payload antes/depois, capturar estado antes da mutação e depois da mutação dentro da mesma operação.

## Idempotência

- Evento auditável de operação idempotente deve distinguir:
  - operação executada;
  - operação ignorada porque já existia.
- Auditoria não deve duplicar evento de baixa/estorno se a operação não produziu novo movimento.
- Eventos financeiros devem se vincular à entidade criada ou reaproveitada.

## Eventos Obrigatórios

- OS: abertura, alteração de status, alteração de fase, cancelamento, reabertura, finalização.
- Peças: inclusão, remoção, baixa, estorno.
- Trade-in: custo agregado e custo revertido.
- Financeiro: fatura/conta/garantia criadas ou reaproveitadas por idempotência.
- Segurança: tentativa bloqueada por permissão quando envolver custo/OS interna pode ser logável, mas não precisa bloquear o MVP.

## Responsabilidades

- `AuditRequestContext`: usuário, IP, sessão, user-agent.
- `AcaoLog`: evento de domínio e payload.
- `AuditEstoqueDetalhe`: detalhe da movimentação.
- Logger de OS: snapshot antes/depois para mudanças relevantes.
- Services de domínio: decidir quando auditoria é obrigatória.

## Rollback

- Se evento crítico não puder ser auditado, a operação deve falhar.
- Se observer de estoque falhar, design futuro deve tratar como falha crítica para baixa/estorno de assistência.
- Se log informativo falhar, pode não bloquear, desde que não envolva estoque, custo ou financeiro.

## Compatibilidade Legado

- Não criar sistema de auditoria paralelo.
- Reaproveitar logs e tela existentes.
- Incrementar payloads e obrigatoriedade por fluxo crítico.
- Manter relatórios atuais consumindo estruturas existentes.

## Pontos Críticos

- Hoje parte da auditoria é best-effort.
- Nem todos os endpoints auxiliares parecem emitir auditoria uniforme.
- Permissão de visualização de custo precisa ser aplicada também em views e relatórios.
- Auditoria precisa preservar motivo em cancelamento, reabertura e ajustes.

## Sequência Técnica Recomendada Antes De Tasks

1. Aprovar este design como boundary operacional.
2. Confirmar se auditoria crítica deve bloquear operação em caso de falha.
3. Confirmar se peça manual continuará fora de estoque/custo.
4. Confirmar se finalização idempotente deve bloquear repetição ou retornar sucesso sem novo efeito.
5. Só então quebrar em tasks pequenas, começando por finalização/idempotência e estoque/estorno.
