<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID de proposta inválido.');
}

// Carregar proposta e cliente
$sql = "SELECT p.*, c.nome_fantasia, c.razao_social, c.cnpj, c.email AS email_cliente,
               pa.nome AS pacote_nome, pa.descricao AS pacote_desc, pa.conformidade,
               u.nome AS usuario_nome
        FROM propostas p
        JOIN clientes c ON c.id=p.id_cliente
        LEFT JOIN pacotes pa ON pa.id=p.id_pacote
        LEFT JOIN usuarios u ON u.id=p.id_usuario
        WHERE p.id=?";
$prop = run_query($sql, [$id])[0] ?? null;
if (!$prop) exit('Proposta não encontrada.');

// Carregar dados da configuração ativa
$config = run_query("SELECT * FROM configuracoes WHERE ativo=1 ORDER BY id DESC LIMIT 1")[0] ?? null;

log_user_action($_SESSION['user']['id'], 'Gerou PDF da proposta', 'propostas', $id, null, $prop);

// HTML do PDF
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#333; }
h1,h2,h3,h4 { color:#002b5c; margin-bottom:5px; }
.header { text-align:center; border-bottom:2px solid #002b5c; padding-bottom:10px; }
.header img { max-height:60px; }
.section { margin-top:20px; }
.table { width:100%; border-collapse:collapse; margin-top:10px; }
.table th, .table td { border:1px solid #ccc; padding:6px 8px; }
.table th { background:#f2f2f2; }
.footer { text-align:center; font-size:10px; margin-top:30px; color:#666; }
</style>
</head>
<body>
<div class="header">
  <?php if(!empty($config['logotipo_url'])): ?>
    <img src="<?= htmlspecialchars($config['logotipo_url']) ?>" alt="Logo">
  <?php endif; ?>
  <h2><?= htmlspecialchars($config['empresa_nome'] ?? 'Empresa') ?></h2>
</div>

<div class="section">
  <h3>Proposta Comercial - NR-01</h3>
  <p><strong>Número:</strong> #<?= (int)$prop['id'] ?>  
     <strong>Data:</strong> <?= date('d/m/Y', strtotime($prop['criado_at'] ?? 'now')) ?></p>
</div>

<div class="section">
  <h4>Cliente</h4>
  <table class="table">
    <tr><th>Nome Fantasia</th><td><?= htmlspecialchars($prop['nome_fantasia']) ?></td></tr>
    <tr><th>Razão Social</th><td><?= htmlspecialchars($prop['razao_social']) ?></td></tr>
    <tr><th>CNPJ</th><td><?= htmlspecialchars($prop['cnpj']) ?></td></tr>
    <tr><th>E-mail</th><td><?= htmlspecialchars($prop['email_cliente']) ?></td></tr>
  </table>
</div>

<div class="section">
  <h4>Informações do Pacote</h4>
  <table class="table">
    <tr><th>Pacote</th><td><?= htmlspecialchars($prop['pacote_nome']) ?></td></tr>
    <tr><th>Descrição</th><td><?= htmlspecialchars($prop['pacote_desc']) ?></td></tr>
    <tr><th>Conformidade</th><td><?= htmlspecialchars($prop['conformidade']) ?></td></tr>
    <tr><th>Quantidade de Vidas</th><td><?= (int)$prop['qtd_colaboradores'] ?></td></tr>
  </table>
</div>

<div class="section">
  <h4>Investimento</h4>
  <table class="table">
    <tr><th>Implantação</th><td>R$ <?= number_format($prop['valor_implantacao'],2,',','.') ?></td></tr>
    <tr><th>Mensal</th><td>R$ <?= number_format($prop['valor_mensal'],2,',','.') ?></td></tr>
    <tr><th>Total</th><td><strong>R$ <?= number_format($prop['total_geral'],2,',','.') ?></strong></td></tr>
  </table>
</div>

<div class="section">
  <h4>Observações</h4>
  <p>
    Esta proposta contempla a implementação do Programa Integrado de Saúde Ocupacional,
    Emocional e de Conformidade com a NR-01, garantindo:
  </p>
  <ul>
    <li>Conformidade legal com a NR-01;</li>
    <li>Prevenção de riscos psicossociais e suporte especializado;</li>
    <li>Cuidado integral e educação continuada em saúde;</li>
    <li>Suporte técnico ao RH e SESMT na gestão de riscos.</li>
  </ul>
</div>

<div class="section">
  <p><strong>Emitido por:</strong> <?= htmlspecialchars($prop['usuario_nome']) ?><br>
     <strong>Data:</strong> <?= date('d/m/Y') ?></p>
</div>

<div class="footer">
  <?php if(!empty($config['endereco'])): ?><?= htmlspecialchars($config['endereco']) ?><br><?php endif; ?>
  <?php if(!empty($config['email_contato'])): ?>📧 <?= htmlspecialchars($config['email_contato']) ?><br><?php endif; ?>
  <?php if(!empty($config['telefone'])): ?>📱 <?= htmlspecialchars($config['telefone']) ?><br><?php endif; ?>
  <?php if(!empty($config['instagram'])): ?>Instagram: <?= htmlspecialchars($config['instagram']) ?><br><?php endif; ?>
  <?= htmlspecialchars($config['rodape']) ?>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

// === GERAÇÃO DO PDF ===
require_once __DIR__ . '/../../vendor/autoload.php'; // dompdf precisa estar instalado

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pdf->stream("Proposta_{$prop['id']}.pdf", ["Attachment" => false]);
exit;
