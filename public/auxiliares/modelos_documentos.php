<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin', 'gestor']);

$user = current_user();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

$modelo = [];
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : $id;

    validate_csrf_token($_POST['_token'] ?? null);
    $pdo = pdo();

    if ($action === 'create' || $action === 'edit') {
        $modelo = [
            'titulo'        => trim($_POST['titulo'] ?? ''),
            'descricao'     => trim($_POST['descricao'] ?? ''),
            'categoria'     => trim($_POST['categoria'] ?? ''),
            'conteudo_html' => $_POST['conteudo_html'] ?? '',
            'ativo'         => isset($_POST['ativo']) ? 1 : 0,
        ];

        if ($modelo['titulo'] === '') {
            $formErrors[] = 'Informe o título do modelo.';
        }
        if ($modelo['categoria'] === '') {
            $formErrors[] = 'Informe a categoria do modelo.';
        }
        if (trim((string)$modelo['conteudo_html']) === '') {
            $formErrors[] = 'Informe o conteúdo do modelo.';
        }

        preg_match_all('/\{\{([^}]+)\}\}/', (string)$modelo['conteudo_html'], $matches);
        $variaveis = !empty($matches[1]) ? array_unique(array_map('trim', $matches[1])) : [];
        $modelo['variaveis_usadas'] = implode(',', $variaveis);

        if (!$formErrors) {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO modelos_documentos (titulo, descricao, categoria, conteudo_html, variaveis_usadas, ativo)
                     VALUES (?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $modelo['titulo'],
                    $modelo['descricao'],
                    $modelo['categoria'],
                    $modelo['conteudo_html'],
                    $modelo['variaveis_usadas'],
                    $modelo['ativo'],
                ]);

                $novoId = (int)$pdo->lastInsertId();
                log_user_action(
                    $user['id'] ?? null,
                    'criar modelo de documento',
                    'modelos_documentos',
                    $novoId,
                    null,
                    ['titulo' => $modelo['titulo'], 'categoria' => $modelo['categoria']]
                );

                redirect(app_url('auxiliares/modelos_documentos.php?success=' . urlencode('Modelo criado com sucesso')));
            }

            if ($action === 'edit') {
                if ($id <= 0) {
                    redirect(app_url('auxiliares/modelos_documentos.php?error=' . urlencode('Modelo inválido')));
                }

                $anterior = run_query('SELECT * FROM modelos_documentos WHERE id = ?', [$id])[0] ?? null;
                if (!$anterior) {
                    redirect(app_url('auxiliares/modelos_documentos.php?error=' . urlencode('Modelo não encontrado')));
                }

                $stmt = $pdo->prepare(
                    'UPDATE modelos_documentos
                        SET titulo = ?, descricao = ?, categoria = ?, conteudo_html = ?, variaveis_usadas = ?, ativo = ?, atualizado_em = CURRENT_TIMESTAMP
                      WHERE id = ?'
                );
                $stmt->execute([
                    $modelo['titulo'],
                    $modelo['descricao'],
                    $modelo['categoria'],
                    $modelo['conteudo_html'],
                    $modelo['variaveis_usadas'],
                    $modelo['ativo'],
                    $id,
                ]);

                log_user_action(
                    $user['id'] ?? null,
                    'editar modelo de documento',
                    'modelos_documentos',
                    $id,
                    $anterior,
                    ['titulo' => $modelo['titulo'], 'categoria' => $modelo['categoria']]
                );

                redirect(app_url('auxiliares/modelos_documentos.php?success=' . urlencode('Modelo atualizado com sucesso')));
            }
        } else {
            $error_msg = implode(' ', $formErrors);
        }
    } elseif ($action === 'delete') {
        if ($id <= 0) {
            redirect(app_url('auxiliares/modelos_documentos.php?error=' . urlencode('Modelo inválido')));
        }

        $registro = run_query('SELECT * FROM modelos_documentos WHERE id = ?', [$id])[0] ?? null;
        if (!$registro) {
            redirect(app_url('auxiliares/modelos_documentos.php?error=' . urlencode('Modelo não encontrado')));
        }

        $stmt = $pdo->prepare('DELETE FROM modelos_documentos WHERE id = ?');
        $stmt->execute([$id]);

        log_user_action(
            $user['id'] ?? null,
            'excluir modelo de documento',
            'modelos_documentos',
            $id,
            $registro,
            null
        );

        redirect(app_url('auxiliares/modelos_documentos.php?success=' . urlencode('Modelo excluído com sucesso')));
    } elseif ($action === 'duplicate') {
        if ($id <= 0) {
            redirect(app_url('auxiliares/modelos_documentos.php?error=' . urlencode('Modelo inválido')));
        }

        $registro = run_query('SELECT * FROM modelos_documentos WHERE id = ?', [$id])[0] ?? null;
        if (!$registro) {
            redirect(app_url('auxiliares/modelos_documentos.php?error=' . urlencode('Modelo não encontrado')));
        }

        $novoTitulo = $registro['titulo'] . ' (Cópia)';
        $stmt = $pdo->prepare(
            'INSERT INTO modelos_documentos (titulo, descricao, categoria, conteudo_html, variaveis_usadas, ativo)
             VALUES (?,?,?,?,?,0)'
        );
        $stmt->execute([
            $novoTitulo,
            $registro['descricao'],
            $registro['categoria'],
            $registro['conteudo_html'],
            $registro['variaveis_usadas'],
        ]);

        $novoId = (int)$pdo->lastInsertId();
        log_user_action(
            $user['id'] ?? null,
            'duplicar modelo de documento',
            'modelos_documentos',
            $novoId,
            $registro,
            ['titulo' => $novoTitulo]
        );

        redirect(app_url('auxiliares/modelos_documentos.php?success=' . urlencode('Modelo duplicado com sucesso')));
    }
}

