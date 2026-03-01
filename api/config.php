<?php
/**
 * SECRETARIA — Gateway centralizado de mensagens WhatsApp
 * Configuracao via env vars (Easypanel injeta)
 */

// Supabase
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: '');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');

// Evolution API
define('EVOLUTION_URL', getenv('EVOLUTION_URL') ?: 'https://evolution.gestorconecta.com.br');
define('EVOLUTION_INSTANCE', getenv('EVOLUTION_INSTANCE') ?: 'oraculo');
define('EVOLUTION_APIKEY', getenv('EVOLUTION_APIKEY') ?: '');

// Limites
define('MAX_MENSAGEM_TEXTO', 4000);
define('MAX_LOTE', 50);
define('INTERVALO_LOTE_MS', 3000);

// Tipos aceitos
define('TIPOS_VALIDOS', ['texto', 'imagem', 'documento', 'audio', 'botoes']);
define('PRIORIDADES_VALIDAS', ['baixa', 'normal', 'alta', 'urgente']);
