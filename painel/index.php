<?php
/**
 * SECRETARIA — Painel Admin
 * Login simples + dashboard de historico de mensagens
 */
session_start();

$senha = getenv('PAINEL_SENHA') ?: '';
$apiKey = getenv('PAINEL_API_KEY') ?: '';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /painel/');
    exit;
}

// Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    if (!empty($senha) && hash_equals($senha, $_POST['senha'])) {
        $_SESSION['painel_auth'] = true;
        header('Location: /painel/');
        exit;
    }
    $erroLogin = true;
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
        <?php if (!empty($erroLogin)): ?>
            <div class="erro">Senha incorreta</div>
        <?php endif; ?>
        <form method="POST">
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
    <script>
        // API key injetada pelo PHP (nunca exposta no codigo-fonte)
        const API_KEY = <?= json_encode($apiKey) ?>;
    </script>

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
