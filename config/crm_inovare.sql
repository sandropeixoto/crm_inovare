-- crm_inovare.sql
-- Estrutura inicial do CRM Inovare (MySQL 8+)
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci

CREATE DATABASE IF NOT EXISTS crm_inovare
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_inovare;

-- =========================================================
-- 1) USUÃRIOS
-- =========================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('admin','comercial','gestor','visualizador') DEFAULT 'comercial',
    ultimo_login DATETIME,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_usuarios_email ON usuarios(email);

-- =========================================================
-- 2) LOGS DE AÃ‡Ã•ES DO USUÃRIO (AUDITORIA)
-- =========================================================
CREATE TABLE IF NOT EXISTS logs_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    acao VARCHAR(200) NOT NULL,
    tabela_afetada VARCHAR(100),
    id_registro_afetado INT,
    dados_anteriores JSON,
    dados_novos JSON,
    ip VARCHAR(45),
    user_agent TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_usuarios_usuario
      FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_logs_usuarios_usuario ON logs_usuarios(id_usuario);
CREATE INDEX idx_logs_usuarios_tabela ON logs_usuarios(tabela_afetada);

-- =========================================================
-- 3) CLIENTES
-- =========================================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
    status ENUM('ativo','inativo','prospecto') DEFAULT 'prospecto',
    origem VARCHAR(100),
    responsavel_comercial INT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_clientes_responsavel
      FOREIGN KEY (responsavel_comercial) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_clientes_status ON clientes(status);
CREATE INDEX idx_clientes_responsavel ON clientes(responsavel_comercial);

-- =========================================================
-- 4) CONTATOS DO CLIENTE
-- =========================================================
CREATE TABLE IF NOT EXISTS contatos_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    nome VARCHAR(150),
    cargo VARCHAR(100),
    email VARCHAR(150),
    telefone VARCHAR(30),
    principal TINYINT(1) DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contatos_cliente
      FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_contatos_id_cliente ON contatos_clientes(id_cliente);

-- =========================================================
-- 5) PACOTES
-- =========================================================
CREATE TABLE IF NOT EXISTS pacotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    conformidade VARCHAR(255),
    tipo_calculo ENUM('fixo','sinistralidade','franquia') DEFAULT 'fixo',
    sinistralidade_padrao DECIMAL(5,2) DEFAULT 10.00,
    franquia_padrao DECIMAL(5,2) DEFAULT 10.00,
    valor_implantacao_base DECIMAL(10,2) DEFAULT 0.00,
    valor_mensal_base DECIMAL(10,2) DEFAULT 0.00,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE UNIQUE INDEX uq_pacotes_nome ON pacotes(nome);

-- =========================================================
-- 6) SERVIÃ‡OS INCLUSOS EM CADA PACOTE
-- =========================================================
CREATE TABLE IF NOT EXISTS pacotes_servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pacote INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    CONSTRAINT fk_pacotes_servicos_pacote
      FOREIGN KEY (id_pacote) REFERENCES pacotes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_pacotes_servicos_pacote ON pacotes_servicos(id_pacote);

-- =========================================================
-- 7) PROPOSTAS
-- =========================================================
CREATE TABLE IF NOT EXISTS propostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_proposta VARCHAR(20) UNIQUE,
    id_cliente INT NOT NULL,
    id_pacote INT,
    modelo_id INT,
    id_usuario INT,
    numero_colaboradores INT,
    sinistralidade_percentual DECIMAL(5,2),
    franquia_percentual DECIMAL(5,2),
    valor_implantacao DECIMAL(12,2),
    valor_mensal DECIMAL(12,2),
    descricao TEXT,
    observacoes TEXT,
    data_envio DATETIME,
    validade_dias INT,
    status ENUM('rascunho','enviada','aceita','rejeitada','expirada') DEFAULT 'rascunho',
    total_servicos DECIMAL(12,2) DEFAULT 0.00,
    total_materiais DECIMAL(12,2) DEFAULT 0.00,
    total_geral DECIMAL(12,2) DEFAULT 0.00,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_propostas_cliente
      FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_propostas_pacote
      FOREIGN KEY (id_pacote) REFERENCES pacotes(id) ON DELETE SET NULL,
    CONSTRAINT fk_propostas_modelo
      FOREIGN KEY (modelo_id) REFERENCES modelos_documentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_propostas_usuario
      FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_propostas_status ON propostas(status);
CREATE INDEX idx_propostas_cliente ON propostas(id_cliente);

