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
  $ativo = isset($_POST['ativo']) ? 1 : 0;

  if ($acao === 'salvar') {
    if ($id > 0) {
      run_query("UPDATE pacotes SET nome=?, descricao=?, tipo_calculo=?, conformidade=?, ativo=? WHERE id=?",
        [$nome, $descricao, $tipo_calculo, $conformidade, $ativo, $id]);
      log_user_action($_SESSION['user']['id'], 'Atualizou pacote', 'pacotes', $id, null, $_POST);
    } else {
      run_query("INSERT INTO pacotes (nome, descricao, tipo_calculo, conformidade, ativo) VALUES (?,?,?,?,?)",
        [$nome, $descricao, $tipo_calculo, $conformidade, $ativo]);
      $id = pdo()->lastInsertId();
      log_user_action($_SESSION['user']['id'], 'Criou pacote', 'pacotes', $id, null, $_POST);
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
          <th>Tipo Cálculo</th>
          <th>Conformidade</th>
          <th>Ativo</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($dados as $d): ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['nome']) ?></td>
          <td><?= htmlspecialchars($d['tipo_calculo']) ?></td>
          <td><?= htmlspecialchars($d['conformidade']) ?></td>
          <td><?= $d['ativo'] ? '✅' : '❌' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-primary" 
              onclick='editar(<?= json_encode($d, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Editar</button>
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
          <div class="mb-3">
            <label class="form-label">Nome *</label>
            <input type="text" name="nome" id="nome" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descrição</label>
            <textarea name="descricao" id="descricao" rows="2" class="form-control"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo de Cálculo</label>
            <input type="text" name="tipo_calculo" id="tipo_calculo" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Conformidade</label>
            <input type="text" name="conformidade" id="conformidade" class="form-control">
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" checked>
            <label class="form-check-label" for="ativo">Ativo</label>
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
function abrirModal(){
  document.querySelector('#id').value='';
  document.querySelector('#nome').value='';
  document.querySelector('#descricao').value='';
  document.querySelector('#tipo_calculo').value='';
  document.querySelector('#conformidade').value='';
  document.querySelector('#ativo').checked=true;
  modal.show();
}
function editar(d){
  document.querySelector('#id').value=d.id;
  document.querySelector('#nome').value=d.nome;
  document.querySelector('#descricao').value=d.descricao;
  document.querySelector('#tipo_calculo').value=d.tipo_calculo;
  document.querySelector('#conformidade').value=d.conformidade;
  document.querySelector('#ativo').checked=d.ativo==1;
  modal.show();
}
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
