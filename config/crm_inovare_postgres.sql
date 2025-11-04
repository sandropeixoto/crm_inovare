-- crm_inovare.sql (PostgreSQL version)
-- Estrutura inicial do CRM Inovare (PostgreSQL)
-- Charset: UTF8

-- =========================================================
-- 1) USUARIOS
-- =========================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil VARCHAR(20) DEFAULT 'comercial' CHECK (perfil IN ('admin','comercial','gestor','visualizador')),
    ultimo_login TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios(email);

-- =========================================================
-- 2) LOGS DE AÇÕES DO USUÁRIO (AUDITORIA)
-- =========================================================
CREATE TABLE IF NOT EXISTS logs_usuarios (
    id SERIAL PRIMARY KEY,
    id_usuario INT,
    acao VARCHAR(200) NOT NULL,
    tabela_afetada VARCHAR(100),
    id_registro_afetado INT,
    dados_anteriores JSONB,
    dados_novos JSONB,
    ip VARCHAR(45),
    user_agent TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_usuarios_usuario
      FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_logs_usuarios_usuario ON logs_usuarios(id_usuario);
CREATE INDEX IF NOT EXISTS idx_logs_usuarios_tabela ON logs_usuarios(tabela_afetada);

-- =========================================================
-- 3) CLIENTES
-- =========================================================
CREATE TABLE IF NOT EXISTS clientes (
    id SERIAL PRIMARY KEY,
    nome_fantasia VARCHAR(200) NOT NULL,
    razao_social VARCHAR(200),
    cnpj VARCHAR(18),
    email VARCHAR(150),
    telefone VARCHAR(30),
    endereco TEXT,
    bairro VARCHAR(120),
    cep VARCHAR(10),
    cidade VARCHAR(100),
    uf CHAR(2),
    qtd_colaboradores INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'prospecto' CHECK (status IN ('ativo','inativo','prospecto')),
    origem VARCHAR(100),
    responsavel_comercial INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP,
    CONSTRAINT fk_clientes_responsavel
      FOREIGN KEY (responsavel_comercial) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_clientes_status ON clientes(status);
CREATE INDEX IF NOT EXISTS idx_clientes_responsavel ON clientes(responsavel_comercial);

-- Create trigger to update atualizado_em automatically
CREATE OR REPLACE FUNCTION update_atualizado_em_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.atualizado_em = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_clientes_atualizado_em BEFORE UPDATE ON clientes
    FOR EACH ROW EXECUTE FUNCTION update_atualizado_em_column();

-- =========================================================
-- 4) CONTATOS DO CLIENTE
-- =========================================================
CREATE TABLE IF NOT EXISTS contatos_clientes (
    id SERIAL PRIMARY KEY,
    id_cliente INT NOT NULL,
    nome VARCHAR(150),
    cargo VARCHAR(100),
    email VARCHAR(150),
    telefone VARCHAR(30),
    principal BOOLEAN DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contatos_cliente
      FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_contatos_id_cliente ON contatos_clientes(id_cliente);

-- =========================================================
-- 5) PACOTES
-- =========================================================
CREATE TABLE IF NOT EXISTS pacotes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    conformidade VARCHAR(255),
    tipo_calculo VARCHAR(20) DEFAULT 'fixo' CHECK (tipo_calculo IN ('fixo','sinistralidade','franquia')),
    sinistralidade_padrao DECIMAL(5,2) DEFAULT 10.00,
    franquia_padrao DECIMAL(5,2) DEFAULT 10.00,
    valor_implantacao_base DECIMAL(10,2) DEFAULT 0.00,
    valor_mensal_base DECIMAL(10,2) DEFAULT 0.00,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_pacotes_nome ON pacotes(nome);

