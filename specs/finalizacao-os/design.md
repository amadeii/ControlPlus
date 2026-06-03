# Finalização Financeira - Design

## Fluxo Técnico

- Entrada atual é `OrdemServicoController::updateEstado`.
- Design recomendado: manter rota e controller, mas isolar a operação crítica de finalização em boundary transacional.
- Finalização deve coordenar:
  - mudança de estado da OS;
  - fatura da OS;
  - conta a receber;
  - garantia;
  - auditoria.

## Boundaries

- Controller: autorização, request e resposta.
- Operação de finalização: regra transacional.
- Models financeiros existentes continuam sendo usados.
- Auditoria recebe evento final de sucesso.

## Transações

Finalização com financeiro deve ser uma única transação:

1. Lock da OS.
2. Validar estado anterior.
3. Validar se já existe fatura/conta/garantia correspondente.
4. Atualizar estado para `fz`.
5. Criar fatura, se aplicável e inexistente.
6. Criar conta a receber, se aplicável e inexistente.
7. Criar garantias, se aplicável e inexistente.
8. Registrar auditoria.
9. Commit.

## Locks

- Lock em `ordem_servicos`.
- Lock ou verificação forte em fatura vinculada à OS.
- Lock/verificação em conta a receber vinculada.
- Verificação de garantias já existentes.

## Idempotência

- Repetir finalização não pode criar nova fatura.
- Repetir finalização não pode criar nova conta.
- Repetir finalização não pode criar nova garantia.
- Se a OS já estiver finalizada, a operação deve retornar resultado consistente ou bloquear com mensagem clara.

## Eventos

- `os_finalizacao_iniciada`
- `os_finalizada`
- `os_fatura_gerada`
- `os_conta_receber_gerada`
- `os_garantia_gerada`
- `os_finalizacao_ignorada_por_idempotencia`
- `os_finalizacao_falhou`

## Responsabilidades

- Workflow decide se OS pode finalizar.
- Financeiro cria documentos derivados.
- Garantia só nasce quando a regra atual permitir.
- Auditoria registra a operação final, não apenas efeitos isolados.

## Rollback

- Se fatura falhar, estado da OS não deve mudar para finalizado.
- Se conta a receber falhar, fatura criada na mesma transação deve ser revertida.
- Se garantia falhar, financeiro e estado devem voltar.
- Nenhum efeito parcial deve sobreviver a erro.

## Compatibilidade Legado

- Manter `updateEstado` como entrada inicial.
- Não redesenhar financeiro.
- Não alterar emissão de NFe.
- Não alterar regra comercial de geração de garantia, apenas blindar duplicidade e transação.

## Pontos Críticos

- Hoje a finalização parece misturar mudança de estado e geração financeira.
- Risco alto de duplicidade.
- Falta de máquina de estados explícita.
- Reabertura precisa saber lidar com OS já faturada.
