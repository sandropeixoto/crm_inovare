<?php
require_once __DIR__ . '/../../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = 'Serviços do Pacote';
$breadcrumb = 'Auxiliares > Pacotes > Serviços';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['_token'] ?? null);
    $acao = $_POST['acao'] ?? '';
    $idPacotePost = (int)($_POST['id_pacote'] ?? 0);

    if ($acao === 'excluir') {
        $idServico = (int)($_POST['id'] ?? 0);
        if ($idServico <= 0 || $idPacotePost <= 0) {
            $_SESSION['flash_error'] = 'Identificador inválido para exclusão.';
        } else {
            try {
                run_query('DELETE FROM pacotes_servicos WHERE id = ? AND id_pacote = ?', [$idServico, $idPacotePost]);
                log_user_action(current_user()['id'] ?? null, 'Excluiu serviço de pacote', 'pacotes_servicos', $idServico, null, null);
                $_SESSION['flash_success'] = 'Serviço removido com sucesso.';
            } catch (Throwable $e) {
                log_system('error', 'Falha ao excluir serviço do pacote: ' . $e->getMessage(), __FILE__, __LINE__);
                $_SESSION['flash_error'] = 'Não foi possível excluir o serviço informado.';
            }
        }
    }

    $redirect = app_url('auxiliares/pacotes_servicos/listar.php' . ($idPacotePost > 0 ? '?id_pacote=' . $idPacotePost : ''));
    header('Location: ' . $redirect);
    exit;
}

$idPacote = isset($_GET['id_pacote']) ? (int)$_GET['id_pacote'] : 0;
$pacotesDisponiveis = run_query('SELECT id, nome FROM pacotes ORDER BY nome');

$pacoteSelecionado = null;
$servicos = [];

if ($idPacote > 0) {
    foreach ($pacotesDisponiveis as $registro) {
        if ((int)$registro['id'] === $idPacote) {
            $pacoteSelecionado = $registro;
            break;
        }
    }

    if ($pacoteSelecionado) {
        $servicos = run_query('SELECT * FROM pacotes_servicos WHERE id_pacote = ? ORDER BY id DESC', [$idPacote]);
    } else {
        $flashError = $flashError ?: 'Pacote não encontrado. Selecione novamente.';
        $idPacote = 0;
    }
}

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold text-primary mb-1">Serviços dos Pacotes</h5>
        <p class="text-muted mb-0">Cadastre os serviços entregues em cada pacote comercial.</p>
      </div>
      <a href="<?= e(app_url('auxiliares/pacotes/listar.php')) ?>" class="btn btn-outline-secondary">Voltar aos Pacotes</a>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success"><?= e($flashSuccess) ?></div>
    <?php elseif ($flashError): ?>
      <div class="alert alert-danger"><?= e($flashError) ?></div>
    <?php endif; ?>

    <form class="row g-2 mb-4" method="GET">
      <div class="col-md-8">
        <label class="form-label">Pacote</label>
        <select name="id_pacote" class="form-select" required>
          <option value="">Selecione...</option>
          <?php foreach ($pacotesDisponiveis as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $idPacote === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-outline-primary w-100">Carregar</button>
      </div>
      <div class="col-md-2 d-flex align-items-end justify-content-end">
        <?php if ($pacoteSelecionado): ?>
          <a href="<?= e(app_url('auxiliares/pacotes_servicos/editar.php?id_pacote=' . (int)$pacoteSelecionado['id'])) ?>" class="btn btn-success w-100">+ Novo Serviço</a>
        <?php else: ?>
          <button class="btn btn-success w-100" type="button" disabled>+ Novo Serviço</button>
        <?php endif; ?>
      </div>
    </form>

    <?php if (!$pacoteSelecionado): ?>
      <p class="text-muted">Selecione um pacote para visualizar e gerenciar os serviços vinculados.</p>
    <?php else: ?>
      <div class="mb-3">
        <span class="badge bg-primary">Pacote selecionado:</span>
        <span class="fw-semibold ms-2"><?= e($pacoteSelecionado['nome']) ?></span>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Título</th>
              <th>Descrição</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$servicos): ?>
              <tr><td colspan="3" class="text-center text-muted py-3">Nenhum serviço cadastrado para este pacote.</td></tr>
            <?php else: ?>
              <?php foreach ($servicos as $servico): ?>
                <tr>
                  <td class="fw-semibold"><?= e($servico['titulo'] ?? '-') ?></td>
                  <td><?= nl2br(e($servico['descricao'] ?? '-')) ?></td>
                  <td class="text-end">
                    <a href="<?= e(app_url('auxiliares/pacotes_servicos/editar.php?id=' . (int)$servico['id'] . '&id_pacote=' . (int)$idPacote)) ?>" class="btn btn-sm btn-primary">Editar</a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este serviço do pacote?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="acao" value="excluir">
                      <input type="hidden" name="id" value="<?= (int)$servico['id'] ?>">
                      <input type="hidden" name="id_pacote" value="<?= (int)$idPacote ?>">
                      <button class="btn btn-sm btn-danger">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../inc/template_base.php';
