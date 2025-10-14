<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    abort(400, 'ID de cliente inválido.');
}

$cliente = run_query("SELECT c.*, u.nome AS responsavel_nome FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_comercial WHERE c.id = ?", [$id])[0] ?? null;
if (!$cliente) {
    abort(404, 'Cliente não encontrado.');
}

log_user_action(current_user()['id'] ?? null, 'Visualizou cliente', 'clientes', $id, null, $cliente);

$propostas = run_query("SELECT p.id, p.codigo_proposta, p.descricao, p.status, p.data_envio, p.total_geral, pa.nome AS pacote FROM propostas p LEFT JOIN pacotes pa ON pa.id=p.id_pacote WHERE p.id_cliente=? ORDER BY p.id DESC LIMIT 5", [$id]);
$interacoes = run_query("SELECT i.*, u.nome AS usuario FROM interacoes i LEFT JOIN usuarios u ON u.id=i.id_usuario WHERE i.id_cliente=? ORDER BY i.id DESC LIMIT 5", [$id]);

$page_title = 'Cliente: ' . $cliente['nome_fantasia'];
$breadcrumb = 'Clientes > Visualização';

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-1">Informações do Cliente</h5>
    <span class="badge bg-secondary">ID <?= (int)$cliente['id'] ?></span>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(app_url('clientes/editar.php?id=' . (int)$cliente['id'])) ?>" class="btn btn-primary btn-sm">Editar</a>
    <a href="<?= e(app_url('clientes/listar.php')) ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <strong>Razão Social:</strong><br><?= e($cliente['razao_social']) ?>
      </div>
      <div class="col-md-3">
        <strong>CNPJ:</strong><br><?= e($cliente['cnpj']) ?>
      </div>
      <div class="col-md-3">
        <strong>Status:</strong><br>
        <?php
          $statusClass = match ($cliente['status']) {
            'ativo' => 'success',
            'prospecto' => 'warning text-dark',
            default => 'secondary'
          };
        ?>
        <span class="badge bg-<?= $statusClass ?>"><?= e(ucfirst($cliente['status'])) ?></span>
      </div>
      <div class="col-md-2">
        <strong>Origem:</strong><br><?= e($cliente['origem']) ?>
      </div>
      <div class="col-md-4">
        <strong>Email:</strong><br><?= e($cliente['email']) ?>
      </div>
      <div class="col-md-4">
        <strong>Telefone:</strong><br><?= e($cliente['telefone']) ?>
      </div>
      <div class="col-md-4">
        <strong>Responsável:</strong><br><?= e($cliente['responsavel_nome']) ?>
      </div>
      <div class="col-md-4">
        <strong>Cidade:</strong><br><?= e($cliente['cidade']) ?> / <?= e($cliente['uf']) ?>
      </div>
      <div class="col-md-4">
        <strong>Cadastrado em:</strong><br><?= e($cliente['criado_em']) ?>
      </div>
      <div class="col-md-4">
        <strong>Atualizado em:</strong><br><?= e($cliente['atualizado_em'] ?? '-') ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100 shadow-sm">
      <div class="card-header bg-white fw-semibold">Últimas Propostas</div>
      <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Código</th>
                <th>Status</th>
                <th>Total (R$)</th>
                <th>Envio</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$propostas): ?>
                <tr><td colspan="5" class="text-center py-3">Nenhuma proposta registrada.</td></tr>
              <?php else: ?>
                <?php foreach ($propostas as $p): ?>
                  <?php
                    $badge = match ($p['status']) {
                      'aceita' => 'success',
                      'enviada' => 'primary',
                      'rejeitada' => 'danger',
                      default => 'secondary'
                    };
                    $codigo = $p['codigo_proposta'] ?: '#' . $p['id'];
                    $dataEnvio = !empty($p['data_envio']) ? date('d/m/Y', strtotime((string)$p['data_envio'])) : '-';
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= e($codigo) ?></div>
                      <?php if (!empty($p['pacote'])): ?>
                        <div class="text-muted small"><?= e($p['pacote']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $badge ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                    <td><?= number_format((float)($p['total_geral'] ?? 0), 2, ',', '.') ?></td>
                    <td><?= e($dataEnvio) ?></td>
                    <td class="text-end">
                      <a href="<?= e(app_url('propostas/ver.php?id=' . (int)$p['id'])) ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer text-end">
          <a href="<?= e(app_url('propostas/listar.php?cliente_id=' . (int)$cliente['id'] . '&cliente=' . urlencode($cliente['nome_fantasia']))) ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
        </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100 shadow-sm">
      <div class="card-header bg-white fw-semibold">Últimas Interações</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr><th>Tipo</th><th>Descrição</th><th>Usuário</th><th>Data</th></tr>
          </thead>
          <tbody>
            <?php if (!$interacoes): ?>
              <tr><td colspan="4" class="text-center py-3">Nenhuma interação registrada.</td></tr>
            <?php else: ?>
              <?php foreach ($interacoes as $i): ?>
                <tr>
                  <td><?= e(ucfirst($i['tipo'])) ?></td>
                  <td><?= e($i['descricao']) ?></td>
                  <td><?= e($i['usuario']) ?></td>
                  <td><?= e($i['criado_em']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer text-end">
        <a href="<?= e(app_url('interacoes/cliente.php?id=' . (int)$cliente['id'])) ?>" class="btn btn-sm btn-outline-primary">Registrar/Ver todas</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
