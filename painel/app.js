/**
 * SECRETARIA — Painel Admin (app.js)
 * Dashboard de historico de comunicacoes
 */

// Estado global
let mensagens = [];
let paginaAtual = 1;
let periodoAtivo = '24h';
const LIMITE = 50;

// Origens conhecidas (preenche dropdown)
const ORIGENS = [
    'monitor-projetos', 'n8n-pedidos', 'n8n-meta-ads', 'n8n-cobranca',
    'control', 'loyal', 'alerta-licitacao', 'prontuario',
    'darkflow', 'monitor-fabrica', 'backup', 'recupera',
    'sympla-scraper', 'assistente', 'chat-camaleao', 'teste'
];

// ============================================================
// Init
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    preencherOrigens();
    bindEventos();
    carregarMensagens();
});

function preencherOrigens() {
    var select = document.getElementById('filtro-origem');
    for (var i = 0; i < ORIGENS.length; i++) {
        var opt = document.createElement('option');
        opt.value = ORIGENS[i];
        opt.textContent = ORIGENS[i];
        select.appendChild(opt);
    }
}

function bindEventos() {
    // Filtrar
    document.getElementById('btn-filtrar').addEventListener('click', function () {
        paginaAtual = 1;
        carregarMensagens();
    });

    // Atalhos de periodo
    var btns = document.querySelectorAll('.btn-atalho');
    for (var i = 0; i < btns.length; i++) {
        btns[i].addEventListener('click', function () {
            // Desativar todos
            var todos = document.querySelectorAll('.btn-atalho');
            for (var j = 0; j < todos.length; j++) {
                todos[j].classList.remove('active');
            }
            this.classList.add('active');
            periodoAtivo = this.dataset.periodo;

            // Limpar date inputs quando usa atalho
            document.getElementById('filtro-de').value = '';
            document.getElementById('filtro-ate').value = '';

            paginaAtual = 1;
            carregarMensagens();
        });
    }

    // Paginacao
    document.getElementById('btn-anterior').addEventListener('click', function () {
        if (paginaAtual > 1) {
            paginaAtual--;
            carregarMensagens();
        }
    });

    document.getElementById('btn-proximo').addEventListener('click', function () {
        paginaAtual++;
        carregarMensagens();
    });

    // Modal fechar
    document.getElementById('modal-fechar').addEventListener('click', fecharModal);
    document.getElementById('modal-overlay').addEventListener('click', function (e) {
        if (e.target === this) fecharModal();
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') fecharModal();
    });

    // Quando digita data, desativa atalhos
    document.getElementById('filtro-de').addEventListener('change', desativarAtalhos);
    document.getElementById('filtro-ate').addEventListener('change', desativarAtalhos);
}

function desativarAtalhos() {
    periodoAtivo = '';
    var btns = document.querySelectorAll('.btn-atalho');
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.remove('active');
    }
}

// ============================================================
// Carregar mensagens
// ============================================================
function carregarMensagens() {
    var params = [];
    var origem = document.getElementById('filtro-origem').value;
    var status = document.getElementById('filtro-status').value;
    var de = document.getElementById('filtro-de').value;
    var ate = document.getElementById('filtro-ate').value;

    if (origem) params.push('origem=' + encodeURIComponent(origem));
    if (status) params.push('status=' + encodeURIComponent(status));

    // Atalho de periodo tem prioridade sobre date inputs
    if (periodoAtivo) {
        params.push('ultimas=' + periodoAtivo);
    } else {
        if (de) params.push('de=' + encodeURIComponent(de + 'T00:00:00'));
        if (ate) params.push('ate=' + encodeURIComponent(ate + 'T23:59:59'));
    }

    params.push('limite=' + LIMITE);

    // Offset para paginacao
    if (paginaAtual > 1) {
        params.push('offset=' + ((paginaAtual - 1) * LIMITE));
    }

    var url = '/api/log.php?' + params.join('&');

    fetch(url, {
        headers: { 'X-Api-Key': API_KEY }
    })
        .then(function (resp) { return resp.json(); })
        .then(function (dados) {
            if (dados.sucesso) {
                mensagens = dados.mensagens || [];
                renderizarTabela();
                atualizarPaginacao(dados.total || 0);
                atualizarStats();
            } else {
                mostrarVazio('Erro ao carregar: ' + (dados.erro || 'desconhecido'));
            }
        })
        .catch(function (err) {
            mostrarVazio('Erro de conexao: ' + err.message);
        });
}

// ============================================================
// Renderizar tabela
// ============================================================
function renderizarTabela() {
    var tbody = document.getElementById('tbody-mensagens');

    if (mensagens.length === 0) {
        mostrarVazio('Nenhuma mensagem encontrada');
        return;
    }

    var html = '';
    for (var i = 0; i < mensagens.length; i++) {
        var m = mensagens[i];
        var preview = extrairPreview(m);
        var data = formatarData(m.criado_em);

        html += '<tr class="linha" data-index="' + i + '">'
            + '<td class="col-data">' + escapeHtml(data) + '</td>'
            + '<td class="col-origem">' + escapeHtml(m.origem_nome || '') + '</td>'
            + '<td class="col-destino">' + formatarTelefone(m.destino || '') + '</td>'
            + '<td class="col-tipo">' + escapeHtml(m.tipo || '') + '</td>'
            + '<td>' + badgeStatus(m.status) + '</td>'
            + '<td class="col-preview" title="' + escapeAttr(preview) + '">' + escapeHtml(preview) + '</td>'
            + '</tr>';
    }

    tbody.innerHTML = html;

    // Bind clique nas linhas
    var linhas = tbody.querySelectorAll('.linha');
    for (var j = 0; j < linhas.length; j++) {
        linhas[j].addEventListener('click', function () {
            var idx = parseInt(this.dataset.index);
            abrirModal(mensagens[idx]);
        });
    }
}

