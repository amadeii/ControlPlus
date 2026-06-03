# Auditoria Operacional - Specification

## Objetivo

Definir auditoria mínima obrigatória para eventos que alteram OS, estoque, custo, financeiro ou status operacional.

## Escopo

- Abertura.
- Alteração de estado/fase.
- Inclusão/remoção de peça.
- Baixa.
- Estorno.
- Cancelamento.
- Reabertura.
- Finalização.
- Custo agregado ao trade-in.

## Fora De Escopo

- SIEM externo.
- Trilha completa de todos os campos administrativos.
- Redesign da tela de logs.

## Regras De Negócio

- Toda operação crítica deve responder: quem fez, quando, onde, em qual entidade e por quê quando aplicável.
- Motivo é obrigatório para cancelamento, reabertura, exclusão operacional, ajustes e reversões críticas.
- Usuários sem permissão não devem ver custo interno/trade-in.

## Regras Técnicas

- Auditoria deve registrar payload antes/depois quando houver alteração de estado ou custo.
- Movimentações de estoque devem manter vínculo com usuário, OS, peça e tipo de transação.
- Falha de auditoria em evento crítico não deve ser silenciosa.
- Logs devem reutilizar estruturas existentes: `AcaoLog`, `AuditEstoqueDetalhe`, observers e logger de OS.

## Critérios De Aceite

- `OS-AUD-01`: WHEN abrir, cancelar, reabrir ou finalizar OS THEN o sistema SHALL registrar evento auditável.
- `OS-AUD-02`: WHEN baixar ou estornar peça THEN o sistema SHALL registrar movimento e auditoria de estoque.
- `OS-AUD-03`: WHEN agregar custo ao trade-in THEN o sistema SHALL registrar valor anterior, incremento e valor posterior.
- `OS-AUD-04`: WHEN operação exigir motivo THEN o sistema SHALL bloquear ausência de motivo.
- `OS-AUD-05`: WHEN usuário não tiver permissão de custo THEN o sistema SHALL ocultar dados sensíveis.

## Riscos

- Divergência sem explicação.
- Custo interno exposto.
- Operação crítica sem responsável.
- Auditoria incompleta em falha parcial.

## Arquivos Afetados

- `app/Helpers/Functions.php`
- `app/Services/AuditRequestContext.php`
- `app/Observers/MovimentacaoProdutoObserver.php`
- `app/Services/OrdemServicoAuditoriaAlteracaoLogger.php`
- `app/Http/Controllers/AuditoriaOperacionalController.php`
- `app/Models/AcaoLog.php`
- `app/Models/AuditEstoqueDetalhe.php`

## Dependências

- Permissões de custo/OS interna.
- Regra de cancelamento.
- Regra de estorno.
- Regra de finalização idempotente.
