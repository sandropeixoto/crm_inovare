<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = "Classificações";
$breadcrumb = "Auxiliares > Classificações";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $acao = $_POST['acao'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  $nome = trim($_POST['nome'] ?? '');
  $descricao = trim($_POST['descricao'] ?? '');
  $ativo = isset($_POST['ativo']) ? 1 : 0;

  if ($acao === 'salvar') {
    if ($id > 0) {
      run_query("UPDATE classificacoes SET nome=?, descricao=?, ativo=? WHERE id=?", [$nome, $descricao, $ativo, $id]);
      log_user_action($_SESSION['user']['id'], 'Atualizou classificacao', 'classificacoes', $id, null, $_POST);
    } else {
      run_query("INSERT INTO classificacoes (nome, descricao, ativo) VALUES (?,?,?)", [$nome, $descricao, $ativo]);
      $id = pdo()->lastInsertId();
      log_user_action($_SESSION['user']['id'], 'Criou classificacao', 'classificacoes', $id, null, $_POST);
    }
  } elseif ($acao === 'excluir' && $id > 0) {
    run_query("DELETE FROM classificacoes WHERE id=?", [$id]);
    log_user_action($_SESSION['user']['id'], 'Excluiu classificacao', 'classificacoes', $id);
  }
}

$dados = run_query("SELECT * FROM classificacoes ORDER BY id DESC");

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold text-primary">Lista de Classificações</h6>
      <button class="btn btn-sm btn-success" onclick="abrirModal()">+ Nova Classificação</button>
    </div>
    <table class="table table-striped table-sm align-middle">
      <thead class="table-light"><tr><th>ID</th><th>Nome</th><th>Descrição</th><th>Ativo</th><th class="text-end">Ações</th></tr></thead>
      <tbody>
        <?php foreach($dados as $d): ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['nome']) ?></td>
          <td><?= htmlspecialchars($d['descricao']) ?></td>
          <td><?= $d['ativo'] ? '✅' : '❌' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-primary" onclick='editar(<?= json_encode($d) ?>)'>Editar</button>
            <form method="POST" class="d-inline" onsubmit="return confirm('Excluir esta classificação?')">
              <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button class="btn btn-sm btn-danger">Excluir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalForm" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="id">
        <div class="modal-header">
          <h5 class="modal-title">Cadastro de Classificação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nome *</label>
          <input type="text" name="nome" id="nome" class="form-control mb-3" required>
          <label class="form-label">Descrição</label>
          <textarea name="descricao" id="descricao" rows="2" class="form-control mb-3"></textarea>
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
let modal; document.addEventListener('DOMContentLoaded',()=>{ modal = new bootstrap.Modal(document.getElementById('modalForm')); });
function abrirModal(){ document.querySelectorAll('#modalForm input, #modalForm textarea').forEach(e=>e.value=''); document.getElementById('ativo').checked=true; modal.show(); }
function editar(d){ document.getElementById('id').value=d.id; document.getElementById('nome').value=d.nome; document.getElementById('descricao').value=d.descricao; document.getElementById('ativo').checked=d.ativo==1; modal.show(); }
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