function mostrarVazio(texto) {
    document.getElementById('tbody-mensagens').innerHTML =
        '<tr><td colspan="6" class="vazio">' + escapeHtml(texto) + '</td></tr>';
}

// ============================================================
// Paginacao
// ============================================================
function atualizarPaginacao(total) {
    var totalPaginas = Math.max(1, Math.ceil(total / LIMITE));
    document.getElementById('pagina-info').textContent = 'Pagina ' + paginaAtual + ' de ' + totalPaginas;
    document.getElementById('btn-anterior').disabled = (paginaAtual <= 1);
    document.getElementById('btn-proximo').disabled = (paginaAtual >= totalPaginas);
}

// ============================================================
// Stats (24h)
// ============================================================
function atualizarStats() {
    fetch('/api/log.php?ultimas=24h&limite=200', {
        headers: { 'X-Api-Key': API_KEY }
    })
        .then(function (resp) { return resp.json(); })
        .then(function (dados) {
            if (!dados.sucesso) return;
            var msgs = dados.mensagens || [];
            var total = msgs.length;
            var enviados = 0;
            var erros = 0;
            for (var i = 0; i < msgs.length; i++) {
                if (msgs[i].status === 'enviado' || msgs[i].status === 'entregue' || msgs[i].status === 'lido') enviados++;
                if (msgs[i].status === 'erro') erros++;
            }
            document.getElementById('stat-total').textContent = total + ' total (24h)';
            document.getElementById('stat-enviado').textContent = enviados + ' enviados';
            document.getElementById('stat-erro').textContent = erros + ' erros';
        })
        .catch(function () { });
}

// ============================================================
// Modal
// ============================================================
function abrirModal(m) {
    var body = document.getElementById('modal-body');
    var conteudo = '';

    try {
        conteudo = JSON.stringify(typeof m.conteudo === 'string' ? JSON.parse(m.conteudo) : m.conteudo, null, 2);
    } catch (e) {
        conteudo = String(m.conteudo || '');
    }

    var metadata = '';
    try {
        metadata = JSON.stringify(typeof m.metadata === 'string' ? JSON.parse(m.metadata) : m.metadata, null, 2);
    } catch (e) {
        metadata = String(m.metadata || '{}');
    }

    var html = ''
        + campo('ID', m.id)
        + campo('Status', badgeStatus(m.status))
        + campo('Origem', m.origem_nome)
        + campo('Destino', formatarTelefone(m.destino))
        + campo('Tipo', m.tipo)
        + campo('Prioridade', m.prioridade)
        + campo('Conteudo', '<pre>' + escapeHtml(conteudo) + '</pre>')
        + campo('Criado em', formatarData(m.criado_em))
        + campo('Enviado em', m.enviado_em ? formatarData(m.enviado_em) : '--');

    if (m.evolution_message_id) {
        html += campo('Evolution ID', '<code>' + escapeHtml(m.evolution_message_id) + '</code>');
    }

    if (m.erro_detalhe) {
        html += campo('Erro', '<span class="erro-texto">' + escapeHtml(m.erro_detalhe) + '</span>');
    }

    if (m.agendar_para) {
        html += campo('Agendado para', formatarData(m.agendar_para));
    }

    if (metadata && metadata !== '{}' && metadata !== 'null') {
        html += campo('Metadata', '<pre>' + escapeHtml(metadata) + '</pre>');
    }

    body.innerHTML = html;
    document.getElementById('modal-overlay').classList.add('aberto');
}

function fecharModal() {
    document.getElementById('modal-overlay').classList.remove('aberto');
}

function campo(label, valor) {
    return '<div class="campo">'
        + '<div class="campo-label">' + escapeHtml(label) + '</div>'
        + '<div class="campo-valor">' + valor + '</div>'
        + '</div>';
}

// ============================================================
// Helpers
// ============================================================
function badgeStatus(status) {
    var classe = 'badge badge-' + (status || 'pendente');
    return '<span class="' + classe + '">' + escapeHtml(status || 'pendente') + '</span>';
}

function extrairPreview(m) {
    if (!m.conteudo) return '--';
    var c = typeof m.conteudo === 'string' ? JSON.parse(m.conteudo) : m.conteudo;
    if (c.texto) return c.texto.substring(0, 80);
    if (c.url) return c.url.substring(0, 60);
    if (c.legenda) return c.legenda.substring(0, 60);
    return '--';
}

function formatarData(iso) {
    if (!iso) return '--';
    var d = new Date(iso);
    var dia = String(d.getDate()).padStart(2, '0');
    var mes = String(d.getMonth() + 1).padStart(2, '0');
    var hora = String(d.getHours()).padStart(2, '0');
    var min = String(d.getMinutes()).padStart(2, '0');
    return dia + '/' + mes + ' ' + hora + ':' + min;
}

function formatarTelefone(tel) {
    if (!tel || tel.length < 11) return escapeHtml(tel);
    // 5589981201204 -> +55 (89) 98120-1204
    var ddi = tel.substring(0, 2);
    var ddd = tel.substring(2, 4);
    var parte1 = tel.substring(4, 9);
    var parte2 = tel.substring(9);
    return '+' + ddi + ' (' + ddd + ') ' + parte1 + '-' + parte2;
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttr(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
