<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID de cliente inválido.');
}

// Buscar cliente atual
$sql = "SELECT * FROM clientes WHERE id = ?";
$cliente = run_query($sql, [$id])[0] ?? null;

if (!$cliente) {
    http_response_code(404);
    exit('Cliente não encontrado.');
}

$mensagem = $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'nome_fantasia' => trim($_POST['nome_fantasia'] ?? ''),
        'razao_social' => trim($_POST['razao_social'] ?? ''),
        'cnpj' => trim($_POST['cnpj'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
        'cidade' => trim($_POST['cidade'] ?? ''),
        'uf' => trim($_POST['uf'] ?? ''),
        'status' => $_POST['status'] ?? 'prospecto',
        'origem' => $_POST['origem'] ?? 'manual'
    ];

    if (!$dados['nome_fantasia']) {
        $erro = 'Informe o nome fantasia.';
    } else {
        $campos = [];
        $params = [];
        foreach ($dados as $k => $v) {
            if ($cliente[$k] != $v) {
                $campos[] = "$k = ?";
                $params[] = $v;
            }
        }
        if ($campos) {
            $params[] = $id;
            $sqlUp = "UPDATE clientes SET " . implode(', ', $campos) . ", atualizado_em = NOW() WHERE id = ?";
            run_query($sqlUp, $params);
            log_user_action($_SESSION['user']['id'], 'Edição de cliente', 'clientes', $id, $cliente, $dados);
            $mensagem = "Cliente atualizado com sucesso!";
            $cliente = array_merge($cliente, $dados);
        } else {
            $mensagem = "Nenhuma alteração detectada.";
        }
    }
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function selected($a,$b){return $a===$b?'selected':'';}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Editar Cliente - CRM Inovare</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h4>Editar Cliente</h4>
  <?php if ($mensagem): ?>
    <div class="alert alert-success"><?= h($mensagem) ?></div>
  <?php elseif ($erro): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
  <?php endif; ?>

  <form method="POST" class="card p-3 shadow-sm bg-white">
    <div class="row mb-2">
      <div class="col-md-6">
        <label class="form-label">Nome Fantasia *</label>
        <input type="text" name="nome_fantasia" value="<?= h($cliente['nome_fantasia']) ?>" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Razão Social</label>
        <input type="text" name="razao_social" value="<?= h($cliente['razao_social']) ?>" class="form-control">
      </div>
    </div>

    <div class="row mb-2">
      <div class="col-md-4">
        <label class="form-label">CNPJ</label>
        <input type="text" name="cnpj" value="<?= h($cliente['cnpj']) ?>" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" value="<?= h($cliente['email']) ?>" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Telefone</label>
        <input type="text" name="telefone" value="<?= h($cliente['telefone']) ?>" class="form-control">
      </div>
    </div>

    <div class="row mb-2">
      <div class="col-md-6">
        <label class="form-label">Cidade</label>
        <input type="text" name="cidade" value="<?= h($cliente['cidade']) ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">UF</label>
        <input type="text" name="uf" maxlength="2" value="<?= h($cliente['uf']) ?>" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Origem</label>
        <select name="origem" class="form-select">
          <option value="manual" <?= selected($cliente['origem'],'manual') ?>>Manual</option>
          <option value="indicação" <?= selected($cliente['origem'],'indicação') ?>>Indicação</option>
          <option value="site" <?= selected($cliente['origem'],'site') ?>>Site</option>
          <option value="outro" <?= selected($cliente['origem'],'outro') ?>>Outro</option>
        </select>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="prospecto" <?= selected($cliente['status'],'prospecto') ?>>Prospecto</option>
          <option value="ativo" <?= selected($cliente['status'],'ativo') ?>>Ativo</option>
          <option value="inativo" <?= selected($cliente['status'],'inativo') ?>>Inativo</option>
        </select>
      </div>
    </div>

    <div>
      <button type="submit" class="btn btn-primary">Salvar Alterações</button>
      <a href="listar.php" class="btn btn-secondary">Voltar</a>
    </div>
  </form>
</div>
</body>
</html>
