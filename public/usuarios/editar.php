<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

// Detecta se é edição ou criação
$id = $_GET['id'] ?? null;
$editando = !empty($id);
$page_title = $editando ? "Editar Usuário" : "Novo Usuário";
$breadcrumb = "Administração > Usuários > " . ($editando ? "Editar" : "Novo");

// Carrega dados se estiver editando
$usuario = $editando ? run_query("SELECT * FROM usuarios WHERE id=?", [$id])[0] ?? null : ['ativo'=>1];

// Se não encontrou o usuário (id inválido), volta à listagem
if ($editando && !$usuario) {
  header("Location: /inovare/public/usuarios/listar.php");
  exit;
}

// Salvar (criar/editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validate_csrf_token($_POST['_token'] ?? null);
  $id = $_POST['id'] ?? null;
  $nome = trim($_POST['nome']);
  $email = trim($_POST['email']);
  $perfil = $_POST['perfil'];
  $ativo = isset($_POST['ativo']) ? 1 : 0;
  $senha = $_POST['senha'] ?? '';

  if ($id) {
    // Edição
    if ($senha) {
      $hash = password_hash($senha, PASSWORD_BCRYPT);
      run_query("UPDATE usuarios SET nome=?, email=?, perfil=?, ativo=?, senha_hash=? WHERE id=?",
        [$nome, $email, $perfil, $ativo, $hash, $id]);
    } else {
      run_query("UPDATE usuarios SET nome=?, email=?, perfil=?, ativo=? WHERE id=?",
        [$nome, $email, $perfil, $ativo, $id]);
    }
  } else {
    // Criação
    $hash = password_hash($senha ?: '123456', PASSWORD_BCRYPT);
    run_query("INSERT INTO usuarios (nome,email,perfil,ativo,senha_hash) VALUES (?,?,?,?,?)",
      [$nome, $email, $perfil, $ativo, $hash]);
  }

  redirect(app_url('usuarios/listar.php'));
}

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold text-primary mb-0">
        <?= $editando ? 'Editar Usuário' : 'Novo Usuário' ?>
      </h5>
      <a href="<?= e(app_url('usuarios/listar.php')) ?>" class="btn btn-secondary btn-sm">← Voltar</a>
    </div>

    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= htmlspecialchars($usuario['id'] ?? '') ?>">

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Nome</label>
          <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" class="form-control" required autofocus>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" class="form-control" required>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">Perfil</label>
          <select name="perfil" class="form-select">
            <?php foreach(['admin','gestor','usuario'] as $p): ?>
              <option value="<?= $p ?>" <?= ($usuario['perfil'] ?? '')==$p ? 'selected' : '' ?>>
                <?= ucfirst($p) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4 mb-3">
          <label class="form-label">
            <?= $editando ? 'Nova Senha (opcional)' : 'Senha Inicial' ?>
          </label>
          <input type="password" name="senha" class="form-control" placeholder="<?= $editando ? 'Deixe em branco para manter' : '123456 (padrão)' ?>">
        </div>

        <div class="col-md-4 mb-3 d-flex align-items-center">
          <div class="form-check mt-4">
            <input type="checkbox" name="ativo" class="form-check-input" id="ativo" <?= ($usuario['ativo'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ativo">Ativo</label>
          </div>
        </div>
      </div>

      <div class="mt-3">
        <button class="btn btn-primary"><?= $editando ? 'Salvar Alterações' : 'Criar Usuário' ?></button>
        <a href="<?= e(app_url('usuarios/listar.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
