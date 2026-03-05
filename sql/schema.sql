-- ============================================
-- SECRETARIA — Gateway centralizado de mensagens WhatsApp
-- Prefixo: secretaria_
-- Rodar no Supabase SQL Editor
-- ============================================

-- ============================================
-- 1. TABELAS
-- ============================================

-- Origens cadastradas (cada projeto/servico = 1 origem)
CREATE TABLE IF NOT EXISTS secretaria_origens (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    nome text NOT NULL UNIQUE,
    api_key text NOT NULL UNIQUE,
    descricao text,
    ativo boolean DEFAULT true,
    criado_em timestamptz DEFAULT now(),
    atualizado_em timestamptz DEFAULT now()
);

-- Log de todas as mensagens enviadas
CREATE TABLE IF NOT EXISTS secretaria_mensagens (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    origem_nome text NOT NULL,
    destino text NOT NULL,
    tipo text NOT NULL DEFAULT 'texto' CHECK (tipo IN ('texto', 'imagem', 'documento', 'audio', 'botoes')),
    conteudo jsonb NOT NULL,
    prioridade text DEFAULT 'normal' CHECK (prioridade IN ('baixa', 'normal', 'alta', 'urgente')),
    status text DEFAULT 'pendente' CHECK (status IN ('pendente', 'agendado', 'enviado', 'erro', 'entregue', 'lido')),
    evolution_message_id text,
    erro_detalhe text,
    metadata jsonb DEFAULT '{}',
    callback_url text,
    agendar_para timestamptz,
    enviado_em timestamptz,
    criado_em timestamptz DEFAULT now()
);

-- ============================================
-- 2. INDICES
-- ============================================

CREATE INDEX IF NOT EXISTS idx_sec_msg_origem ON secretaria_mensagens(origem_nome);
CREATE INDEX IF NOT EXISTS idx_sec_msg_status ON secretaria_mensagens(status);
CREATE INDEX IF NOT EXISTS idx_sec_msg_agendado ON secretaria_mensagens(agendar_para) WHERE status = 'agendado';
CREATE INDEX IF NOT EXISTS idx_sec_msg_criado ON secretaria_mensagens(criado_em DESC);

-- ============================================
-- 3. ORIGENS INICIAIS
-- ============================================

INSERT INTO secretaria_origens (nome, api_key, descricao) VALUES
    ('monitor-projetos',   'sec_mon_' || substr(gen_random_uuid()::text, 1, 32), 'Monitor diario de projetos parados'),
    ('n8n-pedidos',        'sec_n8n_ped_' || substr(gen_random_uuid()::text, 1, 28), 'Alertas de pedidos atrasados (Camaleao)'),
    ('n8n-meta-ads',       'sec_n8n_meta_' || substr(gen_random_uuid()::text, 1, 27), 'Monitoramento de campanhas Meta'),
    ('n8n-cobranca',       'sec_n8n_cob_' || substr(gen_random_uuid()::text, 1, 28), 'Fluxo de cobranca automatica'),
    ('control',            'sec_ctrl_' || substr(gen_random_uuid()::text, 1, 31), 'ERP GestorConecta Control'),
    ('loyal',              'sec_loyal_' || substr(gen_random_uuid()::text, 1, 30), 'Fidelizacao WhatsApp'),
    ('alerta-licitacao',   'sec_licit_' || substr(gen_random_uuid()::text, 1, 30), 'Alertas de licitacoes publicas'),
    ('prontuario',         'sec_med_' || substr(gen_random_uuid()::text, 1, 32), 'Lembretes de consulta MED'),
    ('darkflow',           'sec_dark_' || substr(gen_random_uuid()::text, 1, 31), 'Notificacao de video publicado'),
    ('monitor-fabrica',    'sec_fab_' || substr(gen_random_uuid()::text, 1, 32), 'Alertas impressora Epson'),
    ('backup',             'sec_bkp_' || substr(gen_random_uuid()::text, 1, 32), 'Confirmacao/erro de backup'),
    ('recupera',           'sec_recup_' || substr(gen_random_uuid()::text, 1, 30), 'Recuperacao de vendas WhatsApp'),
    ('sympla-scraper',     'sec_sympla_' || substr(gen_random_uuid()::text, 1, 29), 'Scraper de eventos Sympla'),
    ('assistente',         'sec_assist_' || substr(gen_random_uuid()::text, 1, 29), 'Assistente IA pessoal'),
    ('chat-camaleao',      'sec_chat_' || substr(gen_random_uuid()::text, 1, 31), 'Atendimento multicanal Camaleao'),
    ('cron-scheduler',     'sec_cron_' || substr(gen_random_uuid()::text, 1, 31), 'Logs de execucao do cron-scheduler'),
    ('teste',              'sec_teste_' || substr(gen_random_uuid()::text, 1, 30), 'Testes manuais / desenvolvimento')
ON CONFLICT (nome) DO NOTHING;
