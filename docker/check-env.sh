#!/usr/bin/env bash
set -eo pipefail

ENV_FILE="/var/www/html/.env"

# Se não existe .env, gera a partir das variáveis de ambiente
if [ ! -f "$ENV_FILE" ]; then
    echo "INFO: .env não encontrado. Gerando a partir das variáveis de ambiente..."
    printenv | grep -E '^(APP_|DB_|MYSQL_|CACHE_|QUEUE_|SESSION_|MAIL_|AWS_|REDIS_)' > /tmp/.env.tmp
    cp /tmp/.env.tmp "$ENV_FILE" 2>/dev/null || {
        echo "AVISO: Não foi possível criar .env em disco. Usando variáveis de ambiente diretamente."
    }
fi

REQUIRED_VARS=(
    APP_ENV
    DB_HOST
    DB_DATABASE
    DB_USERNAME
    DB_PASSWORD
)

MISSING_VARS=()

for VAR in "${REQUIRED_VARS[@]}"; do
    VALUE="${!VAR}"
    if [ -z "$VALUE" ]; then
        MISSING_VARS+=("$VAR")
    fi
done

if [ -z "${APP_KEY}" ]; then
    echo "ERRO: APP_KEY deve existir nas variáveis de ambiente"
    MISSING_VARS+=("APP_KEY")
fi

if [ "${#MISSING_VARS[@]}" -ne 0 ]; then
    echo "Variáveis obrigatórias ausentes:"
    for VAR in "${MISSING_VARS[@]}"; do
        echo "  - $VAR"
    done
    exit 1
fi

exec "$@"