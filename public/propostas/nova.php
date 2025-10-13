<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor','comercial']);

$id_cliente = (int)($_GET['cliente_id'] ?? 0);
$cliente = run_query("SELECT id, nome_fantasia FROM clientes WHERE id=?", [$id_cliente])[0] ?? null;

if (!$cliente) {
    http_response_code(404);
    exit('Cliente não encontrado.');
}

// busca pacotes ativos
$pacotes = run_query("SELECT id, nome, tipo_calculo FROM pacotes WHERE ativo=1 ORDER BY id ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pacote = (int)$_POST['id_pacote'];
    $vidas = max(1, (int)$_POST['qtd_colaboradores']);
    $pacote = run_query("SELECT * FROM pacotes WHERE id=?", [$id_pacote])[0] ?? null;
    if (!$pacote) exit('Pacote inválido.');

    // cálculos automáticos
    $valor_implantacao = 0; $valor_mensal = 0; $valor_por_vida = 0; $total_geral = 0;
    switch ($pacote['id']) {
        case 1: // Pacote 1 fixo até 50 vidas
            $valor_implantacao = ($vidas <= 50) ? 9100.00 : round(($vidas/50)*9100,2);
            $valor_mensal = 0;
            $total_geral = $valor_implantacao;
            break;
        case 2: // Pacote 2 - sinistralidade
            $sinistralidade = ($vidas < 300) ? 0.10 : 0.05;
            $valor_por_vida = 32.50;
            $valor_implantacao = 4200.00;
            $valor_mensal = $valor_por_vida * $vidas;
            $total_geral = $valor_implantacao + $valor_mensal;
            break;
        case 3: // Pacote 3 - franquia
            $sinistralidade = ($vidas < 300) ? 0.10 : 0.05;
            $valor_por_vida = 50.00;
            $valor_implantacao = 4200.00;
            $valor_mensal = $valor_por_vida * $vidas;
            $total_geral = $valor_implantacao + $valor_mensal;
            break;
    }

    // grava proposta
    run_query("INSERT INTO propostas 
        (id_cliente,id_pacote,id_usuario,qtd_colaboradores,valor_implantacao,valor_mensal,valor_por_vida,total_geral,sinistralidade,franquia,status)
        VALUES (?,?,?,?,?,?,?,?,?, ?, 'rascunho')",
        [
            $cliente['id'], $id_pacote, $_SESSION['user']['id'],
            $vidas, $valor_implantacao, $valor_mensal, $valor_por_vida,
            $total_geral, $sinistralidade ?? null, $sinistralidade ?? null
        ]
    );
    $id_prop = pdo()->lastInsertId();

    log_user_action($_SESSION['user']['id'], 'Criou proposta', 'propostas', $id_prop, null, [
        'cliente'=>$cliente['nome_fantasia'],'pacote'=>$pacote['nome'],'vidas'=>$vidas,'total'=>$total_geral
    ]);

    $sucesso = "Proposta nº {$id_prop} criada com sucesso!";
}

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Nova Proposta - CRM Inovare</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h4>Nova Proposta - <?= h($cliente['nome_fantasia']) ?></h4>
  <?php if(!empty($sucesso)): ?>
    <div class="alert alert-success"><?= h($sucesso) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="POST" class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Pacote</label>
          <select name="id_pacote" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach($pacotes as $p): ?>
              <option value="<?= $p['id'] ?>"><?= h($p['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Nº de Colaboradores (vidas)</label>
          <input type="number" name="qtd_colaboradores" class="form-control" min="1" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Gerar Proposta</button>
        </div>
      </form>
    </div>
  </div>

  <?php if(!empty($sucesso)): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Resumo da Proposta</div>
    <div class="card-body">
      <table class="table table-sm">
        <tr><th>Cliente</th><td><?= h($cliente['nome_fantasia']) ?></td></tr>
        <tr><th>Pacote</th><td><?= h($pacote['nome'] ?? '-') ?></td></tr>
        <tr><th>Vidas</th><td><?= $vidas ?></td></tr>
        <tr><th>Implantação</th><td>R$ <?= number_format($valor_implantacao,2,',','.') ?></td></tr>
        <tr><th>Mensal</th><td>R$ <?= number_format($valor_mensal,2,',','.') ?></td></tr>
        <tr><th>Total</th><td class="fw-bold">R$ <?= number_format($total_geral,2,',','.') ?></td></tr>
      </table>
      <a href="../clientes/ver.php?id=<?= $cliente['id'] ?>" class="btn btn-success">Voltar ao Cliente</a>
    </div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
