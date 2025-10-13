<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = "Gerenciar Menus do Sistema";
$breadcrumb = "Auxiliares > Menus";

// CRUD normal (criar, editar, excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
  $acao = $_POST['acao'];
  $id = (int)($_POST['id'] ?? 0);

  if ($acao === 'salvar') {
    $titulo = trim($_POST['titulo']);
    $icone = trim($_POST['icone']);
    $link = trim($_POST['link']);
    $parent_id = $_POST['parent_id'] ?: null;
    $ordem = (int)($_POST['ordem'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $perfis_permitidos = implode(',', $_POST['perfis_permitidos'] ?? []);

    if ($id > 0) {
      run_query("UPDATE menus SET titulo=?, icone=?, link=?, parent_id=?, ordem=?, perfis_permitidos=?, ativo=? WHERE id=?",
        [$titulo, $icone, $link, $parent_id, $ordem, $perfis_permitidos, $ativo, $id]);
      log_user_action($_SESSION['user']['id'], 'Atualizou menu', 'menus', $id, null, $_POST);
    } else {
      run_query("INSERT INTO menus (titulo,icone,link,parent_id,ordem,perfis_permitidos,ativo) VALUES (?,?,?,?,?,?,?)",
        [$titulo, $icone, $link, $parent_id, $ordem, $perfis_permitidos, $ativo]);
      $id = pdo()->lastInsertId();
      log_user_action($_SESSION['user']['id'], 'Criou menu', 'menus', $id, null, $_POST);
    }
  } elseif ($acao === 'excluir' && $id > 0) {
    run_query("DELETE FROM menus WHERE id=?", [$id]);
    log_user_action($_SESSION['user']['id'], 'Excluiu menu', 'menus', $id);
  }
  exit(header("Location: menus.php"));
}

// AJAX — salvar reordenação hierárquica
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['estrutura_json'])) {
  $estrutura = json_decode($_POST['estrutura_json'], true);
  $ordem = 1;
  $atualizar = function($itens, $parent_id = null) use (&$atualizar, &$ordem) {
    foreach ($itens as $item) {
      $id = (int)$item['id'];
      run_query("UPDATE menus SET parent_id=?, ordem=? WHERE id=?", [$parent_id, $ordem++, $id]);
      if (!empty($item['children'])) {
        $atualizar($item['children'], $id);
      }
    }
  };
  $atualizar($estrutura);
  echo json_encode(['ok' => true]);
  exit;
}

// Carregar estrutura atual
$menus = run_query("SELECT * FROM menus ORDER BY parent_id, ordem, titulo");
function montarArvore($menus, $parent_id = null) {
  $html = '<ul class="sortable">';
  foreach ($menus as $m) {
    if ($m['parent_id'] == $parent_id) {
      $html .= '<li data-id="'.$m['id'].'">
        <div class="menu-item">
          <span class="handle">☰</span>
          <span class="titulo">'.htmlspecialchars($m['icone'].' '.$m['titulo']).'</span>
          <span class="text-muted small">'.htmlspecialchars($m['link'] ?: '(grupo)').'</span>
          <div class="acoes">
            <button class="btn btn-sm btn-primary" onclick=\'editar('.json_encode($m).')\'>✏️</button>
            <form method="POST" class="d-inline" onsubmit="return confirm(\'Excluir este menu?\')">
              <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="'.$m['id'].'">
              <button class="btn btn-sm btn-danger">🗑️</button>
            </form>
          </div>
        </div>';
      $html .= montarArvore($menus, $m['id']);
      $html .= '</li>';
    }
  }
  return $html.'</ul>';
}

