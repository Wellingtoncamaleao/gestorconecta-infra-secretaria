<?php
/**
 * SECRETARIA — Funcoes auxiliares
 * Auth, Supabase REST, Evolution API, validacao
 */

require_once __DIR__ . '/config.php';

// Cache em memoria para origens (evita query repetida na mesma request)
$_origensCache = null;

// ============================================================
// Resposta JSON padrao
// ============================================================
function responderJson($dados, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderErro($mensagem, $codigo, $httpCode = 400) {
    responderJson([
        'sucesso' => false,
        'erro' => $mensagem,
        'codigo' => $codigo
    ], $httpCode);
}

// ============================================================
// Supabase REST helper (padrao assistente)
// ============================================================
function supabaseFetch($caminho, $opcoes = []) {
    $metodo = $opcoes['metodo'] ?? 'GET';
    $corpo = $opcoes['corpo'] ?? null;
    $headersExtra = $opcoes['headers'] ?? [];

    $headers = array_merge([
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ], $headersExtra);

    $url = SUPABASE_URL . '/rest/v1/' . $caminho;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $metodo,
    ]);

    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
    }

    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
        'dados' => json_decode($resposta, true),
        'erro_curl' => $erro ?: null
    ];
}

// ============================================================
// Autenticacao via X-Api-Key
// ============================================================
function autenticar() {
    global $_origensCache;

    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey)) {
        responderErro('Header X-Api-Key obrigatorio', 'AUTH_MISSING', 401);
    }

    // Cache: carregar origens uma vez
    if ($_origensCache === null) {
        $result = supabaseFetch('secretaria_origens?ativo=eq.true&select=nome,api_key');
        $_origensCache = $result['ok'] ? $result['dados'] : [];
    }

    // Buscar origem pela api_key (timing-safe)
    foreach ($_origensCache as $origem) {
        if (hash_equals($origem['api_key'], $apiKey)) {
            return $origem['nome'];
        }
    }

    responderErro('Api key invalida', 'AUTH_INVALID', 401);
}

// ============================================================
// Validacao de payload
// ============================================================
function validarPayload($body) {
    $erros = [];

    if (empty($body['destino'])) {
        $erros[] = 'Campo "destino" obrigatorio';
    }

    $tipo = $body['tipo'] ?? 'texto';
    if (!in_array($tipo, TIPOS_VALIDOS, true)) {
        $erros[] = 'Tipo invalido. Aceitos: ' . implode(', ', TIPOS_VALIDOS);
    }

    if (empty($body['conteudo']) || !is_array($body['conteudo'])) {
        $erros[] = 'Campo "conteudo" obrigatorio (objeto)';
    } else {
        switch ($tipo) {
            case 'texto':
                if (empty($body['conteudo']['texto'])) {
                    $erros[] = 'conteudo.texto obrigatorio para tipo "texto"';
                }
                break;
            case 'imagem':
            case 'documento':
            case 'audio':
                if (empty($body['conteudo']['url'])) {
                    $erros[] = 'conteudo.url obrigatorio para tipo "' . $tipo . '"';
                } else {
                    $mediaUrl = $body['conteudo']['url'];
                    $parsedMedia = parse_url($mediaUrl);
                    if (!$parsedMedia || !in_array(strtolower($parsedMedia['scheme'] ?? ''), ['http', 'https'], true)) {
                        $erros[] = 'conteudo.url deve ser HTTP ou HTTPS';
                    }
                }
                break;
            case 'botoes':
                if (empty($body['conteudo']['texto'])) {
                    $erros[] = 'conteudo.texto obrigatorio para tipo "botoes"';
                }
                if (empty($body['conteudo']['botoes']) || !is_array($body['conteudo']['botoes'])) {
                    $erros[] = 'conteudo.botoes obrigatorio (array) para tipo "botoes"';
                }
                break;
        }
    }

    $prioridade = $body['prioridade'] ?? 'normal';
    if (!in_array($prioridade, PRIORIDADES_VALIDAS, true)) {
        $erros[] = 'Prioridade invalida. Aceitas: ' . implode(', ', PRIORIDADES_VALIDAS);
    }

    if (!empty($erros)) {
        responderErro(implode('; ', $erros), 'VALIDACAO', 422);
    }

    return true;
}

