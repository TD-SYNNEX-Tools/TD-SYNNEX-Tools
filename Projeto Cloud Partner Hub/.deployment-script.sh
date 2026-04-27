#!/bin/bash

# =========================================
# Oryx Build Script para Azure App Service
# =========================================

set -e

echo "======================================"
echo "  Custom Build Script"
echo "======================================"

DEPLOYMENT_SOURCE="${DEPLOYMENT_SOURCE:-$1}"
DEPLOYMENT_TARGET="${DEPLOYMENT_TARGET:-$2}"

if [[ ! -n "$DEPLOYMENT_SOURCE" ]]; then
  DEPLOYMENT_SOURCE=$(pwd)
fi

if [[ ! -n "$DEPLOYMENT_TARGET" ]]; then
  DEPLOYMENT_TARGET="$HOME/site/wwwroot"
fi

echo "Source: $DEPLOYMENT_SOURCE"
echo "Target: $DEPLOYMENT_TARGET"
echo ""

cd "$DEPLOYMENT_SOURCE"

# 1. Build Frontend
echo "[1/4] Installing frontend dependencies..."
npm ci --production=false

echo "[2/4] Building frontend..."
npm run build

# 2. Prepare Server
echo "[3/4] Preparing server directory..."
mkdir -p "$DEPLOYMENT_TARGET"

# Copy server files
echo "[4/4] Copying server files..."
cp -r server/* "$DEPLOYMENT_TARGET/"
cp -r dist "$DEPLOYMENT_TARGET/public"

# Install server dependencies
cd "$DEPLOYMENT_TARGET"
npm ci --production

echo ""
echo "======================================"
echo "  Build Complete!"
echo "======================================"
