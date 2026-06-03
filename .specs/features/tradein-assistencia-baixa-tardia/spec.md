# Trade-In + Assistencia Com Baixa Tardia Specification

## Problem Statement

O modulo de Assistencia Tecnica ja existe e deve ser reaproveitado para aparelhos recebidos via Trade-In. Hoje as pecas adicionadas a uma OS de assistencia podem baixar estoque imediatamente, mas o fluxo desejado exige apontamento tecnico sem baixa fisica ate conclusao do reparo e aprovacao administrativa.

## Goals

- [ ] Integrar itens de Trade-In ao fluxo existente de OS interna de assistencia sem recriar assistencia.
- [ ] Garantir que pecas de OS interna vinculada a Trade-In sejam apontadas como pendentes antes da aprovacao final.
- [ ] Executar baixa real, movimentacao, custo agregado e ativacao para venda em commit administrativo atomico e idempotente.
- [ ] Preservar o comportamento legado de OS normal, que continua com baixa imediata de pecas.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Recriar fluxo de assistencia | O modulo de OS/assistencia ja existe e deve ser reaproveitado. |
| Alterar globalmente baixa de OS normal | Risco alto de regressao em operacao existente. |
| Reestruturar ReparoInterno | Fluxo paralelo fora do menor caminho seguro. |
| Commit automatico ou deploy | Politica operacional exige aprovacao explicita. |
| Finalizar automaticamente OS como `fz` no commit | O commit de estoque/custo deve ficar desacoplado do encerramento tecnico/financeiro definitivo. |

---

## User Stories

### P1: Apontar pecas sem baixa imediata em OS interna Trade-In - MVP

**User Story**: As an tecnico/administrativo, I want pecas usadas em aparelho Trade-In sejam apontadas na OS interna sem baixar estoque imediatamente so that a baixa fisica ocorra apenas apos aprovacao administrativa.

**Why P1**: Evita consumo prematuro de estoque e preserva a decisao administrativa final.

**Acceptance Criteria**:

1. WHEN uma peca e adicionada a uma OS normal THEN system SHALL manter a baixa imediata atual.
2. WHEN uma peca e adicionada a uma OS interna vinculada a Trade-In THEN system SHALL criar a linha da OS e uma pendencia de baixa sem criar `os_consumo_peca`.
3. WHEN uma peca pendente e removida antes do commit THEN system SHALL cancelar/remover a pendencia sem executar estorno fisico.
4. WHEN uma peca ja baixada tenta ser removida no novo fluxo THEN system SHALL bloquear a remocao no primeiro incremento.

**Independent Test**: Criar uma OS normal e uma OS interna vinculada a Trade-In, adicionar peca em ambas e verificar que somente a OS normal gera movimentacao imediata.

---

### P1: Commit administrativo atomico e idempotente - MVP

**User Story**: As an administrativo, I want aprovar o aparelho pos-reparo so that o sistema baixe as pecas, some custo e disponibilize o aparelho para venda de forma segura.

**Why P1**: E o ponto de integridade central do fluxo Trade-In + Assistencia.

**Acceptance Criteria**:

1. WHEN o administrativo aprova um item Trade-In em assistencia com OS interna aprovada THEN system SHALL executar baixa real das pecas pendentes.
2. WHEN a baixa real ocorre THEN system SHALL criar movimentacao `os_consumo_peca` e lancamento de custo agregado uma unica vez por `produto_os_id`.
3. WHEN o commit termina com sucesso THEN system SHALL alterar `tradein_inventory_items.status` para `transferred` e liberar o aparelho com status operacional `ATIVO`.
4. WHEN o commit falha em qualquer etapa THEN system SHALL reverter todas as alteracoes da transacao.
5. WHEN o commit e executado novamente THEN system SHALL nao duplicar baixa, movimentacao ou custo.

**Independent Test**: Executar a aprovacao administrativa duas vezes sobre o mesmo item e verificar que ha apenas uma baixa/custo por peca.

---

### P2: Conectar inventario Trade-In a OS interna existente

