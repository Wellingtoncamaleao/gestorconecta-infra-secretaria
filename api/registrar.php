<?php
/**
 * SECRETARIA — POST /api/registrar.php
 * Registra evento no log SEM enviar WhatsApp.
 * Usado pelo cron-scheduler e outros servicos internos.
 */

require_once __DIR__ . '/functions.php';

exigirMetodo('POST');

// 1. Autenticar
$origemAuth = autenticar();

// 2. Rate limiting (60/min — logs sao mais frequentes)
verificarRateLimit($origemAuth, 60);

// 3. Ler body
$body = lerBodyJson();

// 4. Validar campos obrigatorios
$titulo = trim($body['titulo'] ?? '');
$statusEvento = $body['status'] ?? 'ok';
$detalhes = trim($body['detalhes'] ?? '');
$duracaoMs = (int) ($body['duracao_ms'] ?? 0);
$metadata = $body['metadata'] ?? [];

if (empty($titulo)) {
    responderErro('Campo "titulo" obrigatorio', 'VALIDACAO', 422);
}

if (!in_array($statusEvento, ['ok', 'erro'], true)) {
    responderErro('Campo "status" deve ser "ok" ou "erro"', 'VALIDACAO', 422);
}

// 5. Montar texto do log
$textoLog = $titulo;
if (!empty($detalhes)) {
    $textoLog .= ' — ' . $detalhes;
}
if ($duracaoMs > 0) {
    $textoLog .= ' (' . $duracaoMs . 'ms)';
}

// 6. Inserir na secretaria_mensagens (reusa tabela existente)
$registro = registrarMensagem([
    'origem_nome' => $origemAuth,
    'destino'     => 'sistema',
    'tipo'        => 'texto',
    'conteudo'    => ['texto' => $textoLog],
    'prioridade'  => 'baixa',
    'status'      => $statusEvento === 'ok' ? 'enviado' : 'erro',
    'erro_detalhe' => $statusEvento === 'erro' ? ($detalhes ?: 'Erro sem detalhes') : null,
    'metadata'    => array_merge($metadata, [
        'cron_log'    => true,
        'duracao_ms'  => $duracaoMs,
    ]),
    'enviado_em'  => date('c'),
]);

$msgId = $registro['dados'][0]['id'] ?? null;

responderJson([
    'sucesso' => true,
    'id'      => $msgId,
    'status'  => 'registrado',
], 201);
