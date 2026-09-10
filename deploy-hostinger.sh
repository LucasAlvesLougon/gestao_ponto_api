#!/usr/bin/env bash
# ==============================================================================
# SCRIPT DE DEPLOY E OTIMIZAÇÃO NA HOSTINGER (Laravel 12)
# ==============================================================================

set -e

echo "🚀 Iniciando deploy na Hostinger..."

# 1. Instalar dependências sem pacotes de desenvolvimento
echo "📦 Instalando dependências do Composer (modo produção)..."
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Executar migrações do banco de dados
echo "🗄️ Executando migrações do banco de dados..."
php artisan migrate --force

# 3. Limpar e criar caches de alta performance
echo "⚡ Otimizando configurações, rotas e views..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Criar link simbólico de storage se não existir
echo "🔗 Verificando storage:link..."
php artisan storage:link || true

# 5. Ajustar permissões para o servidor web da Hostinger
echo "🔒 Ajustando permissões de storage e cache..."
chmod -R 775 storage bootstrap/cache

echo "✅ Deploy da API finalizado com sucesso!"
