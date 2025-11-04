# Overview

CRM Inovare is a web application built with PHP 8.2 designed for commercial teams at Inovare Soluções em Saúde to manage clients, proposals, and contractual deliveries. The system provides role-based authentication, a commercial pipeline, proposal generation with PDF export, financial reporting, and comprehensive audit logging.

**Status**: Successfully configured for Replit environment with PostgreSQL database.

## Recent Changes (November 4, 2025)

### Replit Environment Setup
- Migrated from MySQL to PostgreSQL for Replit compatibility
- Updated database connection in `config/db.php` to use PostgreSQL PDO driver
- Created PostgreSQL-compatible schema in `config/crm_inovare_postgres.sql`
- Configured automatic base path detection for Replit environment
- Fixed boolean field queries (changed `ativo=1` to `ativo=TRUE` for PostgreSQL compatibility)
- Configured PHP built-in server on port 5000 with document root at `public/`

### AdminLTE Integration
- Integrated AdminLTE 3.2 for modern admin interface
- Created responsive sidebar with dynamic menu system
- Updated dashboard with AdminLTE small-box widgets
- Implemented collapsible menu structure with icons

### New Auxiliary Modules
- ✅ **Tipos de Interação**: Full CRUD for interaction types
- ✅ **Classificações**: Full CRUD for custom classifications with color coding
- ✅ **Unidades de Medida**: Full CRUD for measurement units
- ✅ **Status de Propostas**: Informative view of proposal statuses (defined in schema)
- ✅ **Gerenciar Menus**: Visual menu management with drag-and-drop reordering
- 📝 Menu seed script available in `config/seed_menus.sql`

### Default Credentials (Working)
- **Email:** `admin@inovare.com`
- **Senha:** `admin123`
- **Important**: Troque a senha do administrador após o primeiro login

### Sistema de Modelos de Documentos (Novo!)
- ✅ **CRUD de Modelos**: Interface completa para criar, editar, duplicar e excluir modelos de documentos
- ✅ **Editor Visual TinyMCE**: Editor WYSIWYG integrado para criação fácil de modelos
- ✅ **Variáveis Dinâmicas**: Painel lateral com variáveis disponíveis (Cliente, Vendedor, Proposta, Valores, Empresa)
- ✅ **Motor de Templates**: Sistema que substitui variáveis automaticamente ao gerar PDFs
- ✅ **Integração com Propostas**: Campo para selecionar modelo ao criar/editar propostas
- ✅ **Geração de PDF**: PDFs gerados usando o modelo selecionado ou layout padrão como fallback
- ✅ **Modelos Prontos**: 3 templates profissionais incluídos (Proposta Comercial, Orçamento, Contrato)
- 📝 **Limitações conhecidas**: Loop de itens e formatadores avançados serão implementados em versão futura

### Status do Sistema
- ✅ Banco de dados PostgreSQL configurado e populado
- ✅ Login funcionando corretamente
- ✅ Servidor web rodando na porta 5000
- ✅ Deployment configurado
- ✅ AdminLTE integrado e funcional
- ✅ Módulos auxiliares criados e acessíveis via menu
- ✅ Sistema de modelos de documentos operacional

# User Preferences

Preferred communication style: Simple, everyday language.

# System Architecture

## Application Stack
- **Language**: PHP 8.2 with PDO PostgreSQL, OpenSSL, and mbstring extensions
- **Database**: PostgreSQL (Replit-managed) using the `crm_inovare` schema
- **Dependency Management**: Composer for vendor libraries
- **Web Server**: PHP built-in development server on port 5000, document root at `public/`
- **Environment**: Replit with automatic environment variable detection

## Authentication & Authorization
- **Password Security**: Bcrypt hashing for password storage
- **Session Management**: Custom `ensure_session_security` function for secure session handling
- **Role-Based Access Control**: Four user roles (admin, gestor, comercial, visualizador) controlling access to different features
- **Session Storage**: Server-side session management with security hardening

## Application Structure
The application follows a modular directory structure:

