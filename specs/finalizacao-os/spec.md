# Finalização Financeira Da OS - Specification

## Objetivo

Garantir que finalizar OS seja seguro, transacional e idempotente quando envolver faturamento, conta a receber e garantia.

## Escopo

- Mudança para finalizada.
- Geração de fatura da OS.
- Conta a receber.
- Garantia existente.

## Fora De Escopo

- Emissão de NFe.
- Cobrança.
- Conciliação financeira.
- Novas formas de pagamento.
- Redesign do financeiro.

## Regras De Negócio

- Finalizar OS deve acontecer uma única vez por ciclo.
- Se houver faturamento, fatura e conta a receber não podem duplicar.
- Garantia não pode ser criada múltiplas vezes para a mesma finalização.
- OS finalizada deve bloquear alterações críticas, salvo reabertura formal.
- Reabertura de OS com financeiro exige validação específica.

## Regras Técnicas

- Finalização deve executar em transação.
- Deve haver lock da OS durante finalização.
- A operação deve ser reentrante/idempotente.
- Antes de criar financeiro/garantia, o sistema deve verificar existência lógica.
- Falha parcial deve fazer rollback completo.

## Critérios De Aceite

- `OS-FIN-01`: WHEN finalizar OS com faturamento THEN o sistema SHALL criar no máximo uma fatura.
- `OS-FIN-02`: WHEN finalizar OS com conta a receber THEN o sistema SHALL criar no máximo uma conta correspondente.
- `OS-FIN-03`: WHEN repetir finalização THEN o sistema SHALL não duplicar financeiro nem garantia.
- `OS-FIN-04`: WHEN ocorrer erro durante finalização THEN o sistema SHALL manter a OS sem efeitos parciais.
- `OS-FIN-05`: WHEN OS estiver finalizada THEN alterações críticas SHALL exigir reabertura formal.

## Riscos

- Cobrança duplicada.
- Garantia duplicada.
- OS finalizada com financeiro parcial.
- Alterações retroativas sem rastreio.

## Arquivos Afetados

- `app/Http/Controllers/OrdemServicoController.php`
- `app/Models/OrdemServico.php`
- modelos de `FaturaOrdemServico`
- `ContaReceber`
- `Garantia`
- migrations relacionadas a vínculos/constraints

## Dependências

- Regra de finalização.
- Regra de reabertura.
- Regra de idempotência.
- Auditoria operacional.
