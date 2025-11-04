<?php
require_once '../../config/db.php';
ensure_session_security();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['admin', 'gestor'])) {
    die('Acesso negado');
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'edit') {
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            die('Token CSRF inválido');
        }

        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $conteudo_html = $_POST['conteudo_html'] ?? '';
        $ativo = isset($_POST['ativo']) ? TRUE : FALSE;

        if (empty($titulo) || empty($categoria) || empty($conteudo_html)) {
            $error_msg = 'Preencha todos os campos obrigatórios';
        } else {
            preg_match_all('/\{\{([^}]+)\}\}/', $conteudo_html, $matches);
            $variaveis_usadas = !empty($matches[1]) ? implode(',', array_unique($matches[1])) : '';

            if ($action === 'create') {
                $sql = "INSERT INTO modelos_documentos (titulo, descricao, categoria, conteudo_html, variaveis_usadas, ativo) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $result = run_query($sql, [$titulo, $descricao, $categoria, $conteudo_html, $variaveis_usadas, $ativo]);
                
                if ($result) {
                    log_user_action('criar', 'modelos_documentos', null, "Criou modelo: $titulo");
                    header('Location: modelos_documentos.php?success=' . urlencode('Modelo criado com sucesso'));
                    exit;
                }
            } else {
                $sql = "UPDATE modelos_documentos SET titulo=?, descricao=?, categoria=?, conteudo_html=?, 
                        variaveis_usadas=?, ativo=?, atualizado_em=CURRENT_TIMESTAMP WHERE id=?";
                $result = run_query($sql, [$titulo, $descricao, $categoria, $conteudo_html, $variaveis_usadas, $ativo, $id]);
                
                if ($result) {
                    log_user_action('editar', 'modelos_documentos', $id, "Editou modelo: $titulo");
                    header('Location: modelos_documentos.php?success=' . urlencode('Modelo atualizado com sucesso'));
                    exit;
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            die('Token CSRF inválido');
        }
        
        $sql = "DELETE FROM modelos_documentos WHERE id=?";
        $result = run_query($sql, [$id]);
        
        if ($result) {
            log_user_action('excluir', 'modelos_documentos', $id, "Excluiu modelo ID: $id");
            header('Location: modelos_documentos.php?success=' . urlencode('Modelo excluído com sucesso'));
            exit;
        }
    } elseif ($action === 'duplicate') {
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            die('Token CSRF inválido');
        }
        
        $sql = "SELECT * FROM modelos_documentos WHERE id=?";
        $modelo = run_query($sql, [$id], true);
        
        if ($modelo) {
            $novo_titulo = $modelo['titulo'] . ' (Cópia)';
            $sql = "INSERT INTO modelos_documentos (titulo, descricao, categoria, conteudo_html, variaveis_usadas, ativo) 
                    VALUES (?, ?, ?, ?, ?, FALSE)";
            $result = run_query($sql, [
                $novo_titulo, 
                $modelo['descricao'], 
                $modelo['categoria'], 
                $modelo['conteudo_html'], 
                $modelo['variaveis_usadas']
            ]);
            
            if ($result) {
                log_user_action('duplicar', 'modelos_documentos', $id, "Duplicou modelo: {$modelo['titulo']}");
                header('Location: modelos_documentos.php?success=' . urlencode('Modelo duplicado com sucesso'));
                exit;
            }
        }
    }
}

if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $categoria_filter = $_GET['categoria'] ?? '';
    
    $sql = "SELECT * FROM modelos_documentos WHERE 1=1";
    $params = [];
    
    if ($search) {
        $busca = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);
        $like = '%' . $busca . '%';
        $sql .= " AND (LOWER(titulo) LIKE ? OR LOWER(descricao) LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }
    
    if ($categoria_filter) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria_filter;
    }
    
    $sql .= " ORDER BY criado_em DESC";
    $modelos = run_query($sql, $params);
    
    $sql_categorias = "SELECT DISTINCT categoria FROM modelos_documentos ORDER BY categoria";
    $categorias = run_query($sql_categorias);
}

if ($action === 'edit' && $id) {
    $sql = "SELECT * FROM modelos_documentos WHERE id=?";
    $modelo = run_query($sql, [$id], true);
    
    if (!$modelo) {
        header('Location: modelos_documentos.php?error=' . urlencode('Modelo não encontrado'));
        exit;
    }
}

