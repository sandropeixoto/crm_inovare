<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
  run_query("DELETE FROM usuarios WHERE id=?", [$id]);
}
header("Location: /inovare/public/usuarios/listar.php");
exit;
