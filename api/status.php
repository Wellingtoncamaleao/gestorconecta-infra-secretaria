<?php
/**
 * SECRETARIA — GET /api/status.php?id=xxx
 * Status detalhado de uma mensagem
 */

require_once __DIR__ . '/functions.php';

exigirMetodo('GET');
autenticar();

$id = $_GET['id'] ?? '';

if (empty($id)) {
    responderErro('Parametro "id" obrigatorio', 'CAMPO_OBRIGATORIO', 400);
}

$resultado = supabaseFetch('secretaria_mensagens?id=eq.' . urlencode($id) . '&limit=1');

if (!$resultado['ok']) {
    responderErro('Erro ao consultar mensagem', 'SUPABASE_ERRO', 500);
}

if (empty($resultado['dados'])) {
    responderErro('Mensagem nao encontrada', 'NAO_ENCONTRADA', 404);
}

$msg = $resultado['dados'][0];

responderJson([
    'sucesso' => true,
    'id' => $msg['id'],
    'origem' => $msg['origem_nome'],
    'destino' => $msg['destino'],
    'tipo' => $msg['tipo'],
    'conteudo' => $msg['conteudo'],
    'prioridade' => $msg['prioridade'],
    'status' => $msg['status'],
    'evolution_message_id' => $msg['evolution_message_id'],
    'erro_detalhe' => $msg['erro_detalhe'],
    'metadata' => $msg['metadata'],
    'agendar_para' => $msg['agendar_para'],
    'enviado_em' => $msg['enviado_em'],
    'criado_em' => $msg['criado_em']
]);
