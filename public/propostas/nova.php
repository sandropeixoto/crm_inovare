<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial']);

$clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
$query = $clienteId > 0 ? ('?cliente_id=' . $clienteId) : '';

redirect(app_url('propostas/editar.php' . $query));
