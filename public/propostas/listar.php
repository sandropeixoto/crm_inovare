<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$cliente = trim($_GET['cliente'] ?? '');
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

$usuarios = run_query("SELECT id,nome FROM usuarios WHERE ativo=1 ORDER BY nome ASC");

log_user_action(current_user()['id'] ?? null, 'Listou propostas', 'propostas', null, $_GET, ['total'=>$total]);

$page_title = 'Propostas';
$breadcrumb = 'Comercial > Propostas';

ob_start();
?>
<div class="card mb-3 shadow-sm">
  <div class="card-body">
    <form class="row g-3" method="GET">
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
        <label class="form-label">Usuário</label>
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

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Cliente</th>
            <th>Pacote</th>
            <th>Vidas</th>
            <th>Total (R$)</th>
            <th>Status</th>
            <th>Responsável</th>
            <th>Data</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$propostas): ?>
            <tr><td colspan="9" class="text-center py-3">Nenhuma proposta encontrada.</td></tr>
          <?php else: foreach($propostas as $p): ?>
            <?php
              $badge = match ($p['status']) {
                'aceita' => 'success',
                'enviada' => 'primary',
                'rejeitada' => 'danger',
                'expirada' => 'secondary',
                default => 'warning text-dark'
              };
            ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td><?= e($p['nome_fantasia']) ?></td>
              <td><?= e($p['pacote']) ?></td>
              <td><?= (int)$p['qtd_colaboradores'] ?></td>
              <td><?= number_format((float)$p['total_geral'], 2, ',', '.') ?></td>
              <td><span class="badge bg-<?= $badge ?>"><?= e(ucfirst($p['status'])) ?></span></td>
              <td><?= e($p['usuario_nome'] ?? '-') ?></td>
              <td><?= e($p['criado_em']) ?></td>
              <td class="text-end">
                <a href="<?= e(app_url('propostas/ver.php?id=' . (int)$p['id'])) ?>" class="btn btn-sm btn-outline-primary">Ver</a>
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
