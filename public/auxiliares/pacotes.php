<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = "Pacotes NR-01";
$breadcrumb = "Auxiliares > Pacotes";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $acao = $_POST['acao'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  $nome = trim($_POST['nome'] ?? '');
  $descricao = trim($_POST['descricao'] ?? '');
  $tipo_calculo = trim($_POST['tipo_calculo'] ?? '');
  $conformidade = trim($_POST['conformidade'] ?? '');
  $sinistralidade_padrao = $_POST['sinistralidade_padrao'] ?? '';
  $franquia_padrao = $_POST['franquia_padrao'] ?? '';
  $valor_implantacao_base = $_POST['valor_implantacao_base'] ?? '';
  $valor_mensal_base = $_POST['valor_mensal_base'] ?? '';
  $ativo = isset($_POST['ativo']) ? 1 : 0;

  $sinistralidade_padrao = $sinistralidade_padrao === '' ? null : round((float)str_replace(',', '.', $sinistralidade_padrao), 2);
  $franquia_padrao = $franquia_padrao === '' ? null : round((float)str_replace(',', '.', $franquia_padrao), 2);
  $valor_implantacao_base = $valor_implantacao_base === '' ? null : round((float)str_replace(',', '.', $valor_implantacao_base), 2);
  $valor_mensal_base = $valor_mensal_base === '' ? null : round((float)str_replace(',', '.', $valor_mensal_base), 2);

  if ($acao === 'salvar') {
    $payload = [
      'nome' => $nome,
      'descricao' => $descricao,
      'tipo_calculo' => $tipo_calculo,
      'conformidade' => $conformidade,
      'sinistralidade_padrao' => $sinistralidade_padrao,
      'franquia_padrao' => $franquia_padrao,
      'valor_implantacao_base' => $valor_implantacao_base,
      'valor_mensal_base' => $valor_mensal_base,
      'ativo' => $ativo,
    ];

    if ($id > 0) {
      run_query(
        "UPDATE pacotes
            SET nome=?, descricao=?, tipo_calculo=?, conformidade=?, sinistralidade_padrao=?, franquia_padrao=?, valor_implantacao_base=?, valor_mensal_base=?, ativo=?
          WHERE id=?",
        [
          $nome,
          $descricao,
          $tipo_calculo,
          $conformidade,
          $sinistralidade_padrao,
          $franquia_padrao,
          $valor_implantacao_base,
          $valor_mensal_base,
          $ativo,
          $id,
        ]
      );
      log_user_action($_SESSION['user']['id'], 'Atualizou pacote', 'pacotes', $id, null, $payload);
    } else {
      run_query(
        "INSERT INTO pacotes
            (nome, descricao, tipo_calculo, conformidade, sinistralidade_padrao, franquia_padrao, valor_implantacao_base, valor_mensal_base, ativo)
         VALUES (?,?,?,?,?,?,?,?,?)",
        [
          $nome,
          $descricao,
          $tipo_calculo,
          $conformidade,
          $sinistralidade_padrao,
          $franquia_padrao,
          $valor_implantacao_base,
          $valor_mensal_base,
          $ativo,
        ]
      );
      $id = pdo()->lastInsertId();
      log_user_action($_SESSION['user']['id'], 'Criou pacote', 'pacotes', $id, null, $payload);
    }
  } elseif ($acao === 'excluir' && $id > 0) {
    run_query("DELETE FROM pacotes WHERE id=?", [$id]);
    log_user_action($_SESSION['user']['id'], 'Excluiu pacote', 'pacotes', $id);
  }
}

