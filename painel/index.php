<?php
/**
 * SECRETARIA — Painel Admin
 * Login simples + dashboard de historico de mensagens
 */
session_start();

$senha = getenv('PAINEL_SENHA') ?: '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sessao expira em 24h
if (!empty($_SESSION['painel_auth']) && !empty($_SESSION['painel_expires']) && $_SESSION['painel_expires'] < time()) {
    session_destroy();
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /painel/');
    exit;
}

// Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    // Anti brute-force: max 5 tentativas, bloqueio de 15 min
    $tentativas = $_SESSION['login_tentativas'] ?? 0;
    $bloqueioAte = $_SESSION['login_bloqueio'] ?? 0;

    if ($bloqueioAte > time()) {
        $erroLogin = true;
        $erroBloqueio = true;
    } elseif (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
        $erroLogin = true;
    } elseif (!empty($senha) && hash_equals($senha, $_POST['senha'])) {
        session_regenerate_id(true);
        $_SESSION['painel_auth'] = true;
        $_SESSION['painel_expires'] = time() + 86400; // 24h
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        unset($_SESSION['login_tentativas'], $_SESSION['login_bloqueio']);
        header('Location: /painel/');
        exit;
    } else {
        $tentativas++;
        $_SESSION['login_tentativas'] = $tentativas;
        if ($tentativas >= 5) {
            $_SESSION['login_bloqueio'] = time() + 900; // 15 min
        }
        $erroLogin = true;
    }
}

// Se nao logado: mostra login
if (empty($_SESSION['painel_auth'])) {
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria — Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Secretaria</h1>
        <p class="subtitle">Painel de Comunicacoes</p>
        <?php if (!empty($erroBloqueio)): ?>
            <div class="erro">Muitas tentativas. Tente novamente em 15 minutos.</div>
        <?php elseif (!empty($erroLogin)): ?>
            <div class="erro">Senha incorreta</div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="password" name="senha" placeholder="Senha" autofocus required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
    <?php
    exit;
}

// === DASHBOARD (logado) ===
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria — Painel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <h1>Secretaria</h1>
            <span class="subtitle">Painel de Comunicacoes</span>
        </div>
        <div class="header-right">
            <div class="stats" id="stats">
                <span class="stat" id="stat-total">--</span>
                <span class="stat stat-ok" id="stat-enviado">--</span>
                <span class="stat stat-erro" id="stat-erro">--</span>
            </div>
            <a href="?logout" class="btn-logout">Sair</a>
        </div>
    </header>

    <!-- Filtros -->
    <div class="filtros">
        <select id="filtro-origem">
            <option value="">Todas as origens</option>
        </select>
        <select id="filtro-status">
            <option value="">Todos os status</option>
            <option value="enviado">Enviado</option>
            <option value="erro">Erro</option>
            <option value="pendente">Pendente</option>
            <option value="agendado">Agendado</option>
            <option value="entregue">Entregue</option>
            <option value="lido">Lido</option>
        </select>
        <input type="date" id="filtro-de" title="Data inicial">
        <input type="date" id="filtro-ate" title="Data final">
        <div class="atalhos">
            <button class="btn-atalho active" data-periodo="24h">Hoje</button>
            <button class="btn-atalho" data-periodo="7d">7 dias</button>
            <button class="btn-atalho" data-periodo="30d">30 dias</button>
            <button class="btn-atalho" data-periodo="">Tudo</button>
        </div>
        <button class="btn-filtrar" id="btn-filtrar">Filtrar</button>
    </div>

    <!-- Tabela -->
    <div class="tabela-container">
        <table id="tabela-mensagens">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Preview</th>
                </tr>
            </thead>
            <tbody id="tbody-mensagens">
                <tr><td colspan="6" class="vazio">Carregando...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginacao -->
    <div class="paginacao" id="paginacao">
        <button id="btn-anterior" disabled>Anterior</button>
        <span id="pagina-info">Pagina 1</span>
        <button id="btn-proximo">Proximo</button>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>Detalhes da Mensagem</h2>
                <button class="modal-fechar" id="modal-fechar">&times;</button>
            </div>
            <div class="modal-body" id="modal-body">
            </div>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>
