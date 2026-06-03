# Estoque E Peças - Design

## Fluxo Técnico

- Inclusão/remoção de peça continua entrando por `OrdemServicoController`.
- Regra de estoque permanece em `AssistenciaOsEstoqueService`.
- Agregação de custo ao trade-in permanece em `TradeinAssistenciaPecaCustoService`.
- `ProdutoOs` continua representando peça cadastrada ou item manual.

## Boundaries

- Controller valida request e chama operação.
- `ProdutoOs` guarda a linha da OS.
- `AssistenciaOsEstoqueService` executa baixa/estorno.
- `TradeinAssistenciaPecaCustoService` agrega/reverte custo do trade-in.
- `MovimentacaoProduto` é a fonte operacional das movimentações.

## Transações

Inclusão de peça deve ocorrer em uma única transação:

1. Lock da OS.
2. Criar `ProdutoOs`.
3. Atualizar total da OS.
4. Se peça cadastrada e controla estoque, aplicar baixa.
5. Se OS vinculada a trade-in, lançar custo agregado.
6. Registrar logs/auditoria.
7. Commit.

Remoção de peça:

1. Lock da linha `ProdutoOs`.
2. Lock da OS.
3. Estornar estoque, se houve baixa.
4. Reverter custo de trade-in, se houve lançamento.
5. Atualizar total da OS.
6. Excluir ou marcar linha conforme regra atual.
7. Commit.

## Locks

- Lock em `ordem_servicos`.
- Lock em `produto_os`.
- Lock em estoque/local do produto durante baixa/estorno.
- Lock em `tradein_inventory_items` quando alterar custo agregado.

## Idempotência

- Baixa: chave lógica por `tipo_transacao = os_consumo_peca` + `codigo_transacao = produto_os.id`.
- Estorno: chave lógica por `tipo_transacao = os_estorno_peca` + `codigo_transacao = produto_os.id`.
- Custo trade-in: usar unicidade por `produto_os_id`, como já existe.
- Reprocessar baixa/estorno não pode duplicar movimentação nem custo.

## Eventos

- `os_peca_adicionada`
- `os_consumo_peca`
- `os_peca_removida`
- `os_estorno_peca`
- `tradein_custo_peca_agregado`
- `tradein_custo_peca_revertido`

## Responsabilidades

- Peça manual: descrição e valor comercial, sem estoque e sem custo automático.
- Peça cadastrada sem controle de estoque: entra na OS, mas não movimenta estoque.
- Peça cadastrada com controle de estoque: baixa e estorno obrigatórios.
- Trade-in: só recebe custo agregado se houver `tradein_inventory_item_id`.

## Rollback

- Se baixa falhar, a linha da OS não deve permanecer criada.
- Se custo trade-in falhar após baixa, a baixa deve ser revertida pelo rollback.
- Se estorno falhar, a remoção da peça não deve ser concluída.
- Se auditoria crítica falhar, operação crítica deve falhar ou ser explicitamente tratada como bloqueante.

## Compatibilidade Legado

- Reaproveitar `AssistenciaOsEstoqueService`.
- Reaproveitar `TradeinAssistenciaPecaCustoService`.
- Manter `MovimentacaoProduto` como trilha operacional.
- Não alterar fluxo de `ReparoInterno` nesta etapa.

## Pontos Críticos

- Sem constraint forte em `movimentacao_produtos`.
- Custo histórico ainda precisa ser congelado de forma confiável.
- Estoque negativo.
- Peça manual fora do custo.
