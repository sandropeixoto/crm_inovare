<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial']);

$sucesso = $erro = '';
$dados = [
    'nome_fantasia' => '',
    'razao_social' => '',
    'cnpj' => '',
    'email' => '',
    'telefone' => '',
    'cidade' => '',
    'uf' => '',
    'status' => 'prospecto',
    'origem' => 'manual',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['_token'] ?? null);

    foreach (array_keys($dados) as $campo) {
        $dados[$campo] = trim($_POST[$campo] ?? $dados[$campo]);
    }
    $dados['status'] = $_POST['status'] ?? 'prospecto';
    $dados['origem'] = $_POST['origem'] ?? 'manual';
    $dados['responsavel_comercial'] = current_user()['id'] ?? null;

    if (!$dados['nome_fantasia']) {
        $erro = 'Informe o nome fantasia do cliente.';
    } else {
        $sql = "INSERT INTO clientes (nome_fantasia, razao_social, cnpj, email, telefone, cidade, uf, status, origem, responsavel_comercial)"
             . " VALUES (?,?,?,?,?,?,?,?,?,?)";
        run_query($sql, [
            $dados['nome_fantasia'],
            $dados['razao_social'],
            $dados['cnpj'],
            $dados['email'],
            $dados['telefone'],
            $dados['cidade'],
            $dados['uf'],
            $dados['status'],
            $dados['origem'],
            $dados['responsavel_comercial'],
        ]);

        $novoId = (int)pdo()->lastInsertId();
        log_user_action(current_user()['id'] ?? null, 'Cadastro de cliente', 'clientes', $novoId, null, $dados);

        $sucesso = 'Cliente cadastrado com sucesso!';
        $dados = [
            'nome_fantasia' => '',
            'razao_social' => '',
            'cnpj' => '',
            'email' => '',
            'telefone' => '',
            'cidade' => '',
            'uf' => '',
            'status' => 'prospecto',
            'origem' => 'manual',
        ];
    }
}

$page_title = 'Novo Cliente';
$breadcrumb = 'Clientes > Novo cadastro';

ob_start();
?>
<?php if ($sucesso): ?>
  <div class="alert alert-success"><?= e($sucesso) ?></div>
<?php elseif ($erro): ?>
  <div class="alert alert-danger"><?= e($erro) ?></div>
<?php endif; ?>

<form method="POST" class="card p-4 shadow-sm">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Nome Fantasia *</label>
      <input type="text" name="nome_fantasia" class="form-control" value="<?= e($dados['nome_fantasia']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Razão Social</label>
      <input type="text" name="razao_social" class="form-control" value="<?= e($dados['razao_social']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">CNPJ</label>
      <input type="text" name="cnpj" class="form-control" value="<?= e($dados['cnpj']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">E-mail</label>
      <input type="email" name="email" class="form-control" value="<?= e($dados['email']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Telefone</label>
      <input type="text" name="telefone" class="form-control" value="<?= e($dados['telefone']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Cidade</label>
      <input type="text" name="cidade" class="form-control" value="<?= e($dados['cidade']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">UF</label>
      <input type="text" name="uf" maxlength="2" class="form-control" value="<?= e($dados['uf']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Origem</label>
      <select name="origem" class="form-select">
        <?php foreach (['manual','indicação','site','outro'] as $origem): ?>
          <option value="<?= e($origem) ?>" <?= $dados['origem'] === $origem ? 'selected' : '' ?>><?= ucfirst($origem) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['prospecto','ativo','inativo'] as $status): ?>
          <option value="<?= e($status) ?>" <?= $dados['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-success">Salvar Cliente</button>
    <a href="<?= e(app_url('clientes/listar.php')) ?>" class="btn btn-outline-secondary">Voltar</a>
  </div>
</form>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
?>
