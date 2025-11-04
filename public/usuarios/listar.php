<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = "Usuários";
$breadcrumb = "Administração > Usuários";

$usuarios = run_query("SELECT id, nome, email, perfil, ativo, strftime('%d/%m/%Y %H:%M', ultimo_login) AS ultimo_login FROM usuarios ORDER BY nome");

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold text-primary">Gestão de Usuários</h5>
      <a href="<?= e(app_url('usuarios/editar.php')) ?>" class="btn btn-success btn-sm">+ Novo Usuário</a>
    </div>

    <div class="table-responsive">
      <table class="table table-striped align-middle table-hover">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Perfil</th>
            <th>Ativo</th>
            <th>Último Login</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$usuarios): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">Nenhum usuário encontrado.</td></tr>
          <?php else: foreach($usuarios as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td><?= htmlspecialchars($u['nome']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= ucfirst($u['perfil']) ?></td>
              <td><?= $u['ativo'] ? '✅' : '❌' ?></td>
              <td><?= $u['ultimo_login'] ?: '-' ?></td>
              <td class="text-end">
                <a href="<?= e(app_url('usuarios/editar.php?id=' . (int)$u['id'])) ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                <form method="POST" action="<?= e(app_url('usuarios/excluir.php')) ?>" class="d-inline" onsubmit="return confirm('Excluir este usuário?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-sm btn-danger">🗑️ Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
