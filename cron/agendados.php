<?php
/**
 * SECRETARIA — Cron: processar mensagens agendadas
 * Roda a cada minuto via crontab do container
 */

require_once __DIR__ . '/../api/functions.php';

$agora = date('c');
echo "[" . $agora . "] Verificando mensagens agendadas...\n";

// Buscar mensagens agendadas cujo horario ja passou
$resultado = supabaseFetch(
    'secretaria_mensagens?status=eq.agendado&agendar_para=lte.' . urlencode($agora) . '&order=agendar_para.asc&limit=20'
);

if (!$resultado['ok']) {
    echo "ERRO: Falha ao consultar Supabase (HTTP {$resultado['status']})\n";
    exit(1);
}

$mensagens = $resultado['dados'] ?? [];

if (empty($mensagens)) {
    echo "Nenhuma mensagem agendada pendente.\n";
    exit(0);
}

echo count($mensagens) . " mensagem(ns) para enviar.\n";

$enviadas = 0;
$erros = 0;

foreach ($mensagens as $msg) {
    $destino = $msg['destino'];
    $tipo = $msg['tipo'];
    $conteudo = $msg['conteudo'];

    echo "  Enviando {$msg['id']} ({$msg['origem_nome']} -> {$destino})... ";

    $resultado = despacharEnvio($destino, $tipo, $conteudo);

    if ($resultado['sucesso']) {
        atualizarMensagem($msg['id'], [
            'status' => 'enviado',
            'evolution_message_id' => $resultado['message_id'],
            'enviado_em' => date('c')
        ]);
        echo "OK\n";
        $enviadas++;
    } else {
        atualizarMensagem($msg['id'], [
            'status' => 'erro',
            'erro_detalhe' => $resultado['erro'] ?? 'Falha no envio'
        ]);
        echo "ERRO: " . ($resultado['erro'] ?? 'desconhecido') . "\n";
        $erros++;
    }

    // Callback se configurado
    if (!empty($msg['callback_url'])) {
        dispararCallback($msg['callback_url'], [
            'id' => $msg['id'],
            'status' => $resultado['sucesso'] ? 'enviado' : 'erro',
            'timestamp' => date('c')
        ]);
    }

    // Intervalo entre envios
    usleep(500000); // 500ms
}

echo "Concluido: {$enviadas} enviada(s), {$erros} erro(s).\n";
