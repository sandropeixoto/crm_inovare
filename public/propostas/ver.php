<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID de proposta inválido.');
}

// busca proposta
$sql = "SELECT p.*, c.nome_fantasia, c.razao_social, c.cnpj, c.email AS email_cliente,
               pa.nome AS pacote_nome, pa.descricao AS pacote_desc, pa.conformidade,
               u.nome AS usuario_nome
        FROM propostas p
        JOIN clientes c ON c.id=p.id_cliente
        LEFT JOIN pacotes pa ON pa.id=p.id_pacote
        LEFT JOIN usuarios u ON u.id=p.id_usuario
        WHERE p.id=?";
$prop = run_query($sql, [$id])[0] ?? null;

if (!$prop) {
    http_response_code(404);
    exit('Proposta não encontrada.');
}

log_user_action($_SESSION['user']['id'], 'Visualizou proposta', 'propostas', $id, null, $prop);

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Proposta #<?= (int)$prop['id'] ?> - <?= h($prop['nome_fantasia']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {background-color:#f8f9fa;}
.card-header h5 {margin:0;}
.tabela th {background:#e9ecef;}
</style>
</head>
<body>
<div class="container mt-4 mb-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Proposta #<?= (int)$prop['id'] ?></h4>
    <div>
      <a href="listar.php" class="btn btn-secondary">Voltar</a>
      <a href="gerar_pdf.php?id=<?= $prop['id'] ?>" class="btn btn-outline-danger">Gerar PDF</a>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white">
      <h5>Dados do Cliente</h5>
    </div>
    <div class="card-body">
      <div class="row mb-2">
        <div class="col-md-6"><strong>Nome Fantasia:</strong> <?= h($prop['nome_fantasia']) ?></div>
        <div class="col-md-6"><strong>Razão Social:</strong> <?= h($prop['razao_social']) ?></div>
      </div>
      <div class="row mb-2">
        <div class="col-md-4"><strong>CNPJ:</strong> <?= h($prop['cnpj']) ?></div>
        <div class="col-md-4"><strong>E-mail:</strong> <?= h($prop['email_cliente']) ?></div>
        <div class="col-md-4"><strong>Responsável:</strong> <?= h($prop['usuario_nome']) ?></div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white">
      <h5>Informações da Proposta</h5>
    </div>
    <div class="card-body">
      <table class="table tabela table-bordered">
        <tr><th>Pacote</th><td><?= h($prop['pacote_nome']) ?></td></tr>
        <tr><th>Descrição</th><td><?= h($prop['pacote_desc']) ?></td></tr>
        <tr><th>Conformidade</th><td><?= h($prop['conformidade']) ?></td></tr>
        <tr><th>Quantidade de Vidas</th><td><?= (int)$prop['qtd_colaboradores'] ?></td></tr>
        <tr><th>Implantação</th><td>R$ <?= number_format($prop['valor_implantacao'],2,',','.') ?></td></tr>
        <tr><th>Mensalidade</th><td>R$ <?= number_format($prop['valor_mensal'],2,',','.') ?></td></tr>
        <tr><th>Total Geral</th><td class="fw-bold">R$ <?= number_format($prop['total_geral'],2,',','.') ?></td></tr>
        <tr><th>Status</th><td>
          <span class="badge bg-<?= $prop['status']==='aceita'?'success':($prop['status']==='enviada'?'primary':($prop['status']==='rejeitada'?'danger':'secondary')) ?>">
            <?= ucfirst($prop['status']) ?>
          </span>
        </td></tr>
      </table>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white">
      <h5>Observações</h5>
    </div>
    <div class="card-body">
      <p>
        Esta proposta integra o Programa de Saúde Ocupacional, Emocional e de Conformidade com a NR-01,
        desenvolvido pela <strong>Inovare Soluções em Saúde</strong>, garantindo:
      </p>
      <ul>
        <li>Conformidade legal com a NR-01;</li>
        <li>Prevenção de riscos psicossociais e suporte especializado;</li>
        <li>Cuidado integral e educação continuada em saúde ocupacional;</li>
        <li>Suporte técnico ao RH e SESMT na gestão de riscos.</li>
      </ul>
      <p>Emitido em <strong><?= date('d/m/Y') ?></strong>.</p>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body text-center text-muted small">
      Inovare Soluções em Saúde<br>
      Tv. Humaitá, 1733 – 1º andar, Sala 02 – Pedreira – Belém/PA<br>
      📧 diretoria@inovaress.com | 📱 Instagram: @inovaresolucoesemsaude
    </div>
  </div>
</div>
</body>
</html>