if (($action === 'edit') && !$modelo) {
    if ($id <= 0) {
        redirect(app_url('auxiliares/modelos_documentos.php?error=' . urlencode('Modelo inválido')));
    }
    $registro = run_query('SELECT * FROM modelos_documentos WHERE id = ?', [$id])[0] ?? null;
    if (!$registro) {
        redirect(app_url('auxiliares/modelos_documentos.php?error=' . urlencode('Modelo não encontrado')));
    }
    $modelo = $registro;
}

if ($action === 'create' && !$modelo) {
    $modelo = [
        'titulo'        => '',
        'descricao'     => '',
        'categoria'     => 'Proposta Comercial',
        'conteudo_html' => '',
        'ativo'         => 1,
    ];
}

$search = '';
$categoria_filter = '';
$modelos = [];
$categorias = [];

if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $categoria_filter = trim($_GET['categoria'] ?? '');

    $sql = 'SELECT * FROM modelos_documentos WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $busca = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);
        $like = '%' . $busca . '%';
        $sql .= ' AND (LOWER(titulo) LIKE ? OR LOWER(descricao) LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    if ($categoria_filter !== '') {
        $sql .= ' AND categoria = ?';
        $params[] = $categoria_filter;
    }

    $sql .= ' ORDER BY criado_em DESC';
    $modelos = run_query($sql, $params);
    $categorias = run_query('SELECT DISTINCT categoria FROM modelos_documentos ORDER BY categoria');
}