// ============================================================
// Formatar telefone para padrao internacional
// ============================================================
function formatarTelefone($numero) {
    $limpo = preg_replace('/\D/', '', $numero);

    // Auto-prefixar 55 para numeros brasileiros (10-11 digitos)
    if (strlen($limpo) === 11 || strlen($limpo) === 10) {
        $limpo = '55' . $limpo;
    }

    // E.164: minimo 10, maximo 15 digitos
    if (strlen($limpo) < 10 || strlen($limpo) > 15) {
        responderErro('Numero de telefone invalido (espera-se 10-15 digitos)', 'TELEFONE_INVALIDO', 422);
    }

    return $limpo;
}

// ============================================================
// Evolution API — Envio por tipo
// ============================================================
function evolutionRequest($endpoint, $payload) {
    $url = rtrim(EVOLUTION_URL, '/') . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . EVOLUTION_APIKEY,
        ],
    ]);

    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    $dados = json_decode($resposta, true);

    return [
        'sucesso' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'message_id' => $dados['key']['id'] ?? null,
        'dados' => $dados,
        'erro' => $erro ?: ($dados['message'] ?? null)
    ];
}

function evolutionEnviarTexto($destino, $texto) {
    // Dividir mensagens longas
    $partes = mb_str_split_safe($texto, MAX_MENSAGEM_TEXTO);
    $resultado = null;

    foreach ($partes as $parte) {
        $resultado = evolutionRequest('/message/sendText/' . EVOLUTION_INSTANCE, [
            'number' => $destino,
            'text' => $parte
        ]);

        if (!$resultado['sucesso']) {
            return $resultado;
        }
    }

    return $resultado;
}

function evolutionEnviarImagem($destino, $url, $legenda = '') {
    return evolutionRequest('/message/sendMedia/' . EVOLUTION_INSTANCE, [
        'number' => $destino,
        'mediatype' => 'image',
        'media' => $url,
        'caption' => $legenda
    ]);
}

function evolutionEnviarDocumento($destino, $url, $nomeArquivo = '', $legenda = '') {
    return evolutionRequest('/message/sendMedia/' . EVOLUTION_INSTANCE, [
        'number' => $destino,
        'mediatype' => 'document',
        'media' => $url,
        'caption' => $legenda,
        'fileName' => $nomeArquivo
    ]);
}

function evolutionEnviarAudio($destino, $url) {
    return evolutionRequest('/message/sendWhatsAppAudio/' . EVOLUTION_INSTANCE, [
        'number' => $destino,
        'audio' => $url
    ]);
}

function evolutionEnviarBotoes($destino, $texto, $botoes) {
    $botoesFormatados = array_map(function ($b) {
        return [
            'buttonId' => $b['id'] ?? uniqid(),
            'buttonText' => ['displayText' => $b['texto'] ?? $b['text'] ?? '']
        ];
    }, $botoes);

    return evolutionRequest('/message/sendButtons/' . EVOLUTION_INSTANCE, [
        'number' => $destino,
        'title' => '',
        'description' => $texto,
        'buttons' => $botoesFormatados
    ]);
}

// ============================================================
// Despachar envio conforme tipo
// ============================================================
function despacharEnvio($destino, $tipo, $conteudo) {
    switch ($tipo) {
        case 'texto':
            return evolutionEnviarTexto($destino, $conteudo['texto']);

        case 'imagem':
            return evolutionEnviarImagem($destino, $conteudo['url'], $conteudo['legenda'] ?? '');

        case 'documento':
            return evolutionEnviarDocumento(
                $destino,
                $conteudo['url'],
                $conteudo['nome_arquivo'] ?? '',
                $conteudo['legenda'] ?? ''
            );

        case 'audio':
            return evolutionEnviarAudio($destino, $conteudo['url']);

        case 'botoes':
            return evolutionEnviarBotoes($destino, $conteudo['texto'], $conteudo['botoes']);

        default:
            return ['sucesso' => false, 'erro' => 'Tipo desconhecido: ' . $tipo, 'message_id' => null];
    }
}

