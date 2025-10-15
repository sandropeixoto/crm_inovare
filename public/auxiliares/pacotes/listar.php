<?php
require_once __DIR__ . '/../../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = 'Pacotes';
$breadcrumb = 'Auxiliares > Pacotes';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$tiposValidos = ['fixo','sinistralidade','franquia'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['_token'] ?? null);
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Identificador do pacote inválido.';
        } else {
            try {
                run_query('DELETE FROM pacotes WHERE id = ?', [$id]);
                log_user_action(current_user()['id'] ?? null, 'Excluiu pacote', 'pacotes', $id, null, null);
                $_SESSION['flash_success'] = 'Pacote removido com sucesso.';
            } catch (Throwable $e) {
                log_system('error', 'Falha ao excluir pacote: ' . $e->getMessage(), __FILE__, __LINE__);
                $_SESSION['flash_error'] = 'Não foi possível excluir o pacote. Verifique dependências.';
            }
        }
    }

    $redirect = app_url('auxiliares/pacotes/listar.php');
    header('Location: ' . $redirect);
    exit;
}

$busca = trim((string)($_GET['busca'] ?? ''));
$filtroTipo = (string)($_GET['tipo_calculo'] ?? '');
$filtroAtivo = (string)($_GET['ativo'] ?? '');

$condicoes = [];
$params = [];

if ($busca !== '') {
    $condicoes[] = 'p.nome LIKE ?';
    $params[] = '%' . $busca . '%';
}

if (in_array($filtroTipo, $tiposValidos, true)) {
    $condicoes[] = 'p.tipo_calculo = ?';
    $params[] = $filtroTipo;
} else {
    $filtroTipo = '';
}

if ($filtroAtivo !== '' && in_array($filtroAtivo, ['0','1'], true)) {
    $condicoes[] = 'p.ativo = ?';
    $params[] = (int)$filtroAtivo;
} else {
    $filtroAtivo = '';
}

$sql = 'SELECT p.* FROM pacotes p';
if ($condicoes) {
    $sql .= ' WHERE ' . implode(' AND ', $condicoes);
}
$sql .= ' ORDER BY p.nome';

$pacotes = run_query($sql, $params);

function format_money_br($valor): string
{
    return number_format((float)$valor, 2, ',', '.');
}

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold text-primary mb-1">Gestão de Pacotes</h5>
        <p class="text-muted mb-0">Gerencie planos comerciais e valores padrão.</p>
      </div>
      <a href="<?= e(app_url('auxiliares/pacotes/editar.php')) ?>" class="btn btn-success">+ Novo Pacote</a>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success"><?= e($flashSuccess) ?></div>
    <?php elseif ($flashError): ?>
      <div class="alert alert-danger"><?= e($flashError) ?></div>
    <?php endif; ?>

    <form class="row g-2 mb-4" method="GET">
      <div class="col-md-4">
        <label class="form-label">Nome</label>
        <input type="text" name="busca" value="<?= e($busca) ?>" class="form-control" placeholder="Buscar por nome">
      </div>
      <div class="col-md-4">
        <label class="form-label">Tipo de Cálculo</label>
        <select name="tipo_calculo" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($tiposValidos as $tipo): ?>
            <option value="<?= e($tipo) ?>" <?= $filtroTipo === $tipo ? 'selected' : '' ?>><?= ucfirst($tipo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="ativo" class="form-select">
          <option value="">Todos</option>
          <option value="1" <?= $filtroAtivo === '1' ? 'selected' : '' ?>>Ativos</option>
          <option value="0" <?= $filtroAtivo === '0' ? 'selected' : '' ?>>Inativos</option>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-outline-primary w-100">Filtrar</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Nome</th>
            <th>Tipo de Cálculo</th>
            <th>Implantação (R$)</th>
            <th>Mensal (R$)</th>
            <th>Status</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$pacotes): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">Nenhum pacote encontrado.</td></tr>
          <?php else: ?>
            <?php foreach ($pacotes as $pacote): ?>
              <?php
                $badgeStatus = !empty($pacote['ativo']) ? 'bg-success' : 'bg-secondary';
                $labelStatus = !empty($pacote['ativo']) ? 'Ativo' : 'Inativo';
              ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= e($pacote['nome'] ?? '-') ?></div>
                  <?php if (!empty($pacote['conformidade'])): ?>
                    <div class="text-muted small"><?= e($pacote['conformidade']) ?></div>
                  <?php endif; ?>
                </td>
                <td><span class="badge bg-info text-dark"><?= e(ucfirst((string)($pacote['tipo_calculo'] ?? ''))) ?></span></td>
                <td><?= format_money_br($pacote['valor_implantacao_base'] ?? 0) ?></td>
                <td><?= format_money_br($pacote['valor_mensal_base'] ?? 0) ?></td>
                <td><span class="badge <?= $badgeStatus ?>"><?= e($labelStatus) ?></span></td>
                <td class="text-end">
                  <a href="<?= e(app_url('auxiliares/pacotes/editar.php?id=' . (int)$pacote['id'])) ?>" class="btn btn-sm btn-primary">Editar</a>
                  <a href="<?= e(app_url('auxiliares/pacotes_servicos/listar.php?id_pacote=' . (int)$pacote['id'])) ?>" class="btn btn-sm btn-outline-secondary">Ver Serviços</a>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Excluir o pacote selecionado? Esta ação remove os serviços vinculados.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int)$pacote['id'] ?>">
                    <button class="btn btn-sm btn-danger">Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../inc/template_base.php';
