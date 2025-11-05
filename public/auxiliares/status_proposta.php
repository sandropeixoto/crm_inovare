<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin', 'gestor']);

// Esta rota foi substituída pela visão informativa em status_propostas.php.
// Redirecionamos para evitar erro 500 em ambientes sem a tabela auxiliar.
header('Location: ' . app_url('auxiliares/status_propostas.php'));
exit;
