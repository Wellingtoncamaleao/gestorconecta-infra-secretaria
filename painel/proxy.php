<?php
/**
 * SECRETARIA — Proxy do Painel
 * Repassa chamadas para /api/ sem expor a API key no browser
 */
session_start();

// Verificar autenticacao + expiracao
if (empty($_SESSION['painel_auth']) || (!empty($_SESSION['painel_expires']) && $_SESSION['painel_expires'] < time())) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'erro' => 'Nao autenticado']);
    exit;
}

$apiKey = getenv('PAINEL_API_KEY') ?: '';
if (empty($apiKey)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'erro' => 'PAINEL_API_KEY nao configurada']);
    exit;
}

// Determinar endpoint
$acao = $_GET['acao'] ?? '';
$acoesPermitidas = ['log', 'status'];

if (!in_array($acao, $acoesPermitidas, true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'erro' => 'Acao invalida']);
    exit;
}

// Montar URL interna
$params = $_GET;
unset($params['acao']);
$queryString = http_build_query($params);
$url = 'http://127.0.0.1/api/' . $acao . '.php' . ($queryString ? '?' . $queryString : '');

// Fazer request interno
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-Api-Key: ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
header('Content-Type: application/json; charset=utf-8');
echo $resposta;
