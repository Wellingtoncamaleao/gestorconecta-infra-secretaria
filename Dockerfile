FROM php:8.4-fpm-bookworm

# Dependencias do sistema
RUN apt-get update && apt-get install -y \
    nginx supervisor curl cron \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Configs do servidor
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

# Aplicacao
COPY index.html /var/www/html/index.html
COPY api/ /var/www/html/api/
COPY cron/ /var/www/html/cron/
COPY painel/ /var/www/html/painel/

# Crontab: processar agendados a cada minuto
RUN echo "* * * * * php /var/www/html/cron/agendados.php >> /var/log/secretaria-cron.log 2>&1" | crontab -

# Permissoes
RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/log \
    && touch /var/log/secretaria-cron.log \
    && chown www-data:www-data /var/log/secretaria-cron.log

WORKDIR /var/www/html
EXPOSE 80

CMD ["/entrypoint.sh"]