// ============================================================
// Registrar mensagem no Supabase
// ============================================================
function registrarMensagem($dados) {
    return supabaseFetch('secretaria_mensagens', [
        'metodo' => 'POST',
        'corpo' => $dados
    ]);
}

// ============================================================
// Atualizar status de mensagem
// ============================================================
function atualizarMensagem($id, $campos) {
    return supabaseFetch('secretaria_mensagens?id=eq.' . urlencode($id), [
        'metodo' => 'PATCH',
        'corpo' => $campos
    ]);
}

// ============================================================
// Validar URL externa (anti-SSRF)
// ============================================================
function validarUrlExterna($url) {
    if (empty($url)) return false;

    $parsed = parse_url($url);
    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
        return false;
    }

    // Apenas HTTP(S)
    if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
        return false;
    }

    $host = strtolower($parsed['host']);

    // Bloquear localhost e variantes
    $bloqueados = ['localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]'];
    if (in_array($host, $bloqueados, true)) {
        return false;
    }

    // Bloquear IPs privados (RFC 1918) e link-local
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
    }

    // Bloquear metadata de cloud (169.254.169.254)
    if ($ip === '169.254.169.254') {
        return false;
    }

    return true;
}

// ============================================================
// Callback async (fire-and-forget)
// ============================================================
function dispararCallback($url, $dados) {
    if (empty($url)) return;

    // Protecao SSRF: so permite URLs externas
    if (!validarUrlExterna($url)) {
        error_log('[SECRETARIA] Callback bloqueado (SSRF): ' . $url);
        return;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ============================================================
// Helper: dividir texto longo sem quebrar palavras
// ============================================================
function mb_str_split_safe($texto, $maxLen) {
    if (mb_strlen($texto) <= $maxLen) {
        return [$texto];
    }

    $partes = [];
    while (mb_strlen($texto) > 0) {
        if (mb_strlen($texto) <= $maxLen) {
            $partes[] = $texto;
            break;
        }

        $corte = mb_strrpos(mb_substr($texto, 0, $maxLen), "\n");
        if ($corte === false || $corte < $maxLen * 0.5) {
            $corte = mb_strrpos(mb_substr($texto, 0, $maxLen), ' ');
        }
        if ($corte === false || $corte < $maxLen * 0.3) {
            $corte = $maxLen;
        }

        $partes[] = mb_substr($texto, 0, $corte);
        $texto = ltrim(mb_substr($texto, $corte));
    }

    return $partes;
}

// ============================================================
// Rate limiting simples (baseado em arquivo tmp)
// ============================================================
function verificarRateLimit($origem, $maxPorMinuto = 30) {
    $arquivo = sys_get_temp_dir() . '/secretaria_rate_' . md5($origem) . '.json';

    $agora = time();
    $dados = [];

    if (file_exists($arquivo)) {
        $conteudo = @file_get_contents($arquivo);
        $dados = json_decode($conteudo, true) ?: [];
    }

    // Limpar entradas com mais de 60s
    $dados = array_filter($dados, fn($ts) => ($agora - $ts) < 60);

    if (count($dados) >= $maxPorMinuto) {
        responderErro(
            'Rate limit excedido (' . $maxPorMinuto . '/min). Tente novamente em breve.',
            'RATE_LIMIT',
            429
        );
    }

    $dados[] = $agora;
    @file_put_contents($arquivo, json_encode(array_values($dados)), LOCK_EX);
}

// ============================================================
// Ler body JSON da request
// ============================================================
function lerBodyJson() {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if ($body === null && !empty($raw)) {
        responderErro('Body JSON invalido', 'JSON_INVALIDO', 400);
    }

    return $body ?: [];
}

// ============================================================
// Validar metodo HTTP
// ============================================================
function exigirMetodo($metodo) {
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        responderErro('Metodo ' . $_SERVER['REQUEST_METHOD'] . ' nao permitido. Use ' . $metodo, 'METODO_INVALIDO', 405);
    }
}
