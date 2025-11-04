<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    abort(400, 'ID de proposta inválido.');
}

$sql = "SELECT p.*, c.nome_fantasia, c.razao_social, c.cnpj, c.email AS email_cliente,
               pa.nome AS pacote_nome, pa.descricao AS pacote_desc,
               u.nome AS usuario_nome
        FROM propostas p
        JOIN clientes c ON c.id = p.id_cliente
        LEFT JOIN pacotes pa ON pa.id = p.id_pacote
        LEFT JOIN usuarios u ON u.id = p.id_usuario
        WHERE p.id = ?";
$prop = run_query($sql, [$id])[0] ?? null;

if (!$prop) {
    abort(404, 'Proposta não encontrada.');
}

$itens = run_query('SELECT * FROM proposta_itens WHERE id_proposta = ? ORDER BY id ASC', [$id]);

log_user_action(current_user()['id'] ?? null, 'Visualizou proposta', 'propostas', $id, null, $prop);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$codigoDisplay = $prop['codigo_proposta'] ?: '#' . $prop['id'];
$badgeClass = match ($prop['status']) {
    'aceita' => 'success',
    'enviada' => 'primary',
    'rejeitada' => 'danger',
    'expirada' => 'secondary',
    default => 'warning text-dark',
};
$dataEnvio = !empty($prop['data_envio']) ? date('d/m/Y H:i', strtotime((string)$prop['data_envio'])) : '-';
$validade = is_numeric($prop['validade_dias']) ? ((int)$prop['validade_dias'] . ' dias') : '-';
$criadoEm = !empty($prop['criado_em']) ? date('d/m/Y H:i', strtotime((string)$prop['criado_em'])) : '-';
$atualizadoEm = !empty($prop['atualizado_em']) ? date('d/m/Y H:i', strtotime((string)$prop['atualizado_em'])) : '-';
$totalServicos = number_format((float)($prop['total_servicos'] ?? 0), 2, ',', '.');
$totalMateriais = number_format((float)($prop['total_materiais'] ?? 0), 2, ',', '.');
$totalGeral = number_format((float)($prop['total_geral'] ?? 0), 2, ',', '.');
$numeroColaboradores = isset($prop['numero_colaboradores']) && $prop['numero_colaboradores'] !== null ? (int)$prop['numero_colaboradores'] : null;
$sinistralidadeTexto = isset($prop['sinistralidade_percentual']) ? number_format((float)$prop['sinistralidade_percentual'], 2, ',', '.') . '%' : '-';
$franquiaTexto = isset($prop['franquia_percentual']) ? number_format((float)$prop['franquia_percentual'], 2, ',', '.') . '%' : '-';
$valorImplantacaoTexto = isset($prop['valor_implantacao']) ? 'R$ ' . number_format((float)$prop['valor_implantacao'], 2, ',', '.') : '-';
$valorMensalTexto = isset($prop['valor_mensal']) ? 'R$ ' . number_format((float)$prop['valor_mensal'], 2, ',', '.') : '-';
$descricao = trim((string)($prop['descricao'] ?? ''));
$observacoes = trim((string)($prop['observacoes'] ?? ''));

