PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    perfil TEXT NOT NULL DEFAULT 'comercial',
    telefone TEXT,
    ultimo_login TEXT,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios(email);

CREATE TABLE IF NOT EXISTS logs_usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER,
    acao TEXT NOT NULL,
    tabela_afetada TEXT,
    id_registro_afetado INTEGER,
    dados_anteriores TEXT,
    dados_novos TEXT,
    ip TEXT,
    user_agent TEXT,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_logs_usuarios_usuario ON logs_usuarios(id_usuario);
CREATE INDEX IF NOT EXISTS idx_logs_usuarios_tabela ON logs_usuarios(tabela_afetada);

CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_fantasia TEXT NOT NULL,
    razao_social TEXT,
    cnpj TEXT,
    email TEXT,
    telefone TEXT,
    endereco TEXT,
    bairro TEXT,
    cep TEXT,
    cidade TEXT,
    uf TEXT,
    qtd_colaboradores INTEGER DEFAULT 0,
    status TEXT DEFAULT 'prospecto',
    origem TEXT,
    responsavel_comercial INTEGER,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TEXT,
    FOREIGN KEY (responsavel_comercial) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_clientes_status ON clientes(status);
CREATE INDEX IF NOT EXISTS idx_clientes_responsavel ON clientes(responsavel_comercial);

CREATE TABLE IF NOT EXISTS contatos_clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_cliente INTEGER NOT NULL,
    nome TEXT,
    cargo TEXT,
    email TEXT,
    telefone TEXT,
    principal INTEGER NOT NULL DEFAULT 0,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_contatos_id_cliente ON contatos_clientes(id_cliente);

CREATE TABLE IF NOT EXISTS pacotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    descricao TEXT,
    conformidade TEXT,
    tipo_calculo TEXT NOT NULL DEFAULT 'fixo',
    sinistralidade_padrao REAL NOT NULL DEFAULT 10.0,
    franquia_padrao REAL NOT NULL DEFAULT 10.0,
    valor_implantacao_base REAL NOT NULL DEFAULT 0.0,
    valor_mensal_base REAL NOT NULL DEFAULT 0.0,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_pacotes_nome ON pacotes(nome);

CREATE TABLE IF NOT EXISTS pacotes_servicos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_pacote INTEGER NOT NULL,
    titulo TEXT NOT NULL,
    descricao TEXT,
    FOREIGN KEY (id_pacote) REFERENCES pacotes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_pacotes_servicos_pacote ON pacotes_servicos(id_pacote);

CREATE TABLE IF NOT EXISTS modelos_documentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL,
    descricao TEXT,
    categoria TEXT,
    conteudo_html TEXT NOT NULL,
    variaveis_usadas TEXT,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TEXT
);

CREATE INDEX IF NOT EXISTS idx_modelos_categoria ON modelos_documentos(categoria);

CREATE TABLE IF NOT EXISTS propostas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo_proposta TEXT UNIQUE,
    id_cliente INTEGER NOT NULL,
    id_pacote INTEGER,
    modelo_id INTEGER,
    id_usuario INTEGER,
    numero_colaboradores INTEGER,
    sinistralidade_percentual REAL,
    franquia_percentual REAL,
    valor_implantacao REAL,
    valor_mensal REAL,
    descricao TEXT,
    observacoes TEXT,
    data_envio TEXT,
    validade_dias INTEGER,
    status TEXT NOT NULL DEFAULT 'rascunho',
    total_servicos REAL NOT NULL DEFAULT 0.0,
    total_materiais REAL NOT NULL DEFAULT 0.0,
    total_geral REAL NOT NULL DEFAULT 0.0,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TEXT,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pacote) REFERENCES pacotes(id) ON DELETE SET NULL,
    FOREIGN KEY (modelo_id) REFERENCES modelos_documentos(id) ON DELETE SET NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_propostas_status ON propostas(status);
CREATE INDEX IF NOT EXISTS idx_propostas_cliente ON propostas(id_cliente);

CREATE TABLE IF NOT EXISTS proposta_itens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_proposta INTEGER NOT NULL,
    tipo_item TEXT NOT NULL DEFAULT 'servico',
    descricao_item TEXT NOT NULL,
    quantidade REAL NOT NULL DEFAULT 1.0,
    valor_unitario REAL NOT NULL DEFAULT 0.0,
    valor_total REAL NOT NULL DEFAULT 0.0,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_proposta) REFERENCES propostas(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_proposta_itens_proposta ON proposta_itens(id_proposta);

CREATE TABLE IF NOT EXISTS interacoes_tipos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo_interacao TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS interacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_cliente INTEGER NOT NULL,
    id_usuario INTEGER,
    id_tipo_interacao INTEGER,
    tipo TEXT,
    descricao TEXT,
    proxima_acao TEXT,
    resultado TEXT NOT NULL DEFAULT 'pendente',
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (id_tipo_interacao) REFERENCES interacoes_tipos(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_interacoes_id_tipo ON interacoes(id_tipo_interacao);
CREATE INDEX IF NOT EXISTS idx_interacoes_resultado ON interacoes(resultado);

CREATE TABLE IF NOT EXISTS sistema_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nivel TEXT NOT NULL DEFAULT 'info',
    mensagem TEXT,
    arquivo TEXT,
    linha INTEGER,
    stack TEXT,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS configuracoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_nome TEXT,
    logotipo_url TEXT,
    endereco TEXT,
    email_contato TEXT,
    telefone TEXT,
    instagram TEXT,
    rodape TEXT,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS menus (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL,
    icone TEXT,
    link TEXT,
    parent_id INTEGER,
    ordem INTEGER DEFAULT 0,
    perfis_permitidos TEXT DEFAULT 'admin,gestor,comercial',
    ativo INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (parent_id) REFERENCES menus(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_menus_parent ON menus(parent_id);

CREATE TABLE IF NOT EXISTS password_resets (
    email TEXT NOT NULL,
    token TEXT NOT NULL PRIMARY KEY,
    expires_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets(email);
