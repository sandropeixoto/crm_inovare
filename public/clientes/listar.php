<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial']);

// parâmetros de busca
$nome = trim($_GET['nome'] ?? '');
$cidade = trim($_GET['cidade'] ?? '');
$status = $_GET['status'] ?? '';
$responsavel = $_GET['responsavel'] ?? '';

// paginação
$por_pagina = max(5, (int)($_GET['pp'] ?? 15));
$pagina = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pagina - 1) * $por_pagina;

// montar cláusulas dinâmicas
$where = [];
$params = [];

if ($nome !== '') {
    $where[] = "c.nome_fantasia LIKE ?";
    $params[] = '%' . $nome . '%';
}
if ($cidade !== '') {
    $where[] = "c.cidade LIKE ?";
    $params[] = '%' . $cidade . '%';
}
if ($status !== '' && in_array($status, ['ativo','inativo','prospecto'], true)) {
    $where[] = "c.status = ?";
    $params[] = $status;
}
if ($responsavel !== '' && ctype_digit($responsavel)) {
    $where[] = "c.responsavel_comercial = ?";
    $params[] = (int)$responsavel;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// total de registros
$sqlCount = "SELECT COUNT(*) AS total FROM clientes c {$whereSql}";
$total = (int)(run_query($sqlCount, $params)[0]['total'] ?? 0);
$paginas = max(1, (int)ceil($total / $por_pagina));

// busca principal
$sql = "SELECT c.*, u.nome AS responsavel_nome
        FROM clientes c
        LEFT JOIN usuarios u ON u.id = c.responsavel_comercial
        {$whereSql}
        ORDER BY c.criado_em DESC
        LIMIT {$por_pagina} OFFSET {$offset}";
$clientes = run_query($sql, $params);

// carregar opções de responsáveis
$usuarios = run_query("SELECT id, nome FROM usuarios WHERE ativo = TRUE ORDER BY nome ASC");

log_user_action(current_user()['id'] ?? null, 'Listou clientes', 'clientes', null, ['filtros' => $_GET], ['total' => $total]);

function selected($a, $b) { return ($a === $b) ? 'selected' : ''; }

function format_cnpj(?string $value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    if (strlen($digits) !== 14) {
        return $value ?? '';
    }

    return sprintf(
        '%s.%s.%s/%s-%s',
        substr($digits, 0, 2),
        substr($digits, 2, 3),
        substr($digits, 5, 3),
        substr($digits, 8, 4),
        substr($digits, 12, 2)
    );
}

function format_phone_list(?string $value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    if (strlen($digits) < 10) {
        return $value ?? '';
    }

    $ddd = substr($digits, 0, 2);
    $middle = strlen($digits) > 10 ? substr($digits, 2, 5) : substr($digits, 2, 4);
    $end = strlen($digits) > 10 ? substr($digits, 7) : substr($digits, 6);

    return sprintf('(%s) %s-%s', $ddd, $middle, $end);
}

$page_title = 'Clientes';
$breadcrumb = 'Comercial > Clientes';

ob_start();
?>
<div class="card mb-3 shadow-sm">
  <div class="card-body">
    <form class="row g-3" method="GET">
      <div class="col-md-3">
        <label class="form-label">Nome</label>
        <input type="text" name="nome" value="<?= e($nome) ?>" class="form-control" placeholder="Nome fantasia">
      </div>
      <div class="col-md-3">
        <label class="form-label">Cidade</label>
        <input type="text" name="cidade" value="<?= e($cidade) ?>" class="form-control" placeholder="Cidade">
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">Todos</option>
          <option value="prospecto" <?= selected($status, 'prospecto') ?>>Prospecto</option>
          <option value="ativo" <?= selected($status, 'ativo') ?>>Ativo</option>
          <option value="inativo" <?= selected($status, 'inativo') ?>>Inativo</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Responsável</label>
        <select name="responsavel" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($usuarios as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= selected($responsavel, (string)$u['id']) ?>>
              <?= e($u['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button class="btn btn-primary w-100" type="submit">Buscar</button>
      </div>
    </form>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-semibold mb-0">Clientes cadastrados</h5>
  <a href="<?= e(app_url('clientes/nova.php')) ?>" class="btn btn-success btn-sm">+ Novo Cliente</a>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Nome Fantasia</th>
            <th>Cidade/UF</th>
            <th>Status</th>
            <th>Responsável</th>
            <th>Colaboradores</th>
            <th>Criado em</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$clientes): ?>
          <tr><td colspan="8" class="text-center py-4">Nenhum cliente encontrado.</td></tr>
        <?php else: ?>
            <?php foreach ($clientes as $c): ?>
              <?php
                $statusLabel = ucfirst((string)$c['status']);
                $statusClass = match ($c['status']) {
                  'ativo' => 'success',
                  'prospecto' => 'warning text-dark',
                  default => 'secondary'
                };
                $telefoneFormatado = format_phone_list($c['telefone'] ?? '');
                $bairro = trim((string)($c['bairro'] ?? ''));
              ?>
              <tr>
                <td><?= (int)$c['id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= e($c['nome_fantasia']) ?></div>
                  <div class="small text-muted">CNPJ: <?= e(format_cnpj($c['cnpj'] ?? '')) ?></div>
                  <div class="small text-muted">E-mail: <?= e($c['email'] ?: '-') ?></div>
                  <div class="small text-muted">
                    Telefone: <?= e($telefoneFormatado ?: '-') ?>
                    <?= $bairro !== '' ? ' • Bairro: ' . e($bairro) : '' ?>
                  </div>
                </td>
                <td><?= e($c['cidade']) ?>/<?= e($c['uf']) ?></td>
                <td>
                  <span class="badge bg-<?= $statusClass ?>"><?= e($statusLabel) ?></span>
                </td>
                <td><?= e($c['responsavel_nome'] ?? '-') ?></td>
                <td><?= (int)($c['qtd_colaboradores'] ?? 0) ?></td>
                <td><?= e($c['criado_em']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-info" href="<?= e(app_url('clientes/ver.php?id=' . (int)$c['id'])) ?>">Ver</a>
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('clientes/editar.php?id=' . (int)$c['id'])) ?>">Editar</a>
                  <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('propostas/nova.php?cliente_id=' . (int)$c['id'])) ?>">Gerar Proposta</a>
                </td>
              </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <form method="GET" class="d-inline-flex align-items-center gap-2">
        <input type="hidden" name="nome" value="<?= e($nome) ?>">
        <input type="hidden" name="cidade" value="<?= e($cidade) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="responsavel" value="<?= e($responsavel) ?>">
        <label class="me-1">Por página:</label>
        <select name="pp" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ([10,15,25,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= $pp==$por_pagina?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <span class="ms-3 text-muted">Total: <?= $total ?></span>
    </div>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php
          $qs_base = $_GET;
          $qs_base['pp'] = $por_pagina;
          $renderLink = function($p, $label = null, $disabled = false, $active = false) use ($qs_base) {
              $qs = $qs_base;
              $qs['p'] = $p;
              $href = '?' . http_build_query($qs);
              $class = 'page-link';
              $li = 'page-item';
              if ($disabled) $li .= ' disabled';
              if ($active) $li .= ' active';
              $label = $label ?? $p;
              return '<li class="' . $li . '"><a class="' . $class . '" href="' . $href . '">' . $label . '</a></li>';
          };
        echo $renderLink(max(1, $pagina-1), '&laquo;', $pagina<=1);
        $ini = max(1, $pagina - 2);
        $fim = min($paginas, $pagina + 2);
        for ($p = $ini; $p <= $fim; $p++) {
            echo $renderLink($p, (string)$p, false, $p==$pagina);
        }
        echo $renderLink(min($paginas, $pagina+1), '&raquo;', $pagina>=$paginas);
        ?>
      </ul>
    </nav>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