$page_title = 'Modelos de Documentos';
$breadcrumb = 'Auxiliares > Modelos de Documentos';

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body pb-1">
    <div class="d-flex justify-content-between align-items-center">
      <h5 class="fw-bold text-primary mb-0">Modelos de Documentos</h5>
      <?php if ($action === 'list'): ?>
        <a href="<?= e('modelos_documentos.php?action=create') ?>" class="btn btn-success btn-sm">
          <i class="fas fa-plus"></i> Novo Modelo
        </a>
      <?php else: ?>
        <a href="<?= e('modelos_documentos.php') ?>" class="btn btn-secondary btn-sm">
          <i class="fas fa-arrow-left"></i> Voltar
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($success_msg): ?>
  <div class="alert alert-success mt-3 alert-dismissible fade show">
    <?= e($success_msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if ($error_msg): ?>
  <div class="alert alert-danger mt-3 alert-dismissible fade show">
    <?= e($error_msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
  <div class="card mt-3">
    <div class="card-body">
      <form class="row g-2 align-items-end mb-3" method="GET">
        <div class="col-md-6">
          <label class="form-label">Buscar</label>
          <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Título ou descrição">
        </div>
        <div class="col-md-4">
          <label class="form-label">Categoria</label>
          <select name="categoria" class="form-select">
            <option value="">Todas</option>
            <?php foreach ($categorias as $cat): ?>
              <?php $valor = $cat['categoria'] ?? ''; ?>
              <option value="<?= e($valor) ?>" <?= $valor === $categoria_filter ? 'selected' : '' ?>>
                <?= e($valor) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 text-end">
          <button class="btn btn-primary w-100">Filtrar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Título</th>
              <th>Categoria</th>
              <th>Atualizado em</th>
              <th>Ativo</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$modelos): ?>
              <tr>
                <td colspan="6" class="text-center text-muted">Nenhum modelo cadastrado.</td>
              </tr>
            <?php else: foreach ($modelos as $mod): ?>
              <tr>
                <td><?= (int)$mod['id'] ?></td>
                <td><?= e($mod['titulo']) ?></td>
                <td><?= e($mod['categoria']) ?></td>
                <td>
                  <?php
                    $atualizado = $mod['atualizado_em'] ?? $mod['criado_em'] ?? '';
                    echo $atualizado ? date('d/m/Y H:i', strtotime((string)$atualizado)) : '-';
                  ?>
                </td>
                <td>
                  <span class="badge bg-<?= !empty($mod['ativo']) ? 'success' : 'secondary' ?>">
                    <?= !empty($mod['ativo']) ? 'Sim' : 'Não' ?>
                  </span>
                </td>
                <td class="text-end">
                  <a href="<?= e('modelos_documentos.php?action=edit&id=' . (int)$mod['id']) ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i> Editar
                  </a>
                  <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="id" value="<?= (int)$mod['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                      <i class="fas fa-copy"></i> Duplicar
                    </button>
                  </form>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este modelo?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$mod['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="fas fa-trash"></i> Excluir
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
  <div class="row mt-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">
            <?= $action === 'create' ? 'Criar Novo Modelo' : 'Editar Modelo' ?>
          </h5>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= e($action) ?>">
            <?php if ($action === 'edit'): ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
            <?php endif; ?>

            <div class="mb-3">
              <label class="form-label">Título *</label>
              <input type="text" name="titulo" class="form-control" value="<?= e($modelo['titulo'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Categoria *</label>
              <input type="text" name="categoria" class="form-control" value="<?= e($modelo['categoria'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Descrição</label>
              <textarea name="descricao" class="form-control" rows="2"><?= e($modelo['descricao'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">Conteúdo *</label>
              <textarea name="conteudo_html" id="conteudo_html" class="form-control"><?= e($modelo['conteudo_html'] ?? '') ?></textarea>
              <small class="text-muted">Use as variáveis ao lado para montar o documento.</small>
            </div>

            <div class="form-check mb-3">
              <input type="checkbox" class="form-check-input" id="ativo" name="ativo" <?= !empty($modelo['ativo']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ativo">Modelo ativo</label>
            </div>

            <div class="mt-4">
              <button type="submit" class="btn btn-success">
                <i class="fas fa-save me-1"></i> Salvar
              </button>
              <a href="<?= e('modelos_documentos.php') ?>" class="btn btn-outline-secondary">
                Cancelar
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4 mt-3 mt-lg-0">
      <div class="card card-primary">
        <div class="card-header">
          <h5 class="card-title mb-0">Variáveis Disponíveis</h5>
        </div>
        <div class="card-body" style="max-height: 600px; overflow-y: auto;">
          <p class="text-sm text-muted">Clique para inserir no editor:</p>

          <?php
          $grupos = [
              'Cliente' => [
                  '{{cliente_nome}}',
                  '{{cliente_cnpj}}',
                  '{{cliente_endereco}}',
                  '{{cliente_email}}',
              ],
              'Proposta' => [
                  '{{proposta_numero}}',
                  '{{proposta_data}}',
                  '{{proposta_validade}}',
                  '{{proposta_descricao}}',
                  '{{valor_total}}',
                  '{{valor_final}}',
              ],
              'Empresa' => [
                  '{{empresa_nome}}',
                  '{{empresa_cnpj}}',
                  '{{empresa_endereco}}',
                  '{{empresa_email}}',
              ],
          ];
          ?>

          <?php foreach ($grupos as $titulo => $vars): ?>
            <h6 class="mt-3"><strong><?= e($titulo) ?></strong></h6>
            <div class="list-group list-group-flush">
              <?php foreach ($vars as $var): ?>
                <button type="button" class="list-group-item list-group-item-action p-1 var-btn" data-var="<?= e($var) ?>">
                  <code><?= e($var) ?></code>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>

          <div class="alert alert-info mt-3 p-2">
            <small>
              <i class="fas fa-info-circle me-1"></i>
              As variáveis são preenchidas automaticamente ao gerar o documento.
            </small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
  <script>
    document.querySelectorAll('.var-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const variable = btn.dataset.var;
        if (window.tinymce?.activeEditor) {
          window.tinymce.activeEditor.insertContent(variable);
        } else {
          const textarea = document.querySelector('#conteudo_html');
          const start = textarea.selectionStart;
          const end = textarea.selectionEnd;
          const value = textarea.value;
          textarea.value = value.substring(0, start) + variable + value.substring(end);
        }
      });
    });

    tinymce.init({
      selector: '#conteudo_html',
      height: 520,
      language: 'pt_BR',
      menubar: true,
      plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
      ],
      toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | table | help',
      content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
    });
  </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
