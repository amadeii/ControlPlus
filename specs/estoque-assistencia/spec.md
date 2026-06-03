# Estoque E Peças Na OS - Specification

## Objetivo

Tornar explícita a regra de consumo, baixa, estorno e custo histórico das peças usadas na OS.

## Escopo

- Inclusão de peça cadastrada.
- Peça manual.
- Baixa automática.
- Estorno.
- Movimentações rastreáveis.
- Custo de peça em trade-in.

## Fora De Escopo

- Compras.
- Entrada geral de estoque.
- Reserva de peças.
- Inventário completo.
- Migração de `ReparoInterno`.

## Regras De Negócio

- Incluir peça cadastrada em OS de assistência significa consumo real da peça.
- Peça cadastrada com controle de estoque deve gerar baixa.
- Remover peça consumida deve gerar estorno compensatório.
- Movimento original não deve ser apagado.
- Peça manual sem cadastro é apenas descritiva e não baixa estoque nem agrega custo automaticamente.
- Custo da peça deve ser congelado no momento da baixa.
- Custo só deve agregar ao trade-in se a OS estiver vinculada a item oficial de estoque do trade-in.

## Regras Técnicas

- Baixa deve gerar `movimentacao_produtos` com tipo `os_consumo_peca`.
- Estorno deve gerar `os_estorno_peca`.
- Baixa e estorno devem ser idempotentes.
- Operações devem ocorrer em transação com lock da OS/linha quando houver estoque.
- Custo unitário e total usados na baixa não podem depender apenas do valor atual do produto.

## Critérios De Aceite

- `OS-EST-01`: WHEN adicionar peça cadastrada controlada THEN o sistema SHALL baixar estoque uma única vez.
- `OS-EST-02`: WHEN repetir a mesma operação THEN o sistema SHALL não duplicar baixa.
- `OS-EST-03`: WHEN remover peça baixada THEN o sistema SHALL criar estorno compensatório.
- `OS-EST-04`: WHEN remover peça manual THEN o sistema SHALL não movimentar estoque.
- `OS-EST-05`: WHEN peça for vinculada a OS de trade-in THEN o sistema SHALL agregar custo histórico ao aparelho.
- `OS-EST-06`: WHEN estornar peça de trade-in THEN o sistema SHALL reverter o lançamento original.

## Riscos

- Baixa antes do uso físico.
- Divergência de custo histórico.
- Duplicidade de movimento.
- Estoque negativo.
- Peça manual distorcendo margem.

## Arquivos Afetados

- `app/Http/Controllers/OrdemServicoController.php`
- `app/Services/AssistenciaOsEstoqueService.php`
- `app/Services/TradeinAssistenciaPecaCustoService.php`
- `app/Models/ProdutoOs.php`
- `app/Models/MovimentacaoProduto.php`
- `app/Models/TradeinInventoryItem.php`

## Dependências

- Decisão do momento da baixa.
- Regra de estorno.
- Regra de custo histórico.
- Regra de trade-in no estoque.
