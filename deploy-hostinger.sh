#!/usr/bin/env bash
# ==============================================================================
# SCRIPT DE DEPLOY E OTIMIZAÇÃO NA HOSTINGER (Laravel 12)
# Compatível com limitações de proc_open do PHP da Hostinger
# ==============================================================================

set -e

echo "🚀 Iniciando deploy na Hostinger..."

# 0. Limpar caches antigos de pacotes do bootstrap para evitar erros de classes de dev (como Pail/Sail)
echo "🧹 Limpando caches de inicialização antigos..."
rm -f bootstrap/cache/*.php

# 1. Instalar dependências sem pacotes de desenvolvimento e sem scripts que exijam proc_open
echo "📦 Instalando dependências do Composer (modo produção)..."
composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# 2. Limpar qualquer cache residual do bootstrap novamente após o composer install
rm -f bootstrap/cache/*.php

# 3. Executar migrações do banco de dados
echo "🗄️ Executando migrações do banco de dados..."
php artisan migrate --force

# 4. Criar caches de alta performance
echo "⚡ Otimizando configurações, rotas e views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Criar link simbólico de storage se não existir
echo "🔗 Verificando storage:link..."
php artisan storage:link || true

# 6. Ajustar permissões para o servidor web da Hostinger
echo "🔒 Ajustando permissões de storage e cache..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "✅ Deploy da API finalizado com sucesso!"
