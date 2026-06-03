# DEPLOY.md

````md
# DEPLOY.md

## Aplicação

ControlPLUS

## Ambiente

produção

## Regra crítica

Este é o runbook oficial de deploy desta aplicação.
Não utilizar outro fluxo sem autorização explícita.

## Perfil recomendado Codex

operacional

## Diretório local

~/projects/arcadiaplus

## Servidor

Alias SSH: "config pronta de ssh"
Usuário: ubuntu
Host: 00.000.000.00
Diretório remoto: /home/ubuntu/projects

## Pacote

Nome: ControlPLUS.zip
Pasta empacotada: ControlPLUS

## Comandos locais

```bash
cd ~/projects/arcadiaplus

rm -f ControlPLUS.zip

zip -r ControlPLUS.zip ControlPLUS \
  -x "ControlPLUS/.git/*" \
  -x "ControlPLUS/vendor/*" \
  -x "ControlPLUS/node_modules/*" \
  -x "ControlPLUS/storage/logs/*"

scp -i ~/.ssh/altatech-key.pem ControlPLUS.zip ubuntu@45.225.129.46:/home/ubuntu/projects/
```
````

## Comandos no servidor

```bash
ssh altatech

cd ~/projects

unzip -o ControlPLUS.zip

cd ControlPLUS

docker compose down

make up

docker exec -u 1000 backend php artisan optimize:clear

docker exec -u 1000 backend php artisan optimize

docker ps
```

## Validação pós-deploy

```bash
docker ps

docker compose ps

docker logs --tail=100 backend
```

Confirmar:

- containers ativos;
- nenhum container reiniciando;
- aplicação acessível;
- ausência de erro crítico.

## Em caso de erro

Parar execução e informar:

- comando que falhou;
- erro retornado;
- causa provável;
- próxima ação sugerida.

```

```
