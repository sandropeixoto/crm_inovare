<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  abort(405, 'Método não permitido.');
}

validate_csrf_token($_POST['_token'] ?? null);

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
  run_query("DELETE FROM usuarios WHERE id=?", [$id]);
  log_user_action(current_user()['id'] ?? null, 'Excluiu usuário', 'usuarios', $id, null, null);
}

redirect(app_url('usuarios/listar.php'));
