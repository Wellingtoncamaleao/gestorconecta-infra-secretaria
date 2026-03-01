<?php
/**
 * SECRETARIA — POST /api/enviar-lote.php
 * Envio em massa com intervalo entre mensagens (anti-ban)
 */

require_once __DIR__ . '/functions.php';

exigirMetodo('POST');
$origemAuth = autenticar();

$body = lerBodyJson();

$mensagens = $body['mensagens'] ?? [];
$intervaloMs = $body['intervalo_ms'] ?? INTERVALO_LOTE_MS;

if (empty($mensagens) || !is_array($mensagens)) {
    responderErro('Campo "mensagens" obrigatorio (array)', 'VALIDACAO', 422);
}

if (count($mensagens) > MAX_LOTE) {
    responderErro('Maximo de ' . MAX_LOTE . ' mensagens por lote', 'LIMITE_LOTE', 422);
}

$intervaloSeg = max(1, $intervaloMs / 1000);
$resultados = [];

foreach ($mensagens as $i => $msg) {
    $msg['origem'] = $origemAuth;
    $destino = formatarTelefone($msg['destino'] ?? '');
    $tipo = $msg['tipo'] ?? 'texto';
    $conteudo = $msg['conteudo'] ?? [];
    $metadata = $msg['metadata'] ?? [];

    // Validacao basica
    if (empty($destino) || empty($conteudo)) {
        $resultados[] = [
            'indice' => $i,
            'sucesso' => false,
            'erro' => 'destino ou conteudo vazio'
        ];
        continue;
    }

    // Enviar
    $resultado = despacharEnvio($destino, $tipo, $conteudo);
    $status = $resultado['sucesso'] ? 'enviado' : 'erro';

    // Registrar
    $registro = registrarMensagem([
        'origem_nome' => $origemAuth,
        'destino' => $destino,
        'tipo' => $tipo,
        'conteudo' => $conteudo,
        'prioridade' => $msg['prioridade'] ?? 'normal',
        'status' => $status,
        'evolution_message_id' => $resultado['message_id'],
        'erro_detalhe' => $resultado['sucesso'] ? null : ($resultado['erro'] ?? null),
        'metadata' => $metadata,
        'enviado_em' => $resultado['sucesso'] ? date('c') : null
    ]);

    $resultados[] = [
        'indice' => $i,
        'id' => $registro['dados'][0]['id'] ?? null,
        'sucesso' => $resultado['sucesso'],
        'destino' => $destino,
        'erro' => $resultado['sucesso'] ? null : $resultado['erro']
    ];

    // Intervalo entre envios (exceto ultimo)
    if ($i < count($mensagens) - 1) {
        usleep((int)($intervaloSeg * 1000000));
    }
}

$enviados = count(array_filter($resultados, fn($r) => $r['sucesso']));
$erros = count($resultados) - $enviados;

responderJson([
    'sucesso' => $erros === 0,
    'total' => count($resultados),
    'enviados' => $enviados,
    'erros' => $erros,
    'resultados' => $resultados
], $erros === 0 ? 201 : 207);
