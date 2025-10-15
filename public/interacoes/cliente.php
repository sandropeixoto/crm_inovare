<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$id_cliente = (int)($_GET['id'] ?? 0);
if ($id_cliente <= 0) {
    http_response_code(400);
    exit('ID de cliente inválido.');
}

// Busca dados do cliente
$cliente = run_query("SELECT nome_fantasia FROM clientes WHERE id = ?", [$id_cliente])[0] ?? null;
if (!$cliente) {
    http_response_code(404);
    exit('Cliente não encontrado.');
}

// Inserção de nova interação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? 'outro';
    $descricao = trim($_POST['descricao'] ?? '');
    $proxima_acao = $_POST['proxima_acao'] ?: null;

    if ($descricao === '') {
        $erro = 'Descrição obrigatória.';
    } else {
        $dados = [
            'id_cliente' => $id_cliente,
            'id_usuario' => $_SESSION['user']['id'],
            'tipo' => $tipo,
            'descricao' => $descricao,
            'proxima_acao' => $proxima_acao
        ];

        run_query(
            "INSERT INTO interacoes (id_cliente, id_usuario, tipo, descricao, proxima_acao) VALUES (?,?,?,?,?)",
            array_values($dados)
        );

        log_user_action($_SESSION['user']['id'], 'Criou interação', 'interacoes', pdo()->lastInsertId(), null, $dados);
        $sucesso = 'Interação registrada com sucesso.';
    }
}

// Carregar todas as interações
$interacoesRaw = run_query("
    SELECT i.*, u.nome AS usuario
    FROM interacoes i
    LEFT JOIN usuarios u ON u.id = i.id_usuario
    WHERE i.id_cliente = ?
    ORDER BY i.id DESC
", [$id_cliente]);

$interacoes = [];
if (is_array($interacoesRaw)) {
    foreach ($interacoesRaw as $linha) {
        if (is_array($linha)) {
            $interacoes[] = $linha;
        }
    }
}

log_user_action($_SESSION['user']['id'], 'Visualizou interacoes', 'interacoes', null, ['cliente'=>$id_cliente], null);

function format_interacao_tipo(?string $tipo): string
{
    $map = [
        'ligacao' => 'Liga��o',
        'email' => 'E-mail',
        'whatsapp' => 'WhatsApp',
        'reuniao' => 'Reuni�o',
        'visita' => 'Visita',
        'outro' => 'Outro',
    ];
    $tipo = $tipo ?? '';
    return $map[strtolower($tipo)] ?? ucfirst($tipo);
}

function h($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}

$tipoAtual = $_POST['tipo'] ?? 'ligacao';
$descricaoAtual = $_POST['descricao'] ?? '';
$proximaAtual = $_POST['proxima_acao'] ?? '';
$tiposDisponiveis = [
    'ligacao' => format_interacao_tipo('ligacao'),
    'email' => format_interacao_tipo('email'),
    'whatsapp' => format_interacao_tipo('whatsapp'),
    'reuniao' => format_interacao_tipo('reuniao'),
    'visita' => format_interacao_tipo('visita'),
    'outro' => format_interacao_tipo('outro'),
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Interações - <?= h($cliente['nome_fantasia']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.timeline {
  position: relative;
  margin-left: 30px;
}
.timeline::before {
  content: '';
  position: absolute;
  left: 10px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #0d6efd;
}
.timeline-item {
  position: relative;
  margin-bottom: 15px;
  padding-left: 25px;
}
.timeline-item::before {
  content: '';
  position: absolute;
  left: 4px;
  top: 6px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #0d6efd;
}
</style>
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Interações - <?= h($cliente['nome_fantasia']) ?></h4>
    <a href="../clientes/ver.php?id=<?= $id_cliente ?>" class="btn btn-secondary">Voltar ao Cliente</a>
  </div>

  <?php if(!empty($sucesso)): ?>
    <div class="alert alert-success"><?= h($sucesso) ?></div>
  <?php elseif(!empty($erro)): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Nova Interação</div>
    <div class="card-body">
      <form method="POST" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <select name="tipo" class="form-select">
            <?php foreach ($tiposDisponiveis as $valor => $rotulo): ?>
              <option value="<?= e($valor) ?>" <?= $valor === $tipoAtual ? 'selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Descrição *</label>
          <input type="text" name="descricao" class="form-control" required placeholder="Ex: Contato via WhatsApp para proposta" value="<?= h($descricaoAtual) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Próxima Ação</label>
          <input type="date" name="proxima_acao" class="form-control" value="<?= h($proximaAtual) ?>">
        </div>
        <div class="col-12 text-end">
          <button class="btn btn-primary" type="submit">Registrar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Histórico de Interações</div>
    <div class="card-body">
      <?php if(!$interacoes): ?>
        <p class="text-muted">Nenhuma intera��o registrada.</p>
      <?php else: ?>
        <div class="timeline">
          <?php foreach($interacoes as $i): ?>
            <?php
              $tipo = format_interacao_tipo($i['tipo'] ?? null);
              $descricao = $i['descricao'] ?? '';
              $autor = $i['usuario'] ?? '-';
              if ($autor === '') { $autor = '-'; }
              $criado = $i['criado_em'] ?? '-';
              if ($criado === '') { $criado = '-'; }
              $proxima = $i['proxima_acao'] ?? null;
            ?>
            <div class="timeline-item">
              <div class="fw-semibold text-primary"><?= h($tipo) ?></div>
              <div><?= h($descricao) ?></div>
              <div class="small text-muted">
                Por <?= h($autor) ?> em <?= h($criado) ?>
                <?php if(!empty($proxima)): ?> | Pr�x: <?= h($proxima) ?><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
