<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin', 'gestor', 'comercial', 'visualizador']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    abort(400, 'ID de cliente invÃ¡lido.');
}

$cliente = run_query(
    'SELECT c.*, u.nome AS responsavel_nome
     FROM clientes c
     LEFT JOIN usuarios u ON u.id = c.responsavel_comercial
     WHERE c.id = ?',
    [$id]
)[0] ?? null;

if (!$cliente) {
    abort(404, 'Cliente nÃ£o encontrado.');
}

$digitsCnpj = preg_replace('/\D+/', '', (string)($cliente['cnpj'] ?? ''));
$cnpjFormatado = strlen($digitsCnpj) === 14
    ? substr($digitsCnpj, 0, 2) . '.' . substr($digitsCnpj, 2, 3) . '.' . substr($digitsCnpj, 5, 3)
        . '/' . substr($digitsCnpj, 8, 4) . '-' . substr($digitsCnpj, 12)
    : (string)($cliente['cnpj'] ?? '');

$digitsTelefone = preg_replace('/\D+/', '', (string)($cliente['telefone'] ?? ''));
if (strlen($digitsTelefone) >= 10) {
    $telefoneCorpo = strlen($digitsTelefone) > 10
        ? substr($digitsTelefone, 2, 5) . '-' . substr($digitsTelefone, 7)
        : substr($digitsTelefone, 2, 4) . '-' . substr($digitsTelefone, 6);
    $telefoneFormatado = '(' . substr($digitsTelefone, 0, 2) . ') ' . $telefoneCorpo;
} else {
    $telefoneFormatado = (string)($cliente['telefone'] ?? '');
}

$digitsCep = preg_replace('/\D+/', '', (string)($cliente['cep'] ?? ''));
$cepFormatado = strlen($digitsCep) === 8
    ? substr($digitsCep, 0, 5) . '-' . substr($digitsCep, 5)
    : (string)($cliente['cep'] ?? '');

$qtdColaboradores = (int)($cliente['qtd_colaboradores'] ?? 0);

$contatos = run_query(
    'SELECT nome, cargo, email, telefone, principal
     FROM contatos_clientes
     WHERE id_cliente = ?
     ORDER BY principal DESC, nome ASC',
    [$id]
);

function format_phone_view(?string $value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    if (strlen($digits) < 10) {
        return $value ?? '';
    }
    $ddd = substr($digits, 0, 2);
    $middle = strlen($digits) > 10 ? substr($digits, 2, 5) : substr($digits, 2, 4);
    $end = strlen($digits) > 10 ? substr($digits, 7) : substr($digits, 6);
    return sprintf('(%s) %s-%s', $ddd, $middle, $end);
}

$propostas = run_query(
    'SELECT p.id, p.codigo_proposta, p.descricao, p.status, p.data_envio, p.total_geral, pa.nome AS pacote
     FROM propostas p
     LEFT JOIN pacotes pa ON pa.id = p.id_pacote
     WHERE p.id_cliente = ?
     ORDER BY p.id DESC
     LIMIT 5',
    [$id]
);

