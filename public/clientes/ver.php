<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID de cliente inválido.');
}

$cliente = run_query("SELECT c.*, u.nome AS responsavel_nome
                      FROM clientes c
                      LEFT JOIN usuarios u ON u.id = c.responsavel_comercial
                      WHERE c.id = ?", [$id])[0] ?? null;

if (!$cliente) {
    http_response_code(404);
    exit('Cliente não encontrado.');
}

log_user_action($_SESSION['user']['id'], 'Visualizou cliente', 'clientes', $id, null, $cliente);

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Cliente - <?= h($cliente['nome_fantasia']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Cliente: <?= h($cliente['nome_fantasia']) ?></h4>
    <div>
      <a href="listar.php" class="btn btn-secondary">Voltar</a>
      <a href="editar.php?id=<?= $id ?>" class="btn btn-primary">Editar</a>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h6 class="text-muted mb-3">Informações Gerais</h6>
      <div class="row mb-2">
        <div class="col-md-6"><strong>Razão Social:</strong> <?= h($cliente['razao_social']) ?></div>
        <div class="col-md-3"><strong>CNPJ:</strong> <?= h($cliente['cnpj']) ?></div>
        <div class="col-md-3"><strong>Status:</strong> 
          <span class="badge bg-<?= $cliente['status']==='ativo'?'success':($cliente['status']==='prospecto'?'warning text-dark':'secondary') ?>">
            <?= h(ucfirst($cliente['status'])) ?>
          </span>
        </div>
      </div>
      <div class="row mb-2">
        <div class="col-md-4"><strong>Email:</strong> <?= h($cliente['email']) ?></div>
        <div class="col-md-4"><strong>Telefone:</strong> <?= h($cliente['telefone']) ?></div>
        <div class="col-md-4"><strong>Responsável:</strong> <?= h($cliente['responsavel_nome']) ?></div>
      </div>
      <div class="row">
        <div class="col-md-6"><strong>Cidade:</strong> <?= h($cliente['cidade']) ?> / <?= h($cliente['uf']) ?></div>
        <div class="col-md-3"><strong>Origem:</strong> <?= h($cliente['origem']) ?></div>
        <div class="col-md-3"><strong>Cadastrado em:</strong> <?= h($cliente['criado_em']) ?></div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold">Últimas Propostas</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>ID</th><th>Pacote</th><th>Status</th><th>Data</th></tr>
            </thead>
            <tbody>
              <?php
              $props = run_query("SELECT p.id, pa.nome AS pacote, p.status, p.criado_em
                                  FROM propostas p
                                  LEFT JOIN pacotes pa ON pa.id=p.id_pacote
                                  WHERE p.id_cliente=? ORDER BY p.id DESC LIMIT 5", [$id]);
              if(!$props): ?>
                <tr><td colspan="4" class="text-center py-3">Nenhuma proposta registrada.</td></tr>
              <?php else: foreach($props as $p): ?>
                <tr>
                  <td><?= (int)$p['id'] ?></td>
                  <td><?= h($p['pacote']) ?></td>
                  <td><span class="badge bg-<?= $p['status']==='aceita'?'success':($p['status']==='enviada'?'primary':($p['status']==='rejeitada'?'danger':'secondary')) ?>">
                    <?= h(ucfirst($p['status'])) ?></span></td>
                  <td><?= h($p['criado_em']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold">Últimas Interações</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Tipo</th><th>Descrição</th><th>Usuário</th><th>Data</th></tr>
            </thead>
            <tbody>
              <?php
              $ints = run_query("SELECT i.*, u.nome AS usuario
                                 FROM interacoes i
                                 LEFT JOIN usuarios u ON u.id=i.id_usuario
                                 WHERE i.id_cliente=? ORDER BY i.id DESC LIMIT 5", [$id]);
              if(!$ints): ?>
                <tr><td colspan="4" class="text-center py-3">Nenhuma interação registrada.</td></tr>
              <?php else: foreach($ints as $i): ?>
                <tr>
                  <td><?= h(ucfirst($i['tipo'])) ?></td>
                  <td><?= h($i['descricao']) ?></td>
                  <td><?= h($i['usuario']) ?></td>
                  <td><?= h($i['criado_em']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer text-end">
          <a href="../interacoes/cliente.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