ob_start();
?>
<style>
.sortable { list-style:none; margin-left:0; padding-left:0; }
.sortable li { margin:6px 0; background:#f8f9fa; border-radius:8px; padding:8px; }
.menu-item { display:flex; justify-content:space-between; align-items:center; cursor:move; }
.menu-item .handle { cursor:grab; color:#6c757d; font-size:18px; margin-right:6px; }
.menu-item .acoes button { margin-left:3px; }
.sortable li ul { margin-left:20px; }
.placeholder { background:#cfe2ff; border:2px dashed #6ea8fe; min-height:35px; border-radius:5px; }
</style>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold text-primary">Menus do Sistema</h6>
      <button class="btn btn-sm btn-success" onclick="abrirModal()">+ Novo Menu</button>
    </div>

    <div id="menuTree">
      <?= montarArvore($menus) ?>
    </div>
  </div>
</div>

<!-- MODAL CADASTRO -->
<div class="modal fade" id="modalForm" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="id">
        <div class="modal-header">
          <h5 class="modal-title">Cadastro de Menu</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Título *</label>
              <input type="text" name="titulo" id="titulo" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Ícone</label>
              <input type="text" name="icone" id="icone" class="form-control" placeholder="🧩 ou fa fa-cog">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Link</label>
            <input type="text" name="link" id="link" class="form-control" placeholder="../modulo/pagina.php">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Menu Pai</label>
              <select name="parent_id" id="parent_id" class="form-select">
                <option value="">(Nenhum)</option>
                <?php foreach($menus as $p): if(!$p['parent_id']): ?>
                  <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['titulo']) ?></option>
                <?php endif; endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">Ordem</label>
              <input type="number" name="ordem" id="ordem" class="form-control" value="0">
            </div>
            <div class="col-md-3 mb-3 d-flex align-items-center">
              <div class="form-check mt-4">
                <input type="checkbox" class="form-check-input" name="ativo" id="ativo" checked>
                <label for="ativo" class="form-check-label">Ativo</label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label d-block">Perfis Permitidos</label>
            <?php foreach(['admin','gestor','usuario'] as $perfil): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="perfis_permitidos[]" value="<?= $perfil ?>" id="chk_<?= $perfil ?>">
                <label class="form-check-label" for="chk_<?= $perfil ?>"><?= ucfirst($perfil) ?></label>
              </div>
            <?php endforeach; ?>
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

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>
let modal;
document.addEventListener('DOMContentLoaded', () => {
  modal = new bootstrap.Modal(document.getElementById('modalForm'));

  // Função recursiva de inicialização de Sortable hierárquico
  function initSortable(el) {
    new Sortable(el, {
      group: 'menus',
      handle: '.handle',
      animation: 150,
      fallbackOnBody: true,
      swapThreshold: 0.65,
      ghostClass: 'placeholder',
      onEnd: salvarEstrutura
    });
    el.querySelectorAll('.sortable').forEach(initSortable);
  }

  document.querySelectorAll('.sortable').forEach(initSortable);
});

// Envia a estrutura em JSON para o PHP
function salvarEstrutura() {
  const estrutura = montarJSON(document.querySelector('#menuTree > .sortable'));
  $.post("menus.php", { estrutura_json: JSON.stringify(estrutura) }, function(resp) {
    if (resp.ok) {
      $(".card-body").prepend('<div class="alert alert-success py-1 mb-2">✔️ Hierarquia atualizada</div>');
      setTimeout(() => $(".alert").fadeOut(), 1500);
    }
  }, 'json');
}

// Converte a estrutura DOM em JSON recursivo
function montarJSON(ul) {
  const data = [];
  ul.querySelectorAll(':scope > li').forEach(li => {
    const obj = { id: li.dataset.id };
    const child = li.querySelector(':scope > ul');
    if (child && child.children.length > 0) {
      obj.children = montarJSON(child);
    }
    data.push(obj);
  });
  return data;
}

function abrirModal() {
  document.querySelectorAll('#modalForm input, #modalForm select').forEach(el=>{
    if(el.type==='checkbox') el.checked=false; else el.value='';
  });
  document.getElementById('ativo').checked=true;
  modal.show();
}

function editar(d){
  for(let k in d){
    const el=document.getElementById(k);
    if(el){
      if(el.type==='checkbox') el.checked=(d[k]==1);
      else el.value=d[k];
    }
  }
  document.querySelectorAll('[name="perfis_permitidos[]"]').forEach(chk=>{
    chk.checked = (d.perfis_permitidos && d.perfis_permitidos.includes(chk.value));
  });
  modal.show();
}
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
