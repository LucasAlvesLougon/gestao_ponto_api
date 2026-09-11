#!/usr/bin/env bash
# ==============================================================================
# SCRIPT DE DEPLOY E OTIMIZAÇÃO NA HOSTINGER (Laravel 12)
# Compatível com limitações de proc_open do PHP da Hostinger
# ==============================================================================

set -e

echo "🚀 Iniciando deploy na Hostinger..."

# 1. Instalar dependências sem pacotes de desenvolvimento e sem scripts que exijam proc_open
echo "📦 Instalando dependências do Composer (modo produção)..."
composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# 2. Descobrir pacotes e executar migrações do banco de dados
echo "🗄️ Executando migrações do banco de dados..."
php artisan package:discover --ansi || true
php artisan migrate --force

# 3. Limpar e criar caches de alta performance
echo "⚡ Otimizando configurações, rotas e views..."
php artisan optimize:clear || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Criar link simbólico de storage se não existir
echo "🔗 Verificando storage:link..."
php artisan storage:link || true

# 5. Ajustar permissões para o servidor web da Hostinger
echo "🔒 Ajustando permissões de storage e cache..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "✅ Deploy da API finalizado com sucesso!"
