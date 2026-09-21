#!/bin/bash
# ==============================================================================
# Script de Deploy e Atualização do Servidor de Licenças CEGID
# VPS Oracle Cloud (ARM64 - Ubuntu 24.04)
# ==============================================================================

set -e

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/cegid-license-server"

echo "=================================================="
echo "🚀 Iniciando Deploy do Servidor de Licenças CEGID"
echo "📁 Diretório: $APP_DIR"
echo "=================================================="

cd "$APP_DIR"

# 1. Verificar arquivo .env
if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    echo "⚠️ Arquivo .env não encontrado. Criando cópia a partir de .env.example..."
    cp .env.example .env
    echo "🔑 Por favor, ajuste as credenciais em .env caso deseje alterar as senhas padrão."
  else
    echo "❌ Erro: Arquivo .env ou .env.example não encontrado!"
    exit 1
  fi
fi

# 2. Build e inicialização dos containers
echo "📦 Construindo e iniciando containers Docker..."
docker compose build --pull
docker compose up -d

# 3. Aguardar o banco de dados estar pronto
echo "⏳ Aguardando banco de dados PostgreSQL iniciar..."
sleep 5

# 4. Executar Prisma Db Push / Migrations
echo "🔄 Executando migrações do banco de dados (Prisma)..."
docker compose exec -T cegid-license-app npx prisma db push --skip-generate

# 5. Status final
echo "✅ Deploy concluído com sucesso!"
docker compose ps
echo "=================================================="
echo "🌐 Aplicação ativa localmente na porta 3010."
echo "🔗 Configure o Nginx host apontando para http://127.0.0.1:3010"
echo "=================================================="
