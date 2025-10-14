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
$config = run_query('SELECT * FROM configuracoes WHERE ativo=1 ORDER BY id DESC LIMIT 1')[0] ?? null;

log_user_action(current_user()['id'] ?? null, 'Gerou PDF da proposta', 'propostas', $id, null, $prop);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$codigoDisplay = $prop['codigo_proposta'] ?: '#' . $prop['id'];
$statusLabel = ucfirst((string)$prop['status']);
$dataEnvio = !empty($prop['data_envio']) ? date('d/m/Y H:i', strtotime((string)$prop['data_envio'])) : '-';
$validade = is_numeric($prop['validade_dias']) ? ((int)$prop['validade_dias'] . ' dias') : '-';
$criadoEm = !empty($prop['criado_em']) ? date('d/m/Y H:i', strtotime((string)$prop['criado_em'])) : date('d/m/Y');
$totalServicos = number_format((float)($prop['total_servicos'] ?? 0), 2, ',', '.');
$totalMateriais = number_format((float)($prop['total_materiais'] ?? 0), 2, ',', '.');
$totalGeral = number_format((float)($prop['total_geral'] ?? 0), 2, ',', '.');
$descricao = trim((string)($prop['descricao'] ?? ''));
$observacoes = trim((string)($prop['observacoes'] ?? ''));

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
h1,h2,h3,h4 { color: #0b3d60; margin-bottom: 6px; }
.header { text-align: center; border-bottom: 2px solid #0b3d60; padding-bottom: 10px; margin-bottom: 15px; }
.header img { max-height: 60px; margin-bottom: 5px; }
.section { margin-top: 18px; }
.table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.table th, .table td { border: 1px solid #cfcfcf; padding: 6px 8px; }
.table th { background: #f1f3f5; text-align: left; }
.totais td { font-weight: bold; }
.footer { text-align: center; font-size: 9px; margin-top: 25px; color: #666; }
.small { font-size: 10px; color: #555; }
</style>
</head>
<body>
<div class="header">
  <?php if (!empty($config['logotipo_url'])): ?>
    <img src="<?= h($config['logotipo_url']) ?>" alt="Logo">
  <?php endif; ?>
  <h2><?= h($config['empresa_nome'] ?? 'Inovare Soluções em Saúde') ?></h2>
  <div class="small">Proposta comercial <?= h($codigoDisplay) ?> | Status: <?= h($statusLabel) ?></div>
</div>

<div class="section">
  <h3>Informações do Cliente</h3>
  <table class="table">
    <tr><th>Nome fantasia</th><td><?= h($prop['nome_fantasia']) ?></td></tr>
    <tr><th>Razão social</th><td><?= h($prop['razao_social'] ?? '-') ?></td></tr>
    <tr><th>CNPJ</th><td><?= h($prop['cnpj'] ?? '-') ?></td></tr>
    <tr><th>E-mail</th><td><?= h($prop['email_cliente'] ?? '-') ?></td></tr>
  </table>
</div>

<div class="section">
  <h3>Resumo da Proposta</h3>
  <table class="table">
    <tr>
      <th>Responsável</th><td><?= h($prop['usuario_nome'] ?? '-') ?></td>
      <th>Data de envio</th><td><?= h($dataEnvio) ?></td>
    </tr>
    <tr>
      <th>Validade</th><td><?= h($validade) ?></td>
      <th>Emitida em</th><td><?= h($criadoEm) ?></td>
    </tr>
    <tr>
      <th>Pacote</th><td colspan="3"><?= h($prop['pacote_nome'] ?? 'Não vinculado') ?></td>
    </tr>
  </table>

  <table class="table totais">
    <tr>
      <td>Total em serviços: R$ <?= $totalServicos ?></td>
      <td>Total em materiais: R$ <?= $totalMateriais ?></td>
      <td>Valor total: R$ <?= $totalGeral ?></td>
    </tr>
  </table>
</div>

<?php if ($descricao !== ''): ?>
<div class="section">
  <h3>Descrição</h3>
  <p><?= nl2br(h($descricao)) ?></p>
</div>
<?php endif; ?>

<div class="section">
  <h3>Itens da Proposta</h3>
  <table class="table">
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th style="width:15%">Tipo</th>
        <th>Descrição</th>
        <th style="width:12%">Quantidade</th>
        <th style="width:15%">Valor unitário (R$)</th>
        <th style="width:15%">Valor total (R$)</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$itens): ?>
      <tr><td colspan="6" style="text-align:center; padding:12px;">Nenhum item cadastrado.</td></tr>
    <?php else: ?>
      <?php foreach ($itens as $idx => $item): ?>
        <tr>
          <td><?= $idx + 1 ?></td>
          <td><?= h(ucfirst($item['tipo_item'] ?? 'serviço')) ?></td>
          <td><?= h($item['descricao_item'] ?? '') ?></td>
          <td style="text-align:right;"><?= number_format((float)($item['quantidade'] ?? 0), 2, ',', '.') ?></td>
          <td style="text-align:right;"><?= number_format((float)($item['valor_unitario'] ?? 0), 2, ',', '.') ?></td>
          <td style="text-align:right; font-weight:600;"><?= number_format((float)($item['valor_total'] ?? 0), 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($observacoes !== ''): ?>
<div class="section">
  <h3>Observações</h3>
  <p><?= nl2br(h($observacoes)) ?></p>
</div>
<?php endif; ?>

<div class="section">
  <p><strong>Responsável comercial:</strong> <?= h($prop['usuario_nome'] ?? '-') ?><br>
     <strong>Data de emissão:</strong> <?= h(date('d/m/Y')) ?></p>
</div>

<div class="footer">
  <?php if (!empty($config['endereco'])): ?><?= h($config['endereco']) ?><br><?php endif; ?>
  <?php if (!empty($config['telefone'])): ?>Telefone: <?= h($config['telefone']) ?><br><?php endif; ?>
  <?php if (!empty($config['email_contato'])): ?>E-mail: <?= h($config['email_contato']) ?><br><?php endif; ?>
  <?php if (!empty($config['instagram'])): ?>Instagram: <?= h($config['instagram']) ?><br><?php endif; ?>
  <?= h($config['rodape'] ?? 'Inovare Soluções em Saúde') ?>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pdf->stream('Proposta_' . $prop['id'] . '.pdf', ['Attachment' => false]);
exit;
