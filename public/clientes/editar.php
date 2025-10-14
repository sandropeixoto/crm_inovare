<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    abort(400, 'ID de cliente inválido.');
}

$cliente = run_query("SELECT * FROM clientes WHERE id = ?", [$id])[0] ?? null;
if (!$cliente) {
    abort(404, 'Cliente não encontrado.');
}

$mensagem = $erro = '';
$dados = $cliente;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['_token'] ?? null);

    $camposPermitidos = ['nome_fantasia','razao_social','cnpj','email','telefone','cidade','uf','status','origem'];
    $novosDados = [];
    foreach ($camposPermitidos as $campo) {
        $valor = trim($_POST[$campo] ?? '');
        $novosDados[$campo] = $valor;
    }

    if (!$novosDados['nome_fantasia']) {
        $erro = 'Informe o nome fantasia.';
    } else {
        $campos = [];
        $params = [];
        foreach ($novosDados as $campo => $valor) {
            if ($cliente[$campo] != $valor) {
                $campos[] = "$campo = ?";
                $params[] = $valor;
            }
        }
        if ($campos) {
            $params[] = $id;
            $sqlUp = "UPDATE clientes SET " . implode(', ', $campos) . ", atualizado_em = NOW() WHERE id = ?";
            run_query($sqlUp, $params);
            log_user_action(current_user()['id'] ?? null, 'Edição de cliente', 'clientes', $id, $cliente, $novosDados);
            $mensagem = 'Cliente atualizado com sucesso!';
            $cliente = array_merge($cliente, $novosDados);
            $dados = $cliente;
        } else {
            $mensagem = 'Nenhuma alteração detectada.';
        }
    }
}

$page_title = 'Editar Cliente';
$breadcrumb = 'Clientes > Edição';

ob_start();
?>
<?php if ($mensagem): ?>
  <div class="alert alert-success"><?= e($mensagem) ?></div>
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
    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
    <a href="<?= e(app_url('clientes/listar.php')) ?>" class="btn btn-outline-secondary">Voltar</a>
  </div>
</form>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
