#!/bin/sh
set -eu
if ! command -v docker >/dev/null 2>&1; then
  echo 'Docker não encontrado. Instale Docker Engine e o plugin Compose antes de continuar.' >&2
  exit 1
fi
docker compose version >/dev/null
cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
docker compose up -d --build
echo 'OpenPanel iniciado em http://127.0.0.1:8080'
echo 'Acesso remoto: ssh -L 8080:127.0.0.1:8080 usuario@IP'
echo 'Crie a primeira conta antes de expor a instalação.'