?><!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Proposta <?= h($codigoDisplay) ?> - <?= h($prop['nome_fantasia']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.badge-status { font-size: 0.85rem; }
.totais-card .value { font-size: 1.25rem; font-weight: 600; }
</style>
</head>
<body>
<div class="container mt-4 mb-5">
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
      <h4 class="mb-1">Proposta <?= h($codigoDisplay) ?></h4>
      <div class="text-muted">Cliente: <?= h($prop['nome_fantasia']) ?></div>
    </div>
    <div class="btn-group" role="group">
      <a href="listar.php" class="btn btn-outline-secondary">Voltar</a>
      <a href="editar.php?id=<?= (int)$prop['id'] ?>" class="btn btn-primary">Editar</a>
      <a href="gerar_pdf.php?id=<?= (int)$prop['id'] ?>" class="btn btn-outline-danger">Gerar PDF</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="fw-bold text-primary">Status da Proposta</h6>
          <p class="mb-2">
            <span class="badge badge-status bg-<?= $badgeClass ?>"><?= h(ucfirst((string)$prop['status'])) ?></span>
          </p>
          <ul class="list-unstyled small mb-0">
            <li><strong>Responsável:</strong> <?= h($prop['usuario_nome'] ?? '-') ?></li>
            <li><strong>Pacote:</strong> <?= h($prop['pacote_nome'] ?? 'Não vinculado') ?></li>
            <li><strong>Data de envio:</strong> <?= h($dataEnvio) ?></li>
            <li><strong>Validade:</strong> <?= h($validade) ?></li>
            <li><strong>Criada em:</strong> <?= h($criadoEm) ?></li>
            <li><strong>Última atualização:</strong> <?= h($atualizadoEm) ?></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="fw-bold text-primary mb-3">Dados do Cliente</h6>
          <div class="row g-3 small">
            <div class="col-md-6">
              <strong>Razão social:</strong><br><?= h($prop['razao_social'] ?? '-') ?>
            </div>
            <div class="col-md-6">
              <strong>CNPJ:</strong><br><?= h($prop['cnpj'] ?? '-') ?>
            </div>
            <div class="col-md-6">
              <strong>E-mail:</strong><br><?= h($prop['email_cliente'] ?? '-') ?>
            </div>
            <div class="col-md-6">
              <strong>Contato responsável:</strong><br><?= h($prop['usuario_nome'] ?? '-') ?>
            </div>
          </div>
          <?php if (!empty($prop['pacote_desc'])): ?>
            <hr>
            <div class="small">
              <strong>Resumo do pacote:</strong>
              <p class="mb-0"><?= nl2br(h((string)$prop['pacote_desc'])) ?></p>
            </div>
          <?php endif; ?>
          <hr>
          <div class="row g-3 small">
            <div class="col-md-3">
              <strong>N� de colaboradores:</strong><br><?= $numeroColaboradores !== null ? h((string)$numeroColaboradores) : '-' ?>
            </div>
            <div class="col-md-3">
              <strong>Sinistralidade:</strong><br><?= h($sinistralidadeTexto) ?>
            </div>
            <div class="col-md-3">
              <strong>Franquia:</strong><br><?= h($franquiaTexto) ?>
            </div>
            <div class="col-md-3">
              <strong>Valores base:</strong><br><?= h($valorImplantacaoTexto) ?> / <?= h($valorMensalTexto) ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm totais-card h-100">
        <div class="card-body">
          <div class="text-muted small">Total em serviços</div>
          <div class="value text-primary">R$ <?= $totalServicos ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm totais-card h-100">
        <div class="card-body">
          <div class="text-muted small">Total em materiais</div>
          <div class="value text-primary">R$ <?= $totalMateriais ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm totais-card h-100 border-primary">
        <div class="card-body">
          <div class="text-muted small">Valor total da proposta</div>
          <div class="value text-success">R$ <?= $totalGeral ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($descricao !== ''): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Descrição da proposta</div>
    <div class="card-body">
      <p class="mb-0"><?= nl2br(h($descricao)) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Itens da proposta</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Tipo</th>
              <th>Descrição</th>
              <th class="text-end">Quantidade</th>
              <th class="text-end">Valor unitário (R$)</th>
              <th class="text-end">Valor total (R$)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$itens): ?>
              <tr><td colspan="6" class="text-center py-3">Nenhum item cadastrado.</td></tr>
            <?php else: ?>
              <?php foreach ($itens as $idx => $item): ?>
                <tr>
                  <td><?= $idx + 1 ?></td>
                  <td><?= h(ucfirst($item['tipo_item'] ?? 'serviço')) ?></td>
                  <td><?= h($item['descricao_item'] ?? '') ?></td>
                  <td class="text-end"><?= number_format((float)($item['quantidade'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end"><?= number_format((float)($item['valor_unitario'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end fw-semibold"><?= number_format((float)($item['valor_total'] ?? 0), 2, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($observacoes !== ''): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Observações</div>
    <div class="card-body">
      <p class="mb-0"><?= nl2br(h($observacoes)) ?></p>
    </div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
