<?php
/**
 * SECRETARIA — GET /api/log.php
 * Historico de mensagens com filtros
 *
 * Params: origem, destino, status, de, ate, ultimas (ex: "24h", "7d"), limite
 */

require_once __DIR__ . '/functions.php';

exigirMetodo('GET');
autenticar();

// Montar filtros para Supabase
$filtros = [];
$params = [];

if (!empty($_GET['origem'])) {
    $filtros[] = 'origem_nome=eq.' . urlencode($_GET['origem']);
}

if (!empty($_GET['destino'])) {
    $filtros[] = 'destino=eq.' . urlencode($_GET['destino']);
}

if (!empty($_GET['status'])) {
    $filtros[] = 'status=eq.' . urlencode($_GET['status']);
}

if (!empty($_GET['de'])) {
    $filtros[] = 'criado_em=gte.' . urlencode($_GET['de']);
}

if (!empty($_GET['ate'])) {
    $filtros[] = 'criado_em=lte.' . urlencode($_GET['ate']);
}

// "ultimas" = atalho temporal (24h, 7d, 30d)
if (!empty($_GET['ultimas'])) {
    $ultimas = $_GET['ultimas'];
    $agora = time();

    if (preg_match('/^(\d+)h$/i', $ultimas, $m)) {
        $desde = date('c', $agora - ($m[1] * 3600));
    } elseif (preg_match('/^(\d+)d$/i', $ultimas, $m)) {
        $desde = date('c', $agora - ($m[1] * 86400));
    } elseif (preg_match('/^(\d+)m$/i', $ultimas, $m)) {
        $desde = date('c', $agora - ($m[1] * 2592000));
    }

    if (!empty($desde)) {
        $filtros[] = 'criado_em=gte.' . urlencode($desde);
    }
}

$limite = min((int)($_GET['limite'] ?? 50), 200);

// Montar query
$query = 'secretaria_mensagens?select=id,origem_nome,destino,tipo,status,prioridade,metadata,criado_em,enviado_em,erro_detalhe';
$query .= '&order=criado_em.desc';
$query .= '&limit=' . $limite;

if (!empty($filtros)) {
    $query .= '&' . implode('&', $filtros);
}

$resultado = supabaseFetch($query);

if (!$resultado['ok']) {
    responderErro('Erro ao consultar log', 'SUPABASE_ERRO', 500);
}

responderJson([
    'sucesso' => true,
    'total' => count($resultado['dados'] ?? []),
    'mensagens' => $resultado['dados'] ?? []
]);
