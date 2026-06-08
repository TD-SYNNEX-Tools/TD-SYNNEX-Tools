#!/bin/bash

# =========================================
# Script de Deploy para Azure App Service
# =========================================

set -e

echo "======================================"
echo "  CloudPartner HUB - Deploy Azure"
echo "======================================"
echo ""

# Definir diretórios
DEPLOYMENT_SOURCE="${DEPLOYMENT_SOURCE:-.}"
DEPLOYMENT_TARGET="${DEPLOYMENT_TARGET:-./artifact}"

if [ -z "$DEPLOYMENT_SOURCE" ]; then
  DEPLOYMENT_SOURCE=$(pwd)
fi

if [ -z "$DEPLOYMENT_TARGET" ]; then
  DEPLOYMENT_TARGET="$HOME/site/wwwroot"
fi

echo "Source: $DEPLOYMENT_SOURCE"
echo "Target: $DEPLOYMENT_TARGET"
echo ""

cd "$DEPLOYMENT_SOURCE"

# 1. Instalar dependências do frontend
echo "[1/6] Instalando dependências do frontend..."
if [ -f "package.json" ]; then
  npm install --legacy-peer-deps || npm install --force
fi

# 2. Build do frontend
echo "[2/6] Buildando frontend (React + Vite)..."
if [ -f "package.json" ]; then
  npm run build
fi

# 3. Criar estrutura de diretórios no target
echo "[3/6] Criando estrutura de diretórios..."
mkdir -p "$DEPLOYMENT_TARGET"
mkdir -p "$DEPLOYMENT_TARGET/public"

# 4. Copiar arquivos do servidor
echo "[4/6] Copiando arquivos do servidor..."
if [ -d "server" ]; then
  # Copiar arquivos do servidor
  cp -r server/* "$DEPLOYMENT_TARGET/" 2>/dev/null || true
  cp server/.[!.]* "$DEPLOYMENT_TARGET/" 2>/dev/null || true
fi

# 5. Copiar build do frontend para public
echo "[5/6] Copiando build do frontend..."
if [ -d "dist" ]; then
  cp -r dist/* "$DEPLOYMENT_TARGET/public/"
fi

# 6. Instalar dependências do servidor
echo "[6/6] Instalando dependências do servidor..."
cd "$DEPLOYMENT_TARGET"
if [ -f "package.json" ]; then
  npm install --production --legacy-peer-deps || npm install --production --force
fi

echo ""
echo "======================================"
echo "  Deploy Concluído com Sucesso!"
echo "======================================"
echo ""
echo "Arquivos em: $DEPLOYMENT_TARGET"
