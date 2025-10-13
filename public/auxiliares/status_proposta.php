<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = "Status de Propostas";
$breadcrumb = "Auxiliares > Status de Propostas";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $acao = $_POST['acao'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  $nome = trim($_POST['nome'] ?? '');
  $cor = trim($_POST['cor'] ?? '#0d6efd');
  $ativo = isset($_POST['ativo']) ? 1 : 0;

  if ($acao === 'salvar') {
    if ($id > 0) {
      run_query("UPDATE status_proposta SET nome=?, cor=?, ativo=? WHERE id=?", [$nome, $cor, $ativo, $id]);
      log_user_action($_SESSION['user']['id'], 'Atualizou status_proposta', 'status_proposta', $id, null, $_POST);
    } else {
      run_query("INSERT INTO status_proposta (nome, cor, ativo) VALUES (?,?,?)", [$nome, $cor, $ativo]);
      $id = pdo()->lastInsertId();
      log_user_action($_SESSION['user']['id'], 'Criou status_proposta', 'status_proposta', $id, null, $_POST);
    }
  } elseif ($acao === 'excluir' && $id > 0) {
    run_query("DELETE FROM status_proposta WHERE id=?", [$id]);
    log_user_action($_SESSION['user']['id'], 'Excluiu status_proposta', 'status_proposta', $id);
  }
}

$dados = run_query("SELECT * FROM status_proposta ORDER BY id DESC");

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold text-primary">Lista de Status</h6>
      <button class="btn btn-sm btn-success" onclick="abrirModal()">+ Novo Status</button>
    </div>
    <table class="table table-striped table-sm align-middle">
      <thead class="table-light">
        <tr><th>ID</th><th>Nome</th><th>Cor</th><th>Ativo</th><th class="text-end">Ações</th></tr>
      </thead>
      <tbody>
        <?php foreach($dados as $d): ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['nome']) ?></td>
          <td><span class="badge" style="background:<?= htmlspecialchars($d['cor']) ?>">&nbsp;&nbsp;&nbsp;</span> <?= htmlspecialchars($d['cor']) ?></td>
          <td><?= $d['ativo'] ? '✅' : '❌' ?></td>
          <td class="text-end">
            <button class
