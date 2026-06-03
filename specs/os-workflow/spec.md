# Workflow De OS - Specification

## Objetivo

Formalizar o ciclo de vida mínimo da OS de assistência, preservando o comportamento real existente e preparando cancelamento/reabertura sem apagar histórico.

## Escopo

- Abertura de OS cliente.
- Abertura de OS interna.
- Status comercial.
- Fase técnica.
- Cancelamento formal.
- Reabertura controlada.

## Fora De Escopo

- Migrar `ReparoInterno`.
- Redesenhar telas.
- Reserva de estoque.
- NFe.
- Novos relatórios.

## Regras De Negócio

- `OS` é o fluxo dominante; `ReparoInterno` permanece coexistente/legado.
- OS cliente exige cliente.
- OS interna não exige cliente e deve estar vinculada a aparelho da loja/trade-in ou produto interno, conforme regra atual.
- Cancelar OS não deve excluir o histórico operacional.
- OS finalizada só pode ser reaberta com permissão, motivo e validação de impacto financeiro/estoque.

## Regras Técnicas

- Usar status comercial existente como base: `pd`, `ap`, `rp`, `fz`.
- Manter fase técnica separada de status comercial.
- Mudanças críticas de estado devem registrar auditoria.
- Cancelamento deve ser estado/evento formal, não exclusão física operacional.

## Critérios De Aceite

- `OS-WF-01`: WHEN abrir OS cliente sem cliente THEN o sistema SHALL bloquear.
- `OS-WF-02`: WHEN abrir OS interna THEN o sistema SHALL exigir vínculo válido com aparelho/produto interno.
- `OS-WF-03`: WHEN alterar fase técnica THEN o sistema SHALL preservar status comercial.
- `OS-WF-04`: WHEN cancelar OS THEN o sistema SHALL manter OS, itens, movimentos e auditoria.
- `OS-WF-05`: WHEN reabrir OS finalizada THEN o sistema SHALL exigir permissão, motivo e validar financeiro/estoque.

## Riscos

- Duplicidade de fluxos com `ReparoInterno`.
- Cancelamento parcial.
- Alteração retroativa de OS finalizada.

## Arquivos Afetados

- `app/Http/Controllers/OrdemServicoController.php`
- `app/Models/OrdemServico.php`
- `app/Services/AssistenciaOsControleTecnicoService.php`
- `routes/web.php`
- `resources/views/ordem_servico/*`

## Dependências

- Decisão de fluxo dominante.
- Regra de cancelamento.
- Regra de reabertura.
