<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$cliente = trim($_GET['cliente'] ?? '');
$clienteId = (int)($_GET['cliente_id'] ?? 0);
$status  = $_GET['status'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

$por_pagina = max(10, (int)($_GET['pp'] ?? 15));
$pagina = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pagina - 1) * $por_pagina;

$where = [];
$params = [];

if ($cliente !== '') {
    $where[] = "c.nome_fantasia LIKE ?";
    $params[] = "%{$cliente}%";
}
if ($clienteId > 0) {
    $where[] = "p.id_cliente = ?";
    $params[] = $clienteId;
}
if ($status !== '' && in_array($status, ['rascunho','enviada','aceita','rejeitada','expirada'], true)) {
    $where[] = "p.status = ?";
    $params[] = $status;
}
if ($usuario !== '' && ctype_digit($usuario)) {
    $where[] = "p.id_usuario = ?";
    $params[] = (int)$usuario;
}
if ($data_ini !== '' && $data_fim !== '') {
    $where[] = "DATE(p.criado_em) BETWEEN ? AND ?";
    $params[] = $data_ini;
    $params[] = $data_fim;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sqlCount = "SELECT COUNT(*) AS total
             FROM propostas p
             JOIN clientes c ON c.id=p.id_cliente
             LEFT JOIN usuarios u ON u.id=p.id_usuario
             LEFT JOIN pacotes pa ON pa.id=p.id_pacote
             $whereSql";
$total = (int)(run_query($sqlCount, $params)[0]['total'] ?? 0);
$paginas = max(1, (int)ceil($total / $por_pagina));

$sql = "SELECT p.*, c.nome_fantasia, pa.nome AS pacote, u.nome AS usuario_nome
        FROM propostas p
        JOIN clientes c ON c.id=p.id_cliente
        LEFT JOIN usuarios u ON u.id=p.id_usuario
        LEFT JOIN pacotes pa ON pa.id=p.id_pacote
        $whereSql
        ORDER BY p.id DESC
        LIMIT $por_pagina OFFSET $offset";
$propostas = run_query($sql, $params);

$usuarios = run_query("SELECT id,nome FROM usuarios WHERE ativo=TRUE ORDER BY nome ASC");

log_user_action(current_user()['id'] ?? null, 'Listou propostas', 'propostas', null, $_GET, ['total'=>$total]);

$page_title = 'Propostas';
$breadcrumb = 'Comercial > Propostas';

ob_start();
?>
<div class="card mb-3 shadow-sm">
  <div class="card-body">
    <form class="row g-3" method="GET">
      <?php if($clienteId > 0): ?>
        <input type="hidden" name="cliente_id" value="<?= $clienteId ?>">
      <?php endif; ?>
      <div class="col-md-3">
        <label class="form-label">Cliente</label>
        <input type="text" name="cliente" class="form-control" value="<?= e($cliente) ?>" placeholder="Nome fantasia">
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">Todos</option>
          <?php foreach(['rascunho','enviada','aceita','rejeitada','expirada'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Usu&aacute;rio</label>
        <select name="usuario" class="form-select">
          <option value="">Todos</option>
          <?php foreach($usuarios as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $usuario === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Data Inicial</label>
        <input type="date" name="data_ini" value="<?= e($data_ini) ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Data Final</label>
        <input type="date" name="data_fim" value="<?= e($data_fim) ?>" class="form-control">
      </div>
      <div class="col-12 text-end">
        <button class="btn btn-primary" type="submit">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-semibold mb-0">Propostas cadastradas</h5>
  <a href="<?= e(app_url('propostas/nova.php')) ?>" class="btn btn-success btn-sm">+ Nova Proposta</a>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>C&oacute;digo</th>
            <th>Cliente / Descri&ccedil;&atilde;o</th>
            <th>Status</th>
            <th>Envio</th>
            <th>Validade</th>
            <th>Servi&ccedil;os (R$)</th>
            <th>Materiais (R$)</th>
            <th>Total (R$)</th>
            <th>Respons&aacute;vel</th>
            <th class="text-end">A&ccedil;&otilde;es</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$propostas): ?>
            <tr><td colspan="10" class="text-center py-3">Nenhuma proposta encontrada.</td></tr>
          <?php else: foreach($propostas as $p): ?>
            <?php
              $badge = match ($p['status']) {
                'aceita' => 'success',
                'enviada' => 'primary',
                'rejeitada' => 'danger',
                'expirada' => 'secondary',
                default => 'warning text-dark'
              };
              $desc = trim((string)($p['Descri&ccedil;&atilde;o'] ?? ''));
              if ($desc !== '') {
                  if (function_exists('mb_strimwidth')) {
                      $desc = mb_strimwidth($desc, 0, 80, '...');
                  } else {
                      $desc = strlen($desc) > 80 ? substr($desc, 0, 77) . '...' : $desc;
                  }
              }
              $dataEnvio = !empty($p['data_envio']) ? date('d/m/Y H:i', strtotime($p['data_envio'])) : '-';
              $validade = isset($p['validade_dias']) && $p['validade_dias'] !== null ? ((int)$p['validade_dias'] . ' dias') : '-';
              $codigo = $p['codigo_proposta'] ?? null;
            ?>
            <tr>
              <td>
                <div class="fw-semibold">#<?= (int)$p['id'] ?></div>
                <div class="text-muted small"><?= e($codigo ?: '-') ?></div>
              </td>
              <td>
                <div class="fw-semibold"><?= e($p['nome_fantasia']) ?></div>
                <?php if ($desc !== ''): ?>
                  <div class="text-muted small"><?= e($desc) ?></div>
                <?php endif; ?>
                <?php if (!empty($p['pacote'])): ?>
                  <div class="mt-1"><span class="badge bg-light text-dark border">Pacote: <?= e($p['pacote']) ?></span></div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-<?= $badge ?>"><?= e(ucfirst($p['status'])) ?></span></td>
              <td><?= e($dataEnvio) ?></td>
              <td><?= e($validade) ?></td>
              <td><?= number_format((float)($p['total_servicos'] ?? 0), 2, ',', '.') ?></td>
              <td><?= number_format((float)($p['total_Materiais'] ?? 0), 2, ',', '.') ?></td>
              <td class="fw-semibold"><?= number_format((float)($p['total_geral'] ?? 0), 2, ',', '.') ?></td>
              <td><?= e($p['usuario_nome'] ?? '-') ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                  <a href="<?= e(app_url('propostas/ver.php?id=' . (int)$p['id'])) ?>" class="btn btn-outline-primary">Ver</a>
                  <a href="<?= e(app_url('propostas/editar.php?id=' . (int)$p['id'])) ?>" class="btn btn-outline-secondary">Editar</a>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>Total: <?= $total ?> propostas</span>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php
          $qs = $_GET;
          $qs['pp'] = $por_pagina;
          $renderLink = function($p,$lbl=null,$disabled=false,$active=false) use($qs){
              $qs['p']=$p;
              $href='?'.http_build_query($qs);
              $li='page-item'; if($disabled)$li.=' disabled'; if($active)$li.=' active';
              return "<li class='$li'><a class='page-link' href='$href'>".($lbl??$p)."</a></li>";
          };
          echo $renderLink(max(1,$pagina-1),'&laquo;',$pagina<=1);
          $ini=max(1,$pagina-2); $fim=min($paginas,$pagina+2);
          for($p=$ini;$p<=$fim;$p++){ echo $renderLink($p,(string)$p,false,$p==$pagina); }
          echo $renderLink(min($paginas,$pagina+1),'&raquo;',$pagina>=$paginas);
        ?>
      </ul>
    </nav>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';