$interacoes = [];
try {
    $stmtInteracoes = pdo()->prepare("
        SELECT i.*, u.nome AS usuario, COALESCE(t.tipo_interacao, i.tipo) AS tipo_exibicao
        FROM interacoes i
        LEFT JOIN usuarios u ON u.id = i.id_usuario
        LEFT JOIN interacoes_tipos t ON t.id = i.id_tipo_interacao
        WHERE i.id_cliente = ?
        ORDER BY i.id DESC
        LIMIT 5
    ");
    $stmtInteracoes->execute([$id]);
    $resultadoInteracoes = $stmtInteracoes->fetchAll(PDO::FETCH_ASSOC);
    if (is_array($resultadoInteracoes)) {
        $interacoes = $resultadoInteracoes;
    }
} catch (Throwable $e) {
    log_system('warning', 'Resumo de interacoes com tipos auxiliares indisponivel: ' . $e->getMessage(), __FILE__, __LINE__);
    try {
        $stmtInteracoesFallback = pdo()->prepare("
            SELECT i.*, u.nome AS usuario, i.tipo AS tipo_exibicao
            FROM interacoes i
            LEFT JOIN usuarios u ON u.id = i.id_usuario
            WHERE i.id_cliente = ?
            ORDER BY i.id DESC
            LIMIT 5
        ");
        $stmtInteracoesFallback->execute([$id]);
        $resultadoInteracoes = $stmtInteracoesFallback->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($resultadoInteracoes)) {
            $interacoes = $resultadoInteracoes;
        }
    } catch (Throwable $erroFallback) {
        log_system('error', 'Falha ao carregar resumo de interacoes: ' . $erroFallback->getMessage(), __FILE__, __LINE__);
        $interacoes = [];
    }
}

log_user_action(current_user()['id'] ?? null, 'Visualizou cliente', 'clientes', $id, null, $cliente);

$page_title = 'Cliente: ' . $cliente['nome_fantasia'];
$breadcrumb = 'Clientes > VisualizaÃ§Ã£o';

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-1">InformaÃ§Ãµes do Cliente</h5>
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
        <strong>RazÃ£o Social:</strong><br><?= e($cliente['razao_social'] ?: '-') ?>
      </div>
      <div class="col-md-3">
        <strong>CNPJ:</strong><br><?= e($cnpjFormatado ?: '-') ?>
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
        <span class="badge bg-<?= $statusClass ?>"><?= e(ucfirst((string)$cliente['status'])) ?></span>
      </div>
      <div class="col-md-2">
        <strong>Origem:</strong><br><?= e($cliente['origem'] ?: '-') ?>
      </div>
      <div class="col-md-4">
        <strong>E-mail:</strong><br><?= e($cliente['email'] ?: '-') ?>
      </div>
      <div class="col-md-4">
        <strong>Telefone:</strong><br><?= e($telefoneFormatado ?: '-') ?>
      </div>
      <div class="col-md-4">
        <strong>ResponsÃ¡vel:</strong><br><?= e($cliente['responsavel_nome'] ?: '-') ?>
      </div>
      <div class="col-md-6">
        <strong>EndereÃ§o:</strong><br><?= e($cliente['endereco'] ?: '-') ?>
      </div>
      <div class="col-md-3">
        <strong>Bairro:</strong><br><?= e($cliente['bairro'] ?: '-') ?>
      </div>
      <div class="col-md-3">
        <strong>CEP:</strong><br><?= e($cepFormatado ?: '-') ?>
      </div>
      <div class="col-md-4">
        <strong>Cidade:</strong><br><?= e($cliente['cidade'] ?: '-') ?> / <?= e($cliente['uf'] ?: '-') ?>
      </div>
      <div class="col-md-4">
        <strong>Qtd. de Colaboradores:</strong><br><?= $qtdColaboradores ?>
      </div>
      <div class="col-md-4">
        <strong>Cadastrado em:</strong><br><?= e($cliente['criado_em'] ?? '-') ?>
      </div>
      <div class="col-md-4">
        <strong>Atualizado em:</strong><br><?= e($cliente['atualizado_em'] ?? '-') ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">Contatos</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Nome</th>
            <th>Cargo</th>
            <th>E-mail</th>
            <th>Telefone</th>
            <th>Principal</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$contatos): ?>
            <tr><td colspan="5" class="text-center py-3">Nenhum contato cadastrado.</td></tr>
          <?php else: ?>
            <?php foreach ($contatos as $contato): ?>
              <tr>
                <td><?= e($contato['nome'] ?? '-') ?></td>
                <td><?= e($contato['cargo'] ?? '-') ?></td>
                <td><?= e($contato['email'] ?? '-') ?></td>
                <td><?= e(format_phone_view($contato['telefone'] ?? '')) ?></td>
                <td><?= !empty($contato['principal']) ? '<span class="badge bg-primary">Sim</span>' : 'NÃ£o' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100 shadow-sm">
      <div class="card-header bg-white fw-semibold">&Uacute;ltimas Propostas</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>C&oacute;digo</th>
              <th>Status</th>
              <th>Total (R$)</th>
              <th>Envio</th>
              <th class="text-end">A&ccedil;&otilde;es</th>
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
                    'expirada' => 'secondary',
                    default => 'warning text-dark'
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
      <div class="card-header bg-white fw-semibold">&Uacute;ltimas Intera&ccedil;&otilde;es</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Tipo</th>
              <th>Descri&ccedil;&atilde;o</th>
              <th>Usu&aacute;rio</th>
              <th>Data</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$interacoes): ?>
              <tr><td colspan="4" class="text-center py-3">Nenhuma intera&ccedil;&atilde;o registrada.</td></tr>
            <?php else: ?>
              <?php foreach ($interacoes as $i): ?>
                <?php
                  $tipoLinha = trim((string)($i['tipo_exibicao'] ?? ''));
                  if ($tipoLinha === '') {
                      $tipoLinha = '-';
                  }
                  $descricaoLinha = $i['descricao'] ?? '-';
                  if ($descricaoLinha === '') {
                      $descricaoLinha = '-';
                  }
                  $autorLinha = trim((string)($i['usuario'] ?? ''));
                  if ($autorLinha === '') {
                      $autorLinha = '-';
                  }
                  $dataLinhaBruta = $i['criado_em'] ?? null;
                  $dataLinha = '-';
                  if ($dataLinhaBruta) {
                      $timestampResumo = strtotime((string)$dataLinhaBruta);
                      $dataLinha = $timestampResumo ? date('d/m/Y H:i', $timestampResumo) : (string)$dataLinhaBruta;
                  }
                ?>
                <tr>
                  <td><?= e($tipoLinha) ?></td>
                  <td><?= e($descricaoLinha) ?></td>
                  <td><?= e($autorLinha) ?></td>
                  <td><?= e($dataLinha) ?></td>
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


