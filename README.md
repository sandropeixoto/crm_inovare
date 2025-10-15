# CRM Inovare

## Visao geral
CRM Inovare e uma aplicacao web em PHP 8.1 voltada para equipes comerciais da Inovare Solucoes em Saude organizarem clientes, propostas e entregas contratuais. O sistema combina autenticacao com papeis, dashboard executivo e modulos administrativos construidos sobre Bootstrap 5, todos orquestrados pelo template public/inc/template_base.php.

## Destaques atuais
- **Autenticacao e controle de acesso** - Login com senha usando bcrypt, armazenamento seguro de sessao (ensure_session_security) e autorizacao por perfil (admin, gestor, comercial, visualizador).
- **Dashboard** - Cards e resumos para clientes, propostas, pacotes e usuarios ativos imediatamente apos o login.
- **Clientes e pipeline comercial** - Cadastro completo, filtros, timeline de interacoes e campo de proxima acao. Os tipos de interacao agora sao mantidos na tabela auxiliar interacoes_tipos, administrada via Generic CRUD.
- **Propostas comerciais** - Montagem por pacote, calculos automaticos, exportacao PDF (Dompdf) e logs de auditoria a cada alteracao.
- **Relatorios financeiros** - Painel em Chart.js com totais por status e evolucao mensal.
- **Administracao auxiliar** - Alem do generic_crud.php, o sistema possui modulos dedicados para:
  - auxiliares/pacotes/ (listar, filtrar, criar, editar e excluir pacotes)
  - auxiliares/pacotes_servicos/ (manter os servicos vinculados a cada pacote)
  - auxiliares/status_proposta.php, classificacoes.php e unidades_medida.php.
- **Gestao de usuarios** - Criacao, edicao, ativacao e remocao de contas com rastreio completo via log_user_action.
- **Auditoria e monitoramento** - Logs de sistema (sistema_logs) e de usuario (logs_usuarios) registram IP, user agent e payload de alteracoes.

## Requisitos
- PHP 8.1 ou superior com extensoes pdo_mysql, openssl e mbstring habilitadas.
- MySQL 8+ (ou MariaDB equivalente) utilizando o schema crm_inovare.
- Composer para instalar dependencias (Dompdf).
- Servidor HTTP apontando para o diretorio public/ (Apache, Nginx ou php -S).

## Como configurar
1. **Clonar o repositorio** e entrar na pasta do projeto.
2. **Instalar dependencias**: composer install (gera vendor/ com Dompdf).
3. **Configurar variaveis**: copie .env.example para .env e ajuste as chaves CRM_DB_*. O bootstrap em config/db.php carrega esse arquivo automaticamente.
4. **Criar o banco**: importe config/crm_inovare.sql. O script cria tabelas, menus, configuracao institucional, pacotes base e atualiza a senha de admin@inovare.com.
5. **Ajustar caminho base (opcional)**: se o sistema estiver fora de /inovare/public, ajuste APP_BASE_PATH em config/db.php ou adapte template_base.php.
6. **Subir o servidor**: php -S localhost:8000 -t public ou configure seu virtual host preferido.
7. **Acessar**: abra http://localhost:8000/login.php e autentique-se. Troque a senha do administrador logo apos a importacao do schema.

## Estrutura de pastas
```
config/                Bootstrap de banco, helpers, funcoes de seguranca
public/
  auxiliares/         Modulos auxiliares (pacotes, pacotes_servicos, generic CRUD, etc.)
  clientes/           Listagem, cadastro e visualizacao de clientes
  inc/                Template base, menu lateral e includes globais
  interacoes/         Registro de contatos e timeline por cliente
  propostas/          Criacao, revisao e exportacao de propostas
  relatorios/         Dashboard financeiro em Chart.js
  usuarios/           Administracao de contas do sistema
vendor/               Dependencias instaladas via Composer (nao versionadas)
```

## Banco de dados e logs
O schema cobre clientes, contatos, pacotes, servicos, propostas, interacoes (com tabela auxiliar para tipos), configuracoes institucionais e os dois canais de auditoria. As funcoes run_query, log_user_action e log_system centralizam a persistencia, garantem padrao unico de log e exibem mensagens amigaveis em caso de falha.

## Controles de seguranca
Todas as rotas protegidas chamam ensure_session_security() e require_role(). Formularios utilizam csrf_field() e validate_csrf_token(). Em producao recomenda-se HTTPS, rotacao de senhas administrativas e ajuste do timeout de sessao conforme politica interna.

## Dicas de evolucao
- Implementar fluxo de redefinicao de senha por e-mail, alem de expiracao automatica de sessao.
- Centralizar configuracoes de URL base para facilitar deploys em subdiretorios.
- Adicionar testes automatizados (PHPUnit ou Pest) para helpers de banco e regras de negocio.

Este README reflete o estado atual do CRM Inovare e os modulos adicionados recentemente para gestao de pacotes, servicos e tipos de interacao.