CREATE TABLE IF NOT EXISTS proposta_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_proposta INT NOT NULL,
    tipo_item ENUM('servico','material') DEFAULT 'servico',
    descricao_item VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) DEFAULT 1.00,
    valor_unitario DECIMAL(12,2) DEFAULT 0.00,
    valor_total DECIMAL(12,2) DEFAULT 0.00,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_proposta_itens_proposta
      FOREIGN KEY (id_proposta) REFERENCES propostas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_proposta_itens_proposta ON proposta_itens(id_proposta);

-- =========================================================
-- 8) INTERACOES (PIPELINE CRM)
-- =========================================================
CREATE TABLE IF NOT EXISTS interacoes_tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_interacao VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_interacoes_tipos_tipo (tipo_interacao)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS interacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_usuario INT,
    id_tipo_interacao INT,
    tipo VARCHAR(100),
    descricao TEXT,
    proxima_acao DATE,
    resultado ENUM('pendente','concluido') DEFAULT 'pendente',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_interacoes_cliente
      FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_interacoes_usuario
      FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_interacoes_tipo
      FOREIGN KEY (id_tipo_interacao) REFERENCES interacoes_tipos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_interacoes_id_tipo ON interacoes(id_tipo_interacao);
CREATE INDEX idx_interacoes_resultado ON interacoes(resultado);

-- =========================================================
-- 9) LOGS DE SISTEMA (ERROS/INFO)
-- =========================================================
CREATE TABLE IF NOT EXISTS sistema_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nivel ENUM('info','warning','error','critical') DEFAULT 'info',
    mensagem TEXT,
    arquivo VARCHAR(255),
    linha INT,
    stack TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 10) PACOTES INICIAIS (NR-01)
-- =========================================================
INSERT INTO pacotes (nome, descricao, conformidade, tipo_calculo, valor_implantacao_base, valor_mensal_base)
VALUES
('Pacote 1', 'Pesquisa + Mapeamento Psicossocial + RelatÃ³rio TÃ©cnico (NR-01) + EducaÃ§Ã£o em SaÃºde', 'Atende Ã  NR-01 â€“ Conformidade legal', 'fixo', 9100.00, 0.00),
('Pacote 2', 'Tudo do Pacote 1 + Atendimento MÃ©dico e PsicolÃ³gico 24h (sinistralidade de 10%)', 'NR-01 + Cuidado integral â€“ assistÃªncia em saÃºde', 'sinistralidade', 4200.00, 1775.00),
('Pacote 3', 'Tudo do Pacote 2 + Consultas mensais com psicÃ³logo e psiquiatra (franquia 10%)', 'NR-01 + Cuidado integral + gestÃ£o emocional avanÃ§ada', 'franquia', 4200.00, 2570.00)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

UPDATE usuarios SET senha_hash = '$2y$10$VoCGUrN4mBVFUkFqEqhKp.sn.0Py.cydZzxH8ZbI4hrKmqf5aj5p2' WHERE email = 'admin@inovare.com';



CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_nome VARCHAR(150),
    logotipo_url VARCHAR(255),
    endereco TEXT,
    email_contato VARCHAR(150),
    telefone VARCHAR(50),
    instagram VARCHAR(100),
    rodape TEXT,
    ativo TINYINT(1) DEFAULT 1
);

INSERT INTO configuracoes (empresa_nome, logotipo_url, endereco, email_contato, telefone, instagram, rodape)
VALUES (
  'Inovare SoluÃ§Ãµes em SaÃºde',
  'https://inovaress.com/imagens/logo-inovare.png',
  'Tv. HumaitÃ¡, 1733 â€“ 1Âº andar, Sala 02 â€“ Pedreira â€“ BelÃ©m/PA',
  'diretoria@inovaress.com',
  '(91) 98127-6875 / (91) 98425-7770',
  '@inovaresolucoesemsaude',
  'Â© Inovare SoluÃ§Ãµes em SaÃºde â€” Todos os direitos reservados.'
);

CREATE TABLE IF NOT EXISTS menus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(100) NOT NULL,
  icone VARCHAR(50) DEFAULT NULL,
  link VARCHAR(255) DEFAULT NULL,
  parent_id INT DEFAULT NULL,
  ordem INT DEFAULT 0,
  perfis_permitidos VARCHAR(255) DEFAULT 'admin,gestor,usuario',
  ativo TINYINT(1) DEFAULT 1,
  FOREIGN KEY (parent_id) REFERENCES menus(id) ON DELETE CASCADE
);