- **config/** - Database bootstrap, helper functions, security utilities, and SQL schema
- **public/** - Front-facing application modules organized by feature
  - **auxiliares/** - Administrative modules for managing support data (packages, services, statuses, classifications)
  - **clientes/** - Client listing, registration, and detail views
  - **interacoes/** - Client interaction timeline and contact logging
  - **propostas/** - Proposal creation, editing, and PDF export
  - **relatorios/** - Financial dashboards with Chart.js visualizations
  - **usuarios/** - User account management
  - **inc/** - Shared templates, navigation menus, and global includes

## Configuration Management
- **Environment Variables**: Uses Replit environment variables (PGHOST, PGDATABASE, PGUSER, PGPASSWORD, PGPORT)
- **Bootstrap**: `config/db.php` loads environment variables and establishes PostgreSQL connection
- **Base Path**: Auto-detects Replit environment and sets `APP_BASE_PATH` to `/` (instead of `/inovare/public`)
- **Fallback**: Can still use `.env` file for custom configurations if needed

## Data Model
The database schema includes tables for:

- **Core Entities**: Clients, contacts, proposals, packages, services
- **Support Tables**: Interaction types (`interacoes_tipos`), proposal statuses, classifications, units of measure
- **User Management**: Users table with role-based permissions
- **Configuration**: Institutional settings stored in database
- **Audit Trails**: Dual logging system with `sistema_logs` and `logs_usuarios`

## Audit & Monitoring
- **Dual Logging System**: 
  - System logs (`sistema_logs`) for application-level events
  - User logs (`logs_usuarios`) for user actions with IP, user agent, and change payloads
- **Action Tracking**: `log_user_action` function records all user modifications
- **Query Logging**: `run_query` function provides centralized database operation logging

## PDF Generation
- **Library**: Dompdf 3.1 for HTML-to-PDF conversion
- **Use Case**: Proposal documents exported to PDF format
- **Integration**: Proposals module generates formatted PDFs from proposal data

## Reporting & Analytics
- **Dashboard**: Quick-view cards showing client count, proposal metrics, package count, and active users
- **Financial Reports**: Chart.js visualizations displaying:
  - Totals by proposal status
  - Monthly evolution trends
- **Client Pipeline**: Timeline visualization of client interactions with next action tracking

## Module Design Pattern
Modules follow a consistent pattern:
- **List View**: Filterable tables showing records
- **Create/Edit Forms**: Data entry with validation
- **Detail Views**: Single-record display with related information
- **Delete Operations**: Soft or hard delete with audit trail
- **Generic CRUD**: `generic_crud.php` provides reusable CRUD operations for auxiliary tables

## Security Measures
- **Password Hashing**: Bcrypt algorithm for password storage
- **Session Security**: Custom hardening through `ensure_session_security`
- **SQL Injection Protection**: PDO prepared statements via `run_query` function
- **Access Control**: Role-based authorization checks on module access
- **Audit Trail**: Complete logging of IP addresses, user agents, and modification payloads

# External Dependencies

## Third-Party Libraries (via Composer)
- **dompdf/dompdf** (v3.1.4) - HTML to PDF conversion for proposal export
  - Dependencies: php-font-lib, php-svg-lib, masterminds/html5
  - Requires: ext-dom, ext-mbstring
  - Used for generating PDF versions of commercial proposals

## Required PHP Extensions
- **pdo_mysql** - Database connectivity to MySQL/MariaDB
- **openssl** - Cryptographic operations for password hashing
- **mbstring** - Multi-byte string handling for international character support

## Database
- **MySQL 8+** or **MariaDB** (equivalent version)
- Database name: `crm_inovare`
- Schema initialization: `config/crm_inovare.sql`

## Client-Side Libraries
- **Chart.js** - Financial reporting visualizations and dashboards

## Deployment Requirements
- Web server (Apache, Nginx, or PHP built-in)
- Document root pointing to `public/` directory
- Composer for dependency installation
- Environment configuration via `.env` file