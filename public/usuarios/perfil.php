<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();

$page_title = "Meu Perfil";
$breadcrumb = "Usuário > Meu Perfil";

$user = $_SESSION['user'];
$id = $user['id'];
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validate_csrf_token($_POST['_token'] ?? null);
  $senha_atual = $_POST['senha_atual'] ?? '';
  $nova_senha = $_POST['nova_senha'] ?? '';
  $confirmar = $_POST['confirmar'] ?? '';

  // Verifica senha atual
  $usuario = run_query("SELECT senha_hash FROM usuarios WHERE id=?", [$id])[0] ?? null;
  if (!$usuario || !password_verify($senha_atual, $usuario['senha_hash'])) {
    $erro = "❌ Senha atual incorreta.";
  } elseif (strlen($nova_senha) < 6) {
    $erro = "A nova senha deve ter pelo menos 6 caracteres.";
  } elseif ($nova_senha !== $confirmar) {
    $erro = "A confirmação não coincide com a nova senha.";
  } else {
    $hash = password_hash($nova_senha, PASSWORD_BCRYPT);
    run_query("UPDATE usuarios SET senha_hash=? WHERE id=?", [$hash, $id]);
    $mensagem = "✅ Senha alterada com sucesso!";
  }
}

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <h5 class="fw-bold text-primary mb-3">Meu Perfil</h5>
    <p class="text-muted small mb-4">Gerencie suas informações pessoais e altere sua senha com segurança.</p>

    <div class="row mb-4">
      <div class="col-md-6">
        <label class="form-label fw-semibold">Nome</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($user['nome']) ?>" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">Email</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
      </div>
    </div>

    <hr>

    <h6 class="fw-bold text-secondary mb-3">Alterar Senha</h6>
    <?php if($mensagem): ?>
      <div class="alert alert-success py-2"><?= $mensagem ?></div>
    <?php elseif($erro): ?>
      <div class="alert alert-danger py-2"><?= $erro ?></div>
    <?php endif; ?>

    <form method="POST" style="max-width:500px;">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Senha Atual</label>
        <input type="password" name="senha_atual" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Nova Senha</label>
        <input type="password" name="nova_senha" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirmar Nova Senha</label>
        <input type="password" name="confirmar" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary">Atualizar Senha</button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
