<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial','visualizador']);

$id_cliente = (int)($_GET['id'] ?? 0);
if ($id_cliente <= 0) {
    http_response_code(400);
    exit('ID de cliente invalido.');
}

$cliente = run_query("SELECT nome_fantasia FROM clientes WHERE id = ?", [$id_cliente])[0] ?? null;
if (!$cliente) {
    http_response_code(404);
    exit('Cliente nao encontrado.');
}

$tiposDisponiveis = carregar_tipos_interacao();
$temTipos = !empty($tiposDisponiveis);
$tipoAtual = (int)($_POST['id_tipo_interacao'] ?? 0);
if ($tipoAtual <= 0 && $temTipos) {
    $idsTipos = array_keys($tiposDisponiveis);
    $tipoAtual = (int)($idsTipos[0] ?? 0);
}

$descricaoAtual = $_POST['descricao'] ?? '';
$proximaAtual = $_POST['proxima_acao'] ?? '';
$alertaTipos = $temTipos ? null : 'Nenhum tipo de interacao cadastrado. Utilize Auxiliares > Generic CRUD com a tabela interacoes_tipos.';
$idUsuarioAtual = (int)($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descricao = trim($_POST['descricao'] ?? '');
    $proxima_acao = $_POST['proxima_acao'] ?: null;
    $tipoSelecionado = (int)($_POST['id_tipo_interacao'] ?? 0);

    if (!$temTipos) {
        $erro = 'Cadastre ao menos um tipo de interacao antes de registrar.';
    } elseif ($tipoSelecionado <= 0 || !isset($tiposDisponiveis[$tipoSelecionado])) {
        $erro = 'Selecione um tipo de interacao valido.';
        $tipoAtual = $tipoSelecionado;
    } elseif ($descricao === '') {
        $erro = 'Descricao obrigatoria.';
        $tipoAtual = $tipoSelecionado;
    } else {
        $dados = [
            'id_cliente' => $id_cliente,
            'id_usuario' => $idUsuarioAtual ?: null,
            'id_tipo_interacao' => $tipoSelecionado,
            'descricao' => $descricao,
            'proxima_acao' => $proxima_acao
        ];

        try {
            $stmt = pdo()->prepare("
                INSERT INTO interacoes (id_cliente, id_usuario, id_tipo_interacao, descricao, proxima_acao)
                VALUES (?,?,?,?,?)
            ");
            $stmt->execute([
                $dados['id_cliente'],
                $dados['id_usuario'],
                $dados['id_tipo_interacao'],
                $dados['descricao'],
                $dados['proxima_acao']
            ]);

            $idInteracao = (int)pdo()->lastInsertId();
            log_user_action($idUsuarioAtual ?: null, 'Criou interacao', 'interacoes', $idInteracao, null, $dados);

            $sucesso = 'Interacao registrada com sucesso.';
            $descricaoAtual = '';
            $proximaAtual = '';
            $tipoAtual = $dados['id_tipo_interacao'];
        } catch (Throwable $e) {
            log_system('error', 'Falha ao registrar interacao: ' . $e->getMessage(), __FILE__, __LINE__);
            $erro = 'Nao foi possivel registrar a interacao. Tente novamente.';
        }
    }
}

$interacoes = carregar_interacoes_cliente($id_cliente);

log_user_action($idUsuarioAtual ?: null, 'Visualizou interacoes', 'interacoes', null, ['cliente'=>$id_cliente], null);

function h($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}

function carregar_tipos_interacao(): array
{
    try {
        $stmt = pdo()->query("SELECT id, tipo_interacao FROM interacoes_tipos ORDER BY tipo_interacao");
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($linhas)) {
            return [];
        }

        $tipos = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $id = (int)($linha['id'] ?? 0);
            $descricao = trim((string)($linha['tipo_interacao'] ?? ''));
            if ($id > 0 && $descricao !== '') {
                $tipos[$id] = $descricao;
            }
        }

        return $tipos;
    } catch (Throwable $e) {
        log_system('warning', 'interacoes_tipos indisponivel: ' . $e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}

function carregar_interacoes_cliente(int $idCliente): array
{
    try {
        $stmt = pdo()->prepare("
            SELECT i.*, u.nome AS usuario, COALESCE(t.tipo_interacao, i.tipo) AS tipo_exibicao
            FROM interacoes i
            LEFT JOIN usuarios u ON u.id = i.id_usuario
            LEFT JOIN interacoes_tipos t ON t.id = i.id_tipo_interacao
            WHERE i.id_cliente = ?
            ORDER BY i.id DESC
        ");
        $stmt->execute([$idCliente]);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($linhas)) {
            return $linhas;
        }
    } catch (Throwable $e) {
        log_system('warning', 'Fallback interacoes sem tipos auxiliares: ' . $e->getMessage(), __FILE__, __LINE__);
    }

    try {
        $stmt = pdo()->prepare("
            SELECT i.*, u.nome AS usuario, i.tipo AS tipo_exibicao
            FROM interacoes i
            LEFT JOIN usuarios u ON u.id = i.id_usuario
            WHERE i.id_cliente = ?
            ORDER BY i.id DESC
        ");
        $stmt->execute([$idCliente]);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($linhas) ? $linhas : [];
    } catch (Throwable $e) {
        log_system('error', 'Falha total ao carregar interacoes: ' . $e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Intera&ccedil;&otilde;es - <?= h($cliente['nome_fantasia']) ?></title>
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
    <h4>Intera&ccedil;&otilde;es - <?= h($cliente['nome_fantasia']) ?></h4>
    <a href="../clientes/ver.php?id=<?= $id_cliente ?>" class="btn btn-secondary">Voltar ao Cliente</a>
  </div>

  <?php if(!empty($sucesso)): ?>
    <div class="alert alert-success"><?= h($sucesso) ?></div>
  <?php elseif(!empty($erro)): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
  <?php endif; ?>

  <?php if(!empty($alertaTipos)): ?>
    <div class="alert alert-warning"><?= h($alertaTipos) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Nova Intera&ccedil;&atilde;o</div>
    <div class="card-body">
      <form method="POST" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <select name="id_tipo_interacao" class="form-select" <?= $temTipos ? '' : 'disabled' ?> required>
            <?php if ($tiposDisponiveis): ?>
              <?php foreach ($tiposDisponiveis as $valor => $rotulo):
                $valorInt = (int)$valor;
              ?>
                <option value="<?= $valorInt ?>" <?= $valorInt === $tipoAtual ? 'selected' : '' ?>><?= e($rotulo) ?></option>
              <?php endforeach; ?>
            <?php else: ?>
              <option value="">Cadastre os tipos de interacao</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Descri&ccedil;&atilde;o *</label>
          <input type="text" name="descricao" class="form-control" required placeholder="Ex: Contato via WhatsApp para proposta" value="<?= h($descricaoAtual) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Pr&oacute;xima A&ccedil;&atilde;o</label>
          <input type="date" name="proxima_acao" class="form-control" value="<?= h($proximaAtual) ?>">
        </div>
        <div class="col-12 text-end">
          <button class="btn btn-primary" type="submit" <?= $temTipos ? '' : 'disabled' ?>>Registrar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Hist&oacute;rico de Intera&ccedil;&otilde;es</div>
    <div class="card-body">
      <?php if(!$interacoes): ?>
        <p class="text-muted">Nenhuma intera&ccedil;&atilde;o registrada.</p>
      <?php else: ?>
        <div class="timeline">
          <?php foreach($interacoes as $i): ?>
            <?php
              $tipo = trim((string)($i['tipo_exibicao'] ?? ''));
              if ($tipo === '') {
                  $tipo = 'Tipo nao informado';
              }
              $descricao = $i['descricao'] ?? '';
              $autor = trim((string)($i['usuario'] ?? ''));
              if ($autor === '') { $autor = '-'; }
              $criadoBruto = $i['criado_em'] ?? null;
              $criado = '-';
              if ($criadoBruto) {
                  $timestampCriado = strtotime((string)$criadoBruto);
                  $criado = $timestampCriado ? date('d/m/Y H:i', $timestampCriado) : (string)$criadoBruto;
              }
              $proxima = $i['proxima_acao'] ?? null;
              $proximaFmt = null;
              if (!empty($proxima)) {
                  $timestampProx = strtotime((string)$proxima);
                  $proximaFmt = $timestampProx ? date('d/m/Y', $timestampProx) : (string)$proxima;
              }
            ?>
            <div class="timeline-item">
              <div class="fw-semibold text-primary"><?= h($tipo) ?></div>
              <div><?= h($descricao) ?></div>
              <div class="small text-muted">
                Por <?= h($autor) ?> em <?= h($criado) ?>
                <?php if(!empty($proximaFmt)): ?> | Pr&oacute;x: <?= h($proximaFmt) ?><?php endif; ?>
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
