<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial']);

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
        'origem' => $_POST['origem'] ?? 'manual',
        'responsavel_comercial' => $_SESSION['user']['id'] ?? null
    ];

    if (!$dados['nome_fantasia']) {
        $erro = "Informe o nome fantasia do cliente.";
    } else {
        $sql = "INSERT INTO clientes (nome_fantasia, razao_social, cnpj, email, telefone, cidade, uf, status, origem, responsavel_comercial)
                VALUES (?,?,?,?,?,?,?,?,?,?)";
        run_query($sql, array_values($dados));
        $id = pdo()->lastInsertId();

        log_user_action($_SESSION['user']['id'], 'Cadastro de cliente', 'clientes', (int)$id, null, $dados);
        $sucesso = "Cliente cadastrado com sucesso!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Novo Cliente - CRM Inovare</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h4>Novo Cliente</h4>
  <?php if (!empty($sucesso)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
  <?php elseif (!empty($erro)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <form method="POST" class="card p-3 shadow-sm bg-white">
    <div class="row mb-2">
      <div class="col-md-6">
        <label class="form-label">Nome Fantasia *</label>
        <input type="text" name="nome_fantasia" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Razão Social</label>
        <input type="text" name="razao_social" class="form-control">
      </div>
    </div>

    <div class="row mb-2">
      <div class="col-md-4">
        <label class="form-label">CNPJ</label>
        <input type="text" name="cnpj" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Telefone</label>
        <input type="text" name="telefone" class="form-control">
      </div>
    </div>

    <div class="row mb-2">
      <div class="col-md-6">
        <label class="form-label">Cidade</label>
        <input type="text" name="cidade" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">UF</label>
        <input type="text" name="uf" maxlength="2" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Origem</label>
        <select name="origem" class="form-select">
          <option value="manual">Manual</option>
          <option value="indicação">Indicação</option>
          <option value="site">Site</option>
          <option value="outro">Outro</option>
        </select>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="prospecto">Prospecto</option>
          <option value="ativo">Ativo</option>
          <option value="inativo">Inativo</option>
        </select>
      </div>
    </div>

    <button type="submit" class="btn btn-success">Salvar Cliente</button>
    <a href="../login.php" class="btn btn-secondary">Sair</a>
  </form>
</div>
</body>
</html>
