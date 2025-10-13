# CRM Inovare

## Visão geral
O CRM Inovare é um painel web em PHP pensado para equipes comerciais e de gestão da saúde ocupacional acompanharem clientes, propostas e interações vinculadas aos pacotes NR-01 da Inovare Soluções em Saúde. O projeto oferece autenticação com papéis, dashboard com indicadores em tempo real e módulos de apoio (clientes, propostas, auxiliares, relatórios e usuários) organizados sobre um layout único com menu dinâmico carregado do banco de dados.【F:public/index.php†L1-L55】【F:public/inc/template_base.php†L1-L129】

## Principais funcionalidades
- **Autenticação e controle de acesso** — Login com verificação de senha hash, bloqueio por perfil (`admin`, `gestor`, `comercial`, `visualizador`) e guardas de rota por função.【F:public/login.php†L1-L88】【F:config/db.php†L131-L155】
- **Dashboard executivo** — Cards com totais de clientes, propostas, pacotes e usuários ativos logo após o login.【F:public/index.php†L5-L55】
- **Gestão de clientes** — Listagem com filtros por nome, cidade, status e responsável, paginação e integração com cadastro de propostas.【F:public/clientes/listar.php†L13-L200】
- **Pipeline de interações** — Registro de contatos por tipo, timeline por cliente e acompanhamento da próxima ação planejada.【F:public/interacoes/cliente.php†L1-L163】
- **Propostas comerciais** — Cálculo automático por pacote, salvamento e resumo da proposta recém-gerada para cada cliente.【F:public/propostas/nova.php†L1-L123】
- **Geração de PDFs** — Exportação de propostas com layout corporativo via Dompdf, trazendo dados do cliente, pacote e configuração institucional.【F:public/propostas/gerar_pdf.php†L1-L137】
- **Relatórios financeiros** — Gráficos (Chart.js) com totais por status, evolução mensal e consolidados de propostas emitidas.【F:public/relatorios/dashboard_financeiro.php†L1-L254】
- **Administração auxiliar** — CRUD genérico para tabelas de apoio (menus, pacotes, status etc.) com filtros, modais e paginação.【F:public/auxiliares/generic_crud.php†L1-L200】
- **Gestão de usuários** — Tela administrativa para criar, editar, ativar/desativar e remover contas da aplicação.【F:public/usuarios/listar.php†L1-L57】

## Requisitos
- PHP 8.1+ com extensões `pdo_mysql` e `openssl` habilitadas.
- MySQL 8 ou compatível para hospedar o banco `crm_inovare`.
- Composer para instalar dependências PHP (Dompdf).【F:composer.json†L1-L5】
- Servidor HTTP apontando para o diretório `public/` (Apache, Nginx ou `php -S`).

## Configuração do ambiente
1. **Clonar o repositório** e acessar a pasta do projeto.
2. **Instalar as dependências**: `composer install` (gera `vendor/` com o Dompdf).【F:public/propostas/gerar_pdf.php†L123-L136】
3. **Configurar credenciais do banco**: defina as variáveis de ambiente `CRM_DB_HOST`, `CRM_DB_NAME`, `CRM_DB_USER` e `CRM_DB_PASS` ou ajuste os valores padrão em `config/db.php`.【F:config/db.php†L17-L48】
4. **Criar o schema**: importe `config/crm_inovare.sql` no MySQL (`mysql -u user -p < config/crm_inovare.sql`). O script cria tabelas, dados iniciais (pacotes, menus, configuração institucional) e atualiza a senha do usuário `admin@inovare.com` (defina uma senha conhecida após a importação).【F:config/crm_inovare.sql†L1-L237】
5. **Ajustar URL base (opcional)**: se a aplicação não estiver na pasta `/inovare/public`, atualize `$base_path` em `public/inc/template_base.php` e qualquer redirecionamento absoluto para refletir o novo caminho público.【F:public/inc/template_base.php†L31-L125】
6. **Subir o servidor**: `php -S localhost:8000 -t public` (ou configure o virtual host). O arquivo `index.php` na raiz já redireciona para `public/index.php`.【F:index.php†L1-L4】
7. **Acessar o sistema**: abra `http://localhost:8000/login.php` e autentique com um usuário existente; redefina a senha do administrador diretamente no banco se necessário.

## Estrutura principal
```
config/            # Conexão PDO, helpers e script SQL completo do banco
public/            # Código acessível via HTTP (login, dashboard, módulos)
  auxiliares/      # CRUD genérico para tabelas auxiliares
  clientes/        # Cadastro, edição e visualização de clientes
  inc/             # Template base (layout, menus) e includes comuns
  interacoes/      # Timeline de interações por cliente
  propostas/       # Criação, listagem e geração de PDFs de propostas
  relatorios/      # Dashboard financeiro com gráficos
  usuarios/        # Administração de contas de usuário
vendor/            # Dependências instaladas via Composer (gerado)
```

## Banco de dados e auditoria
O schema contempla usuários, clientes, pacotes, propostas, interações, logs de auditoria e menus dinâmicos. A criação inicial popula pacotes NR-01, configurações institucionais e o menu lateral padrão.【F:config/crm_inovare.sql†L90-L237】 As funções auxiliares centralizam conexão PDO, logs sistêmicos e rastreamento de ações do usuário (IP e user agent), o que facilita auditoria de operações críticas.【F:config/db.php†L20-L155】

## Segurança e controle de acesso
Todas as rotas protegidas chamam `ensure_session_security()` para exigir sessão ativa e `require_role()` para validar perfis autorizados antes de executar ações específicas (clientes, propostas, relatórios, etc.). Use HTTPS em produção e substitua a senha padrão do administrador logo após o provisionamento.【F:config/db.php†L131-L155】【F:public/clientes/listar.php†L10-L12】

## Personalização e extensões
- **Menus laterais**: gerenciados pela tabela `menus` e renderizados dinamicamente; utilize o CRUD genérico para adicionar itens e submenus por perfil.【F:public/inc/template_base.php†L15-L125】【F:public/auxiliares/generic_crud.php†L1-L200】
- **Configuração institucional**: altere o logotipo, contatos e rodapé pelo registro ativo em `configuracoes`, refletindo imediatamente no login, layout e PDFs.【F:public/login.php†L11-L85】【F:public/propostas/gerar_pdf.php†L50-L117】
- **Relatórios**: o dashboard financeiro pode ser expandido com novos datasets ou exportações reutilizando a base Chart.js existente.【F:public/relatorios/dashboard_financeiro.php†L10-L254】

## Próximos passos sugeridos
- Implementar recuperação de senha por e-mail e expiração automática de sessão.
- Converter os caminhos absolutos fixos (`/inovare/public`) para uma configuração centralizada para facilitar deploys em subdiretórios.【F:public/inc/template_base.php†L31-L125】
- Adicionar testes automatizados (PHPUnit ou Pest) para os helpers em `config/db.php`.

A documentação acima resume os módulos existentes e como iniciar rapidamente o CRM Inovare em um novo ambiente.
