-- Seed de menus do sistema
-- Execute este script após criar o banco de dados para popular os menus

-- Limpar menus existentes (opcional - use com cuidado)
-- DELETE FROM menus;

-- Inserir menus principais
INSERT INTO menus (titulo, icone, link, parent_id, ordem, perfis_permitidos, ativo) VALUES
('Dashboard', '🏠', 'index.php', NULL, 1, 'admin,gestor,comercial,visualizador', TRUE),
('Clientes', '👥', 'clientes/listar.php', NULL, 2, 'admin,gestor,comercial', TRUE),
('Propostas', '📄', 'propostas/listar.php', NULL, 3, 'admin,gestor,comercial', TRUE),
('Relatórios', '📊', 'relatorios/dashboard_financeiro.php', NULL, 4, 'admin,gestor', TRUE),
('Usuários', '👤', 'usuarios/listar.php', NULL, 5, 'admin,gestor', TRUE),
('Configurações', '⚙️', 'configuracoes/editar.php', NULL, 6, 'admin', TRUE),
('Módulos Auxiliares', '🔧', '#', NULL, 7, 'admin,gestor', TRUE)
ON CONFLICT DO NOTHING;

-- Buscar ID do menu "Módulos Auxiliares" e inserir submenus
DO $$
DECLARE
    menu_aux_id INT;
BEGIN
    SELECT id INTO menu_aux_id FROM menus WHERE titulo = 'Módulos Auxiliares';
    
    IF menu_aux_id IS NOT NULL THEN
        INSERT INTO menus (titulo, icone, link, parent_id, ordem, perfis_permitidos, ativo) VALUES
        ('Pacotes', '📦', 'auxiliares/pacotes/listar.php', menu_aux_id, 1, 'admin,gestor', TRUE),
        ('Serviços de Pacotes', '🛠️', 'auxiliares/pacotes_servicos/listar.php', menu_aux_id, 2, 'admin,gestor', TRUE),
        ('Tipos de Interação', '💬', 'auxiliares/tipos_interacao.php', menu_aux_id, 3, 'admin,gestor', TRUE),
        ('Status de Propostas', '📋', 'auxiliares/status_propostas.php', menu_aux_id, 4, 'admin,gestor', TRUE),
        ('Classificações', '🏷️', 'auxiliares/classificacoes.php', menu_aux_id, 5, 'admin,gestor', TRUE),
        ('Unidades de Medida', '📏', 'auxiliares/unidades_medida.php', menu_aux_id, 6, 'admin,gestor', TRUE),
        ('Gerenciar Menus', '🎯', 'auxiliares/menus.php', menu_aux_id, 7, 'admin', TRUE)
        ON CONFLICT DO NOTHING;
    END IF;
END $$;
