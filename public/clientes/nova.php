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
    'endereco' => '',
    'bairro' => '',
    'cep' => '',
    'cidade' => '',
    'uf' => '',
    'qtd_colaboradores' => '0',
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
    $dados['cep'] = preg_replace('/\D+/', '', $dados['cep']);
    $dados['cnpj'] = preg_replace('/\D+/', '', $dados['cnpj']);
    $dados['telefone'] = preg_replace('/\D+/', '', $dados['telefone']);
    $dados['qtd_colaboradores'] = (string)max(0, (int)$dados['qtd_colaboradores']);
    $dados['responsavel_comercial'] = current_user()['id'] ?? null;

    if ($dados['nome_fantasia'] === '') {
        $erro = 'Informe o nome fantasia do cliente.';
    } else {
        $sql = "INSERT INTO clientes (nome_fantasia, razao_social, cnpj, email, telefone, endereco, bairro, cep, cidade, uf, qtd_colaboradores, status, origem, responsavel_comercial)"
             . " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        run_query($sql, [
            $dados['nome_fantasia'],
            $dados['razao_social'],
            $dados['cnpj'],
            $dados['email'],
            $dados['telefone'],
            $dados['endereco'],
            $dados['bairro'],
            $dados['cep'],
            $dados['cidade'],
            $dados['uf'],
            $dados['qtd_colaboradores'],
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
            'endereco' => '',
            'bairro' => '',
            'cep' => '',
            'cidade' => '',
            'uf' => '',
            'qtd_colaboradores' => '0',
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
      <input type="text" name="cnpj" class="form-control" data-mask="cnpj" value="<?= e($dados['cnpj']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">E-mail</label>
      <input type="email" name="email" class="form-control" value="<?= e($dados['email']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Telefone</label>
      <input type="text" name="telefone" class="form-control" data-mask="phone" value="<?= e($dados['telefone']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Endereço</label>
      <input type="text" name="endereco" class="form-control" value="<?= e($dados['endereco']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Bairro</label>
      <input type="text" name="bairro" class="form-control" value="<?= e($dados['bairro']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">CEP</label>
      <input type="text" name="cep" class="form-control" data-mask="cep" value="<?= e($dados['cep']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Cidade</label>
      <input type="text" name="cidade" class="form-control" value="<?= e($dados['cidade']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">UF</label>
      <input type="text" name="uf" maxlength="2" class="form-control text-uppercase" value="<?= e($dados['uf']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Qtd. de Colaboradores</label>
      <input type="number" name="qtd_colaboradores" class="form-control" min="0" value="<?= e($dados['qtd_colaboradores']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Origem</label>
      <select name="origem" class="form-select">
        <?php foreach (['manual','indicação','site','outro'] as $origem): ?>
          <option value="<?= e($origem) ?>" <?= $dados['origem'] === $origem ? 'selected' : '' ?>><?= ucfirst($origem) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const formatters = {
    cnpj: (value) => {
      const digits = value.slice(0, 14);
      const parts = [
        digits.slice(0, 2),
        digits.slice(2, 5),
        digits.slice(5, 8),
        digits.slice(8, 12),
        digits.slice(12, 14),
      ];
      let formatted = '';
      if (parts[0]) formatted = parts[0];
      if (parts[1]) formatted += '.' + parts[1];
      if (parts[2]) formatted += '.' + parts[2];
      if (parts[3]) formatted += '/' + parts[3];
      if (parts[4]) formatted += '-' + parts[4];
      return formatted;
    },
    phone: (value) => {
      const digits = value.slice(0, 11);
      if (digits.length <= 2) return digits;
      const ddd = digits.slice(0, 2);
      const middle = digits.length > 10 ? digits.slice(2, 7) : digits.slice(2, 6);
      const end = digits.length > 10 ? digits.slice(7, 11) : digits.slice(6, 10);
      let formatted = `(${ddd})`;
      if (middle) formatted += ` ${middle}`;
      if (end) formatted += `-${end}`;
      return formatted;
    },
    cep: (value) => {
      const digits = value.slice(0, 8);
      if (digits.length <= 5) return digits;
      return `${digits.slice(0, 5)}-${digits.slice(5, 8)}`;
    },
  };

  document.querySelectorAll('[data-mask]').forEach((input) => {
    const type = input.dataset.mask;
    const formatter = formatters[type];
    if (!formatter) {
      return;
    }

    const applyMask = () => {
      const digits = input.value.replace(/\D+/g, '');
      input.value = formatter(digits);
    };

    input.addEventListener('input', applyMask);
    input.addEventListener('blur', applyMask);
    applyMask();
  });
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