-- =========================================================
-- 6) SERVIÇOS INCLUSOS EM CADA PACOTE
-- =========================================================
CREATE TABLE IF NOT EXISTS pacotes_servicos (
    id SERIAL PRIMARY KEY,
    id_pacote INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    CONSTRAINT fk_pacotes_servicos_pacote
      FOREIGN KEY (id_pacote) REFERENCES pacotes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_pacotes_servicos_pacote ON pacotes_servicos(id_pacote);

-- =========================================================
-- 7) PROPOSTAS
-- =========================================================
CREATE TABLE IF NOT EXISTS propostas (
    id SERIAL PRIMARY KEY,
    codigo_proposta VARCHAR(20) UNIQUE,
    id_cliente INT NOT NULL,
    id_pacote INT,
    modelo_id INT,
    id_usuario INT,
    numero_colaboradores INT,
    sinistralidade_percentual NUMERIC(5,2),
    franquia_percentual NUMERIC(5,2),
    valor_implantacao NUMERIC(12,2),
    valor_mensal NUMERIC(12,2),
    descricao TEXT,
    observacoes TEXT,
    data_envio TIMESTAMP,
    validade_dias INT,
    status VARCHAR(20) DEFAULT 'rascunho' CHECK (status IN ('rascunho','enviada','aceita','rejeitada','expirada')),
    total_servicos DECIMAL(12,2) DEFAULT 0.00,
    total_materiais DECIMAL(12,2) DEFAULT 0.00,
    total_geral DECIMAL(12,2) DEFAULT 0.00,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_propostas_cliente
      FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_propostas_pacote
      FOREIGN KEY (id_pacote) REFERENCES pacotes(id) ON DELETE SET NULL,
    CONSTRAINT fk_propostas_modelo
      FOREIGN KEY (modelo_id) REFERENCES modelos_documentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_propostas_usuario
      FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_propostas_status ON propostas(status);
CREATE INDEX IF NOT EXISTS idx_propostas_cliente ON propostas(id_cliente);

CREATE TRIGGER update_propostas_atualizado_em BEFORE UPDATE ON propostas
    FOR EACH ROW EXECUTE FUNCTION update_atualizado_em_column();

CREATE TABLE IF NOT EXISTS proposta_itens (
    id SERIAL PRIMARY KEY,
    id_proposta INT NOT NULL,
    tipo_item VARCHAR(20) DEFAULT 'servico' CHECK (tipo_item IN ('servico','material')),
    descricao_item VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) DEFAULT 1.00,
    valor_unitario DECIMAL(12,2) DEFAULT 0.00,
    valor_total DECIMAL(12,2) DEFAULT 0.00,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_proposta_itens_proposta
      FOREIGN KEY (id_proposta) REFERENCES propostas(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_proposta_itens_proposta ON proposta_itens(id_proposta);

-- =========================================================
-- 8) INTERACOES (PIPELINE CRM)
-- =========================================================
CREATE TABLE IF NOT EXISTS interacoes_tipos (
    id SERIAL PRIMARY KEY,
    tipo_interacao VARCHAR(100) NOT NULL,
    CONSTRAINT uq_interacoes_tipos_tipo UNIQUE (tipo_interacao)
);

CREATE TABLE IF NOT EXISTS interacoes (
    id SERIAL PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_usuario INT,
    id_tipo_interacao INT,
    tipo VARCHAR(100),
    descricao TEXT,
    proxima_acao DATE,
    resultado VARCHAR(20) DEFAULT 'pendente' CHECK (resultado IN ('pendente','concluido')),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_interacoes_cliente
      FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_interacoes_usuario
      FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_interacoes_tipo
      FOREIGN KEY (id_tipo_interacao) REFERENCES interacoes_tipos(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_interacoes_id_tipo ON interacoes(id_tipo_interacao);
CREATE INDEX IF NOT EXISTS idx_interacoes_resultado ON interacoes(resultado);

-- =========================================================
-- 9) LOGS DE SISTEMA (ERROS/INFO)
-- =========================================================
CREATE TABLE IF NOT EXISTS sistema_logs (
    id SERIAL PRIMARY KEY,
    nivel VARCHAR(20) DEFAULT 'info' CHECK (nivel IN ('info','warning','error','critical')),
    mensagem TEXT,
    arquivo VARCHAR(255),
    linha INT,
    stack TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================================
-- 10) CONFIGURAÇÕES
-- =========================================================
CREATE TABLE IF NOT EXISTS configuracoes (
    id SERIAL PRIMARY KEY,
    empresa_nome VARCHAR(150),
    logotipo_url VARCHAR(255),
    endereco TEXT,
    email_contato VARCHAR(150),
    telefone VARCHAR(50),
    instagram VARCHAR(100),
    rodape TEXT,
    ativo BOOLEAN DEFAULT TRUE
);

-- =========================================================
-- 11) MENUS
-- =========================================================
CREATE TABLE IF NOT EXISTS menus (
  id SERIAL PRIMARY KEY,
  titulo VARCHAR(100) NOT NULL,
  icone VARCHAR(50) DEFAULT NULL,
  link VARCHAR(255) DEFAULT NULL,
  parent_id INT DEFAULT NULL,
  ordem INT DEFAULT 0,
  perfis_permitidos VARCHAR(255) DEFAULT 'admin,gestor,usuario',
  ativo BOOLEAN DEFAULT TRUE,
  CONSTRAINT fk_menus_parent FOREIGN KEY (parent_id) REFERENCES menus(id) ON DELETE CASCADE
);

-- =========================================================
-- DADOS INICIAIS
-- =========================================================

-- Inserir pacotes iniciais
INSERT INTO pacotes (nome, descricao, conformidade, tipo_calculo, valor_implantacao_base, valor_mensal_base)
VALUES
('Pacote 1', 'Pesquisa + Mapeamento Psicossocial + Relatório Técnico (NR-01) + Educação em Saúde', 'Atende à NR-01 – Conformidade legal', 'fixo', 9100.00, 0.00),
('Pacote 2', 'Tudo do Pacote 1 + Atendimento Médico e Psicológico 24h (sinistralidade de 10%)', 'NR-01 + Cuidado integral – assistência em saúde', 'sinistralidade', 4200.00, 1775.00),
('Pacote 3', 'Tudo do Pacote 2 + Consultas mensais com psicólogo e psiquiatra (franquia 10%)', 'NR-01 + Cuidado integral + gestão emocional avançada', 'franquia', 4200.00, 2570.00)
ON CONFLICT (nome) DO NOTHING;

-- Inserir configuração inicial
INSERT INTO configuracoes (empresa_nome, logotipo_url, endereco, email_contato, telefone, instagram, rodape)
VALUES (
  'Inovare Soluções em Saúde',
  'https://inovaress.com/imagens/logo-inovare.png',
  'Tv. Humaitá, 1733 – 1º andar, Sala 02 – Pedreira – Belém/PA',
  'diretoria@inovaress.com',
  '(91) 98127-6875 / (91) 98425-7770',
  '@inovaresolucoesemsaude',
  '© Inovare Soluções em Saúde – Todos os direitos reservados.'
)
ON CONFLICT DO NOTHING;

-- Inserir usuário admin inicial (senha: admin123)
INSERT INTO usuarios (nome, email, senha_hash, perfil)
VALUES ('Administrador', 'admin@inovare.com', '$2y$10$VoCGUrN4mBVFUkFqEqhKp.sn.0Py.cydZzxH8ZbI4hrKmqf5aj5p2', 'admin')
ON CONFLICT (email) DO UPDATE SET senha_hash = EXCLUDED.senha_hash;
