<?php
require_once __DIR__ . '/../../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$tiposValidos = ['fixo','sinistralidade','franquia'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editar = $id > 0;

if ($editar) {
    $pacoteAtual = run_query('SELECT * FROM pacotes WHERE id = ?', [$id])[0] ?? null;
    if (!$pacoteAtual) {
        abort(404, 'Pacote não encontrado.');
    }
} else {
    $pacoteAtual = [
        'nome' => '',
        'descricao' => '',
        'conformidade' => '',
        'tipo_calculo' => 'fixo',
        'sinistralidade_padrao' => '0.00',
        'franquia_padrao' => '0.00',
        'valor_implantacao_base' => '0.00',
        'valor_mensal_base' => '0.00',
        'ativo' => 1,
    ];
}

$page_title = $editar ? 'Editar Pacote' : 'Novo Pacote';
$breadcrumb = 'Auxiliares > Pacotes > ' . ($editar ? 'Editar' : 'Novo');

$erros = [];
$formData = $pacoteAtual;

function normalizar_decimal(?string $valor, string $campo, array &$erros): ?string
{
    if ($valor === null) {
        return null;
    }
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }
    $valor = str_replace(['.', ' '], '', $valor);
    $valor = str_replace(',', '.', $valor);
    if (!is_numeric($valor)) {
        $erros[] = "Valor inválido para {$campo}.";
        return null;
    }
    return number_format((float)$valor, 2, '.', '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['_token'] ?? null);

    $formData['nome'] = trim((string)($_POST['nome'] ?? ''));
    $formData['descricao'] = trim((string)($_POST['descricao'] ?? ''));
    $formData['conformidade'] = trim((string)($_POST['conformidade'] ?? ''));
    $tipo = (string)($_POST['tipo_calculo'] ?? '');
    if (!in_array($tipo, $tiposValidos, true)) {
        $erros[] = 'Selecione um tipo de cálculo válido.';
    } else {
        $formData['tipo_calculo'] = $tipo;
    }

    $formData['sinistralidade_padrao'] = normalizar_decimal($_POST['sinistralidade_padrao'] ?? null, 'Sinistralidade padrão', $erros) ?? '0.00';
    $formData['franquia_padrao'] = normalizar_decimal($_POST['franquia_padrao'] ?? null, 'Franquia padrão', $erros) ?? '0.00';
    $formData['valor_implantacao_base'] = normalizar_decimal($_POST['valor_implantacao_base'] ?? null, 'Valor base de implantação', $erros) ?? '0.00';
    $formData['valor_mensal_base'] = normalizar_decimal($_POST['valor_mensal_base'] ?? null, 'Valor base mensal', $erros) ?? '0.00';
    $formData['ativo'] = isset($_POST['ativo']) ? 1 : 0;

    if ($formData['nome'] === '') {
        $erros[] = 'Informe o nome do pacote.';
    }

    if (!$erros) {
        $dados = [
            $formData['nome'],
            $formData['descricao'] !== '' ? $formData['descricao'] : null,
            $formData['conformidade'] !== '' ? $formData['conformidade'] : null,
            $formData['tipo_calculo'],
            $formData['sinistralidade_padrao'],
            $formData['franquia_padrao'],
            $formData['valor_implantacao_base'],
            $formData['valor_mensal_base'],
            $formData['ativo'],
        ];

        if ($editar) {
            $dados[] = $id;
            run_query('UPDATE pacotes SET nome=?, descricao=?, conformidade=?, tipo_calculo=?, sinistralidade_padrao=?, franquia_padrao=?, valor_implantacao_base=?, valor_mensal_base=?, ativo=? WHERE id=?', $dados);
            log_user_action(current_user()['id'] ?? null, 'Atualizou pacote', 'pacotes', $id, $pacoteAtual, $formData);
            $_SESSION['flash_success'] = 'Pacote atualizado com sucesso.';
        } else {
            run_query('INSERT INTO pacotes (nome, descricao, conformidade, tipo_calculo, sinistralidade_padrao, franquia_padrao, valor_implantacao_base, valor_mensal_base, ativo) VALUES (?,?,?,?,?,?,?,?,?)', $dados);
            $novoId = (int)pdo()->lastInsertId();
            log_user_action(current_user()['id'] ?? null, 'Criou pacote', 'pacotes', $novoId, null, $formData);
            $_SESSION['flash_success'] = 'Pacote criado com sucesso.';
        }

        header('Location: ' . app_url('auxiliares/pacotes/listar.php'));
        exit;
    }
}

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold text-primary mb-0"><?= e($page_title) ?></h5>
      <a href="<?= e(app_url('auxiliares/pacotes/listar.php')) ?>" class="btn btn-outline-secondary">Voltar</a>
    </div>

    <?php if ($erros): ?>
      <div class="alert alert-danger">
        <strong>Verifique os pontos abaixo:</strong>
        <ul class="mb-0">
          <?php foreach ($erros as $erro): ?>
            <li><?= e($erro) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" class="row g-3">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <div class="col-md-6">
        <label class="form-label">Nome *</label>
        <input type="text" name="nome" class="form-control" value="<?= e($formData['nome'] ?? '') ?>" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Conformidade</label>
        <input type="text" name="conformidade" class="form-control" value="<?= e($formData['conformidade'] ?? '') ?>" placeholder="Ex: Atende NR-01, NR-07...">
      </div>

      <div class="col-md-12">
        <label class="form-label">Descrição</label>
        <textarea name="descricao" rows="3" class="form-control" placeholder="Resumo dos principais benefícios do pacote."><?= e($formData['descricao'] ?? '') ?></textarea>
      </div>

      <div class="col-md-4">
        <label class="form-label">Tipo de Cálculo *</label>
        <select name="tipo_calculo" class="form-select" required>
          <?php foreach ($tiposValidos as $tipo): ?>
            <option value="<?= e($tipo) ?>" <?= ($formData['tipo_calculo'] ?? '') === $tipo ? 'selected' : '' ?>><?= ucfirst($tipo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Sinistralidade Padrão (%)</label>
        <input type="number" step="0.01" min="0" name="sinistralidade_padrao" class="form-control" value="<?= e(number_format((float)($formData['sinistralidade_padrao'] ?? 0), 2, '.', '')) ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Franquia Padrão (%)</label>
        <input type="number" step="0.01" min="0" name="franquia_padrao" class="form-control" value="<?= e(number_format((float)($formData['franquia_padrao'] ?? 0), 2, '.', '')) ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Valor Base de Implantação (R$)</label>
        <input type="number" step="0.01" min="0" name="valor_implantacao_base" class="form-control" value="<?= e(number_format((float)($formData['valor_implantacao_base'] ?? 0), 2, '.', '')) ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Valor Base Mensal (R$)</label>
        <input type="number" step="0.01" min="0" name="valor_mensal_base" class="form-control" value="<?= e(number_format((float)($formData['valor_mensal_base'] ?? 0), 2, '.', '')) ?>">
      </div>

      <div class="col-md-4 d-flex align-items-center">
        <div class="form-check mt-4">
          <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" <?= !empty($formData['ativo']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="ativo">Pacote ativo</label>
        </div>
      </div>

      <div class="col-12 text-end">
        <button class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../inc/template_base.php';