$dados = run_query("SELECT * FROM pacotes ORDER BY id DESC");

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold text-primary">Lista de Pacotes</h6>
      <button class="btn btn-sm btn-success" onclick="abrirModal()">+ Novo Pacote</button>
    </div>
        <table class="table table-striped table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>Tipo Calculo</th>
          <th>Sinistralidade (%)</th>
          <th>Franquia (%)</th>
          <th>Implantacao (R$)</th>
          <th>Mensal (R$)</th>
          <th>Conformidade</th>
          <th>Ativo</th>
          <th class="text-end">Acoes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dados as $d): ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['nome']) ?></td>
          <td><?= htmlspecialchars($d['tipo_calculo']) ?></td>
          <td><?= $d['sinistralidade_padrao'] !== null ? number_format((float)$d['sinistralidade_padrao'], 2, ',', '.') : '-' ?></td>
          <td><?= $d['franquia_padrao'] !== null ? number_format((float)$d['franquia_padrao'], 2, ',', '.') : '-' ?></td>
          <td><?= $d['valor_implantacao_base'] !== null ? 'R$ ' . number_format((float)$d['valor_implantacao_base'], 2, ',', '.') : '-' ?></td>
          <td><?= $d['valor_mensal_base'] !== null ? 'R$ ' . number_format((float)$d['valor_mensal_base'], 2, ',', '.') : '-' ?></td>
          <td><?= htmlspecialchars($d['conformidade']) ?></td>
          <td><?= $d['ativo'] ? 'Sim' : 'Nao' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-primary" 
              onclick='editar(<?= json_encode($d, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>Editar</button>
            <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este pacote?')">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button class="btn btn-sm btn-danger">Excluir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="modalForm" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="id">
        <div class="modal-header">
          <h5 class="modal-title">Cadastro de Pacote</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nome *</label>
              <input type="text" name="nome" id="nome" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipo de Calculo</label>
              <input type="text" name="tipo_calculo" id="tipo_calculo" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Descricao</label>
              <textarea name="descricao" id="descricao" rows="2" class="form-control"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Conformidade</label>
              <input type="text" name="conformidade" id="conformidade" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Sinistralidade (%)</label>
              <input type="number" name="sinistralidade_padrao" id="sinistralidade_padrao" class="form-control" step="0.01" min="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">Franquia (%)</label>
              <input type="number" name="franquia_padrao" id="franquia_padrao" class="form-control" step="0.01" min="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">Implantacao (R$)</label>
              <input type="number" name="valor_implantacao_base" id="valor_implantacao_base" class="form-control" step="0.01" min="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">Mensal (R$)</label>
              <input type="number" name="valor_mensal_base" id="valor_mensal_base" class="form-control" step="0.01" min="0">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="ativo" id="ativo" checked>
                <label class="form-check-label" for="ativo">Ativo</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">Salvar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modal;
document.addEventListener('DOMContentLoaded', () => {
  modal = new bootstrap.Modal(document.getElementById('modalForm'));
});

function setNumericValue(selector, value) {
  const input = document.querySelector(selector);
  if (!input) {
    return;
  }
  if (value === null || value === undefined || value === '') {
    input.value = '';
    return;
  }
  const num = Number(value);
  input.value = Number.isFinite(num) ? num.toFixed(2) : '';
}

function abrirModal(){
  document.querySelector('#id').value='';
  document.querySelector('#nome').value='';
  document.querySelector('#descricao').value='';
  document.querySelector('#tipo_calculo').value='';
  document.querySelector('#conformidade').value='';
  setNumericValue('#sinistralidade_padrao', '');
  setNumericValue('#franquia_padrao', '');
  setNumericValue('#valor_implantacao_base', '');
  setNumericValue('#valor_mensal_base', '');
  document.querySelector('#ativo').checked=true;
  modal.show();
}
function editar(d){
  document.querySelector('#id').value=d.id;
  document.querySelector('#nome').value=d.nome;
  document.querySelector('#descricao').value=d.descricao;
  document.querySelector('#tipo_calculo').value=d.tipo_calculo;
  document.querySelector('#conformidade').value=d.conformidade;
  setNumericValue('#sinistralidade_padrao', d.sinistralidade_padrao);
  setNumericValue('#franquia_padrao', d.franquia_padrao);
  setNumericValue('#valor_implantacao_base', d.valor_implantacao_base);
  setNumericValue('#valor_mensal_base', d.valor_mensal_base);
  document.querySelector('#ativo').checked=d.ativo==1;
  modal.show();
}
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
