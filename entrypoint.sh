#!/bin/bash
# SECRETARIA — Entrypoint
# Garante permissoes e inicia supervisord

# Permissoes
chown -R www-data:www-data /var/www/html 2>/dev/null
touch /var/log/secretaria-cron.log
chown www-data:www-data /var/log/secretaria-cron.log

echo "[entrypoint] Secretaria iniciando..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
