<?php
/**
 * SECRETARIA — POST /api/enviar.php
 * Endpoint principal: recebe mensagem, valida, envia via Evolution, loga no Supabase
 */

require_once __DIR__ . '/functions.php';

// Apenas POST
exigirMetodo('POST');

// 1. Autenticar
$origemAuth = autenticar();

// 2. Ler body
$body = lerBodyJson();

// Usar origem do auth (ignora campo origem do body — seguranca)
$body['origem'] = $origemAuth;

// 3. Validar
validarPayload($body);

// 4. Preparar dados
$destino = formatarTelefone($body['destino']);
$tipo = $body['tipo'] ?? 'texto';
$conteudo = $body['conteudo'];
$prioridade = $body['prioridade'] ?? 'normal';
$metadata = $body['metadata'] ?? [];
$callbackUrl = $body['callback_url'] ?? null;
$agendarPara = $body['agendar_para'] ?? null;

// 5. Gerar ID da mensagem
$msgId = null;

// 6. Se agendado no futuro: salvar e retornar 202
if (!empty($agendarPara)) {
    $dataAgendada = strtotime($agendarPara);
    if ($dataAgendada && $dataAgendada > time()) {
        $registro = registrarMensagem([
            'origem_nome' => $origemAuth,
            'destino' => $destino,
            'tipo' => $tipo,
            'conteudo' => $conteudo,
            'prioridade' => $prioridade,
            'status' => 'agendado',
            'metadata' => $metadata,
            'callback_url' => $callbackUrl,
            'agendar_para' => date('c', $dataAgendada)
        ]);

        $msgId = $registro['dados'][0]['id'] ?? null;

        responderJson([
            'sucesso' => true,
            'id' => $msgId,
            'status' => 'agendado',
            'enviar_em' => date('c', $dataAgendada)
        ], 202);
    }
}

// 7. Envio imediato
$resultado = despacharEnvio($destino, $tipo, $conteudo);

$status = $resultado['sucesso'] ? 'enviado' : 'erro';
$agora = date('c');

// 8. Registrar no Supabase
$registro = registrarMensagem([
    'origem_nome' => $origemAuth,
    'destino' => $destino,
    'tipo' => $tipo,
    'conteudo' => $conteudo,
    'prioridade' => $prioridade,
    'status' => $status,
    'evolution_message_id' => $resultado['message_id'],
    'erro_detalhe' => $resultado['sucesso'] ? null : ($resultado['erro'] ?? 'Erro desconhecido'),
    'metadata' => $metadata,
    'callback_url' => $callbackUrl,
    'enviado_em' => $resultado['sucesso'] ? $agora : null
]);

$msgId = $registro['dados'][0]['id'] ?? null;

// 9. Callback se configurado
if (!empty($callbackUrl)) {
    dispararCallback($callbackUrl, [
        'id' => $msgId,
        'status' => $status,
        'evolution_message_id' => $resultado['message_id'],
        'timestamp' => $agora
    ]);
}

// 10. Resposta
if ($resultado['sucesso']) {
    responderJson([
        'sucesso' => true,
        'id' => $msgId,
        'status' => 'enviado',
        'evolution_message_id' => $resultado['message_id'],
        'timestamp' => $agora
    ], 201);
} else {
    responderJson([
        'sucesso' => false,
        'id' => $msgId,
        'status' => 'erro',
        'erro' => $resultado['erro'] ?? 'Falha ao enviar via Evolution API',
        'codigo' => 'EVOLUTION_ERRO'
    ], 502);
}