include '../inc/template_base.php';
?>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>
                        <?php 
                        if ($action === 'create') echo 'Novo Modelo de Documento';
                        elseif ($action === 'edit') echo 'Editar Modelo de Documento';
                        else echo 'Modelos de Documentos';
                        ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Modelos de Documentos</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($success_msg) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error_msg) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Gerenciar Modelos</h3>
                        <div class="card-tools">
                            <a href="?action=create" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Novo Modelo
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-3">
                            <div class="row">
                                <div class="col-md-5">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Buscar por título ou descrição..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div class="col-md-4">
                                    <select name="categoria" class="form-control">
                                        <option value="">Todas as Categorias</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['categoria']) ?>" 
                                                    <?= $categoria_filter === $cat['categoria'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['categoria']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                    <a href="modelos_documentos.php" class="btn btn-secondary">Limpar</a>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Categoria</th>
                                        <th>Descrição</th>
                                        <th>Variáveis</th>
                                        <th>Status</th>
                                        <th>Criado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($modelos)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Nenhum modelo encontrado</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($modelos as $m): ?>
                                            <tr>
                                                <td><?= $m['id'] ?></td>
                                                <td><strong><?= htmlspecialchars($m['titulo']) ?></strong></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?= htmlspecialchars($m['categoria']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars(substr($m['descricao'] ?? '', 0, 100)) ?></td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= $m['variaveis_usadas'] ? count(explode(',', $m['variaveis_usadas'])) : 0 ?> variáveis
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($m['ativo']): ?>
                                                        <span class="badge badge-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
                                                <td>
                                                    <a href="?action=edit&id=<?= $m['id'] ?>" 
                                                       class="btn btn-sm btn-warning" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <form method="POST" style="display:inline;" 
                                                          onsubmit="return confirm('Duplicar este modelo?')">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_field() ?>">
                                                        <button type="submit" name="action" value="duplicate" 
                                                                class="btn btn-sm btn-info" title="Duplicar">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="POST" action="?action=delete&id=<?= $m['id'] ?>" 
                                                          style="display:inline;" 
                                                          onsubmit="return confirm('Tem certeza que deseja excluir?')">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_field() ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <div class="row">
                    <div class="col-md-9">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?= $action === 'create' ? 'Criar Novo Modelo' : 'Editar Modelo' ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_field() ?>">
                                    
                                    <div class="form-group">
                                        <label for="titulo">Título do Modelo *</label>
                                        <input type="text" class="form-control" id="titulo" name="titulo" 
                                               value="<?= htmlspecialchars($modelo['titulo'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="categoria">Categoria *</label>
                                        <input type="text" class="form-control" id="categoria" name="categoria" 
                                               value="<?= htmlspecialchars($modelo['categoria'] ?? 'Proposta Comercial') ?>" 
                                               placeholder="Ex: Proposta Comercial, Contrato, Orçamento" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="descricao">Descrição</label>
                                        <textarea class="form-control" id="descricao" name="descricao" 
                                                  rows="2"><?= htmlspecialchars($modelo['descricao'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="conteudo_html">Conteúdo do Modelo *</label>
                                        <textarea id="conteudo_html" name="conteudo_html" class="form-control"><?= htmlspecialchars($modelo['conteudo_html'] ?? '') ?></textarea>
                                        <small class="form-text text-muted">
                                            Use as variáveis da lista ao lado para criar um modelo dinâmico.
                                        </small>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" id="ativo" name="ativo" 
                                               <?= ($modelo['ativo'] ?? true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ativo">Modelo Ativo</label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Salvar Modelo
                                        </button>
                                        <a href="modelos_documentos.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Voltar
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Variáveis Disponíveis</h3>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <p class="text-sm">Clique para copiar e colar no editor:</p>
                                
                                <h6 class="mt-3"><strong>Cliente</strong></h6>
                                <div class="list-group list-group-flush">
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{cliente_nome}}">
                                        <code>{{cliente_nome}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{cliente_cnpj}}">
                                        <code>{{cliente_cnpj}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{cliente_endereco}}">
                                        <code>{{cliente_endereco}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{cliente_email}}">
                                        <code>{{cliente_email}}</code>
                                    </button>
                                </div>
                                
                                <h6 class="mt-3"><strong>Vendedor</strong></h6>
                                <div class="list-group list-group-flush">
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{vendedor_nome}}">
                                        <code>{{vendedor_nome}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{vendedor_email}}">
                                        <code>{{vendedor_email}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{vendedor_telefone}}">
                                        <code>{{vendedor_telefone}}</code>
                                    </button>
                                </div>
                                
                                <h6 class="mt-3"><strong>Proposta</strong></h6>
                                <div class="list-group list-group-flush">
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{proposta_numero}}">
                                        <code>{{proposta_numero}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{proposta_data}}">
                                        <code>{{proposta_data}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{proposta_validade}}">
                                        <code>{{proposta_validade}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{proposta_descricao}}">
                                        <code>{{proposta_descricao}}</code>
                                    </button>
                                </div>
                                
                                <h6 class="mt-3"><strong>Valores</strong></h6>
                                <div class="list-group list-group-flush">
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{valor_total}}">
                                        <code>{{valor_total}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{valor_desconto}}">
                                        <code>{{valor_desconto}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{valor_final}}">
                                        <code>{{valor_final}}</code>
                                    </button>
                                </div>
                                
                                <h6 class="mt-3"><strong>Empresa</strong></h6>
                                <div class="list-group list-group-flush">
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{empresa_nome}}">
                                        <code>{{empresa_nome}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{empresa_cnpj}}">
                                        <code>{{empresa_cnpj}}</code>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="{{empresa_endereco}}">
                                        <code>{{empresa_endereco}}</code>
                                    </button>
                                </div>
                                
                                <div class="alert alert-info mt-3 p-2">
                                    <small>
                                        <i class="fas fa-info-circle"></i>
                                        As variáveis serão substituídas automaticamente ao gerar a proposta.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                document.querySelectorAll('.var-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const variable = this.dataset.var;
                        navigator.clipboard.writeText(variable).then(() => {
                            this.classList.add('bg-success', 'text-white');
                            setTimeout(() => {
                                this.classList.remove('bg-success', 'text-white');
                            }, 500);
                        });
                    });
                });
                
                tinymce.init({
                    selector: '#conteudo_html',
                    height: 500,
                    menubar: true,
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                        'insertdatetime', 'media', 'table', 'help', 'wordcount'
                    ],
                    toolbar: 'undo redo | blocks | ' +
                        'bold italic forecolor | alignleft aligncenter ' +
                        'alignright alignjustify | bullist numlist outdent indent | ' +
                        'removeformat | table | help',
                    content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
                    language: 'pt_BR',
                    setup: function(editor) {
                        document.querySelectorAll('.var-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const variable = this.dataset.var;
                                editor.insertContent(variable);
                            });
                        });
                    }
                });
                </script>

            <?php endif; ?>
        </div>
    </section>
</div>
