<?php
require_once __DIR__ . '/../../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$idPacote = isset($_GET['id_pacote']) ? (int)$_GET['id_pacote'] : 0;
$idServico = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPacote = (int)($_POST['id_pacote'] ?? 0);
    $idServico = (int)($_POST['id'] ?? 0);
}

if ($idPacote <= 0) {
    abort(400, 'Pacote não informado.');
}

$pacote = run_query('SELECT id, nome FROM pacotes WHERE id = ?', [$idPacote])[0] ?? null;
if (!$pacote) {
    abort(404, 'Pacote não encontrado.');
}

$editar = $idServico > 0;
$servicoAtual = null;
$formData = [
    'titulo' => '',
    'descricao' => '',
];

if ($editar) {
    $servicoAtual = run_query('SELECT * FROM pacotes_servicos WHERE id = ? AND id_pacote = ?', [$idServico, $idPacote])[0] ?? null;
    if (!$servicoAtual) {
        abort(404, 'Serviço não encontrado para este pacote.');
    }
    $formData = $servicoAtual;
}

$page_title = $editar ? 'Editar Serviço' : 'Novo Serviço';
$breadcrumb = 'Auxiliares > Pacotes > Serviços > ' . ($editar ? 'Editar' : 'Novo');

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['_token'] ?? null);

    $formData['titulo'] = trim((string)($_POST['titulo'] ?? ''));
    $formData['descricao'] = trim((string)($_POST['descricao'] ?? ''));

    if ($formData['titulo'] === '') {
        $erros[] = 'Informe o título do serviço.';
    }

    if (!$erros) {
        if ($editar) {
            run_query('UPDATE pacotes_servicos SET titulo = ?, descricao = ? WHERE id = ? AND id_pacote = ?', [
                $formData['titulo'],
                $formData['descricao'] !== '' ? $formData['descricao'] : null,
                $idServico,
                $idPacote,
            ]);
            log_user_action(current_user()['id'] ?? null, 'Atualizou serviço de pacote', 'pacotes_servicos', $idServico, $servicoAtual ?? null, $formData);
            $_SESSION['flash_success'] = 'Serviço atualizado com sucesso.';
        } else {
            run_query('INSERT INTO pacotes_servicos (id_pacote, titulo, descricao) VALUES (?,?,?)', [
                $idPacote,
                $formData['titulo'],
                $formData['descricao'] !== '' ? $formData['descricao'] : null,
            ]);
            $novoId = (int)pdo()->lastInsertId();
            log_user_action(current_user()['id'] ?? null, 'Criou serviço de pacote', 'pacotes_servicos', $novoId, null, $formData);
            $_SESSION['flash_success'] = 'Serviço criado com sucesso.';
        }

        header('Location: ' . app_url('auxiliares/pacotes_servicos/listar.php?id_pacote=' . $idPacote));
        exit;
    }
}

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="fw-bold text-primary mb-0"><?= e($page_title) ?></h5>
        <div class="text-muted">
          Pacote: <span class="fw-semibold"><?= e($pacote['nome']) ?></span>
        </div>
      </div>
      <a href="<?= e(app_url('auxiliares/pacotes_servicos/listar.php?id_pacote=' . (int)$pacote['id'])) ?>" class="btn btn-outline-secondary">Voltar</a>
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
      <input type="hidden" name="id" value="<?= (int)$idServico ?>">
      <input type="hidden" name="id_pacote" value="<?= (int)$pacote['id'] ?>">

      <div class="col-12">
        <label class="form-label">Título *</label>
        <input type="text" name="titulo" class="form-control" value="<?= e($formData['titulo'] ?? '') ?>" required>
      </div>

      <div class="col-12">
        <label class="form-label">Descrição</label>
        <textarea name="descricao" rows="4" class="form-control" placeholder="Detalhes do serviço incluso."><?= e($formData['descricao'] ?? '') ?></textarea>
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