**User Story**: As an administrativo, I want enviar um item Trade-In para OS interna so that a assistencia tecnica existente seja usada para reparo.

**Why P2**: Necessario para reduzir acao manual e evitar criacao de fluxos paralelos.

**Acceptance Criteria**:

1. WHEN um item `pending_transfer` e enviado para assistencia THEN system SHALL criar ou direcionar para uma OS interna vinculada ao `tradein_inventory_item_id`.
2. WHEN o item entra em assistencia THEN system SHALL marcar `tradein_inventory_items.status` como `em_assistencia`.
3. WHEN ja existe OS interna ativa para o mesmo Trade-In THEN system SHALL impedir duplicidade.

**Independent Test**: Enviar um item Trade-In para assistencia e confirmar que o vinculo aparece na OS interna.

---

### P2: Transparencia operacional na tela da OS

**User Story**: As an tecnico/administrativo, I want ver se as pecas estao pendentes ou baixadas so that eu entenda o efeito no estoque antes da aprovacao final.

**Why P2**: Reduz erro operacional e evita surpresa com estoque.

**Acceptance Criteria**:

1. WHEN uma OS interna Trade-In possui pecas pendentes THEN system SHALL exibir status visual de pendencia.
2. WHEN uma peca ja foi baixada pelo commit THEN system SHALL exibir status de baixa concluida.
3. WHEN a OS nao e interna Trade-In THEN system SHALL preservar o comportamento visual atual ou aplicar mudanca minima sem alterar semantica.

**Independent Test**: Abrir a OS antes e depois do commit administrativo e verificar o status das pecas.

---

## Edge Cases

- WHEN OS nao e interna vinculada a Trade-In THEN system SHALL manter comportamento legado.
- WHEN produto da linha nao gerencia estoque THEN system SHALL nao criar baixa fisica, mas manter rastreabilidade conforme fluxo existente.
- WHEN duas requisicoes de commit chegam simultaneamente THEN system SHALL usar locks e unicidade para impedir duplicidade.
- WHEN falta OS interna vinculada ao Trade-In THEN system SHALL bloquear commit administrativo.
- WHEN OS interna nao esta em estado administrativo permitido (`ap`) THEN system SHALL bloquear commit.
- WHEN o aparelho ainda nao pode ficar vendavel THEN system SHALL manter status operacional nao-ATIVO.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| TIAS-01 | P1: Apontar pecas sem baixa imediata | Tasks | Pending Validation |
| TIAS-02 | P1: Remover/cancelar pendencia sem estorno fisico | Tasks | Pending Validation |
| TIAS-03 | P1: Commit administrativo atomico | Tasks | Pending Validation |
| TIAS-04 | P1: Idempotencia contra execucao duplicada | Tasks | Pending Validation |
| TIAS-05 | P1: Custo agregado somente apos baixa real | Tasks | Pending Validation |
| TIAS-06 | P1: Ativacao para venda somente apos aprovacao | Tasks | Pending Validation |
| TIAS-07 | P2: Enviar Trade-In para OS interna existente | Tasks | Pending Validation |
| TIAS-08 | P2: Evitar OS interna duplicada para mesmo Trade-In | Tasks | Pending Validation |
| TIAS-09 | P2: Exibir pendencias/baixas na UI da OS | Tasks | Pending |
| TIAS-10 | Compatibilidade legado de OS normal | Tasks | Pending |

**Coverage**: 10 total, 10 mapped to tasks, 0 unmapped.

---

## Success Criteria

- [ ] OS normal continua baixando pecas imediatamente.
- [ ] OS interna vinculada a Trade-In cria pendencias sem movimentar estoque.
- [ ] Commit administrativo baixa estoque, registra movimentacao, agrega custo e ativa venda em transacao unica.
- [ ] Reexecucao do commit nao duplica movimentacao nem custo.
- [ ] Trade-In usa estados minimos: `pending_transfer`, `em_assistencia`, `transferred`, `cancelled`.
- [ ] Nenhum fluxo funcional novo e implementado antes da aprovacao explicita das tasks.
