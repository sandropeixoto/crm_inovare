<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$tabela = $_GET['tabela'] ?? '';
$titulo = $_GET['titulo'] ?? ucfirst($tabela);
if (!$tabela) exit('Tabela não especificada.');

$page_title = "Gerenciar {$titulo}";
$breadcrumb = "Auxiliares > {$titulo}";

// Campos da tabela
$cols = run_query("SHOW COLUMNS FROM {$tabela}");
$chaves = array_column($cols, 'Field');

// Filtros
$busca = trim($_GET['busca'] ?? '');
$filtro_ativo = $_GET['ativo'] ?? '';
$where = [];
$params = [];

if ($busca) {
  $like = [];
  foreach ($cols as $c) {
    if (preg_match('/char|text/i', $c['Type'])) $like[] = "{$c['Field']} LIKE ?";
  }
  if ($like) {
    $where[] = '(' . implode(' OR ', $like) . ')';
    foreach ($like as $_) $params[] = "%{$busca}%";
  }
}
if ($filtro_ativo !== '' && in_array('ativo', $chaves)) {
  $where[] = "ativo=?";
  $params[] = (int)$filtro_ativo;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Paginação
$por_pagina = max(10, (int)($_GET['pp'] ?? 10));
$pagina_atual = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pagina_atual - 1) * $por_pagina;

// Contagem total
$total_registros = run_query("SELECT COUNT(*) AS total FROM {$tabela} {$where_sql}", $params)[0]['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));

// CRUD
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $isUpdate = ($acao === 'salvar' && $id > 0);
    $dados = [];
    foreach ($cols as $c) {
      $campo = $c['Field'];
      if (in_array($campo, ['id', 'criado_em', 'atualizado_em'])) continue;
      $tipo = strtolower($c['Type']);
      $nullable = strtoupper($c['Null'] ?? '') === 'YES';

      // Valor vindo do POST
      if (preg_match('/tinyint|bit/i', $tipo)) {
        // Campos booleanos via checkbox
        $valor = isset($_POST[$campo]) ? 1 : 0;
      } else {
        $valor = $_POST[$campo] ?? null;
      }

      $isNumeric = (bool)preg_match('/int|decimal|float|double/i', $tipo);
      $isDateLike = (bool)preg_match('/date|datetime|timestamp/i', $tipo);

      // Normaliza campos vazios para evitar erros com modos STRICT do MySQL
      if ($valor === '') {
        if ($isNumeric || $isDateLike) {
          if ($nullable) {
            // Persistir NULL quando permitido
            $valor = null;
          } else if ($isUpdate) {
            // Em atualização, não sobrescrever com valor inválido
            continue;
          } else if ($isNumeric) {
            // Em inserção: fallback não-nocivo para numéricos
            $valor = 0;
          }
        }
      }

      $dados[$campo] = $valor;
    }

  if ($acao === 'salvar') {
      if ($id > 0) {
        if (!empty($dados)) {
          $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($dados)));
          run_query("UPDATE {$tabela} SET {$sets} WHERE id=?", array_merge(array_values($dados), [$id]));
          log_user_action($_SESSION['user']['id'], "Atualizou registro em {$tabela}", $tabela, $id, null, $dados);
        }
      } else {
        if (!empty($dados)) {
          $colsStr = implode(',', array_keys($dados));
          $valsStr = implode(',', array_fill(0, count($dados), '?'));
          run_query("INSERT INTO {$tabela} ({$colsStr}) VALUES ({$valsStr})", array_values($dados));
          $id = pdo()->lastInsertId();
          log_user_action($_SESSION['user']['id'], "Criou registro em {$tabela}", $tabela, $id, null, $dados);
        }
      }
    } elseif ($acao === 'excluir' && $id > 0) {
    run_query("DELETE FROM {$tabela} WHERE id=?", [$id]);
    log_user_action($_SESSION['user']['id'], "Excluiu registro em {$tabela}", $tabela, $id);
  }
}

// Consulta principal
$sql = "SELECT * FROM {$tabela} {$where_sql} ORDER BY id DESC LIMIT {$por_pagina} OFFSET {$offset}";
$dados = run_query($sql, $params);

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">

    <!-- Cabeçalho e Filtros -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
      <h6 class="fw-bold text-primary mb-2"><?= htmlspecialchars($titulo) ?></h6>
      <button class="btn btn-sm btn-success mb-2" onclick="abrirModal()">+ Novo Registro</button>
    </div>

    <form class="row g-2 mb-3" method="GET">
      <input type="hidden" name="tabela" value="<?= htmlspecialchars($tabela) ?>">
      <input type="hidden" name="titulo" value="<?= htmlspecialchars($titulo) ?>">
      <div class="col-md-4">
        <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="form-control" placeholder="Buscar...">
      </div>
      <?php if (in_array('ativo', $chaves)): ?>
      <div class="col-md-3">
        <select name="ativo" class="form-select">
          <option value="">Todos</option>
          <option value="1" <?= $filtro_ativo==='1'?'selected':'' ?>>Ativos</option>
          <option value="0" <?= $filtro_ativo==='0'?'selected':'' ?>>Inativos</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <select name="pp" class="form-select">
          <?php foreach([10,25,50,100] as $opt): ?>
          <option value="<?= $opt ?>" <?= $por_pagina==$opt?'selected':'' ?>><?= $opt ?>/pág.</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100">Filtrar</button>
      </div>
      <div class="col-md-12 text-end text-muted small">
        Total: <?= $total_registros ?> registro<?= $total_registros>1?'s':'' ?> • Página <?= $pagina_atual ?> de <?= $total_paginas ?>
      </div>
    </form>

    <!-- Tabela -->
    <div class="table-responsive">
      <table class="table table-striped table-sm align-middle">
        <thead class="table-light">
          <tr>
            <?php foreach($chaves as $c): ?><th><?= ucfirst($c) ?></th><?php endforeach; ?>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$dados): ?>
          <tr><td colspan="<?= count($chaves)+1 ?>" class="text-center py-3">Nenhum registro encontrado.</td></tr>
        <?php else: foreach($dados as $d): ?>
          <tr>
              <?php foreach($chaves as $c): ?>
                <td><?= htmlspecialchars((string)($d[$c] ?? '')) ?></td>
              <?php endforeach; ?>
            <td class="text-end">
              <button class="btn btn-sm btn-primary" onclick='editar(<?= json_encode($d) ?>)'>Editar</button>
              <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este registro?')">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <button class="btn btn-sm btn-danger">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <nav>
      <ul class="pagination justify-content-center mt-3 mb-0">
        <?php
        $base_url = '?' . http_build_query(array_merge($_GET, ['p'=>1]));
        $disabled = $pagina_atual<=1 ? 'disabled' : '';
        echo "<li class='page-item {$disabled}'><a class='page-link' href='?".http_build_query(array_merge($_GET,['p'=>1]))."'>&laquo; Primeiro</a></li>";
        echo "<li class='page-item {$disabled}'><a class='page-link' href='?".http_build_query(array_merge($_GET,['p'=>max(1,$pagina_atual-1)]))."'>‹ Anterior</a></li>";
        $ini = max(1, $pagina_atual - 2);
        $fim = min($total_paginas, $pagina_atual + 2);
        for($i=$ini;$i<=$fim;$i++){
          $active = $i==$pagina_atual ? 'active' : '';
          echo "<li class='page-item {$active}'><a class='page-link' href='?".http_build_query(array_merge($_GET,['p'=>$i]))."'>$i</a></li>";
        }
        $disabled = $pagina_atual>=$total_paginas ? 'disabled' : '';
        echo "<li class='page-item {$disabled}'><a class='page-link' href='?".http_build_query(array_merge($_GET,['p'=>min($total_paginas,$pagina_atual+1)]))."'>Próximo ›</a></li>";
        echo "<li class='page-item {$disabled}'><a class='page-link' href='?".http_build_query(array_merge($_GET,['p'=>$total_paginas]))."'>Último &raquo;</a></li>";
        ?>
      </ul>
    </nav>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalForm" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="id">
        <div class="modal-header">
          <h5 class="modal-title">Cadastro de <?= htmlspecialchars($titulo) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php foreach($cols as $c): 
            $campo = $c['Field'];
            $tipo = strtolower($c['Type']);
            if (in_array($campo, ['id','criado_em','atualizado_em'])) continue;
          ?>
            <div class="mb-3">
              <label class="form-label"><?= ucfirst($campo) ?></label>
              <?php
              if (preg_match('/tinyint|bit/i', $tipo)): ?>
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="<?= $campo ?>" id="<?= $campo ?>" value="1">
                  <label for="<?= $campo ?>" class="form-check-label">Ativo</label>
                </div>
              <?php elseif (preg_match('/int/i', $tipo)): ?>
                <input type="number" name="<?= $campo ?>" id="<?= $campo ?>" class="form-control">
              <?php elseif (preg_match('/decimal|float|double/i', $tipo)): ?>
                <input type="number" step="0.01" name="<?= $campo ?>" id="<?= $campo ?>" class="form-control">
              <?php elseif (preg_match('/date/i', $tipo)): ?>
                <input type="date" name="<?= $campo ?>" id="<?= $campo ?>" class="form-control">
              <?php elseif (preg_match('/datetime|timestamp/i', $tipo)): ?>
                <input type="datetime-local" name="<?= $campo ?>" id="<?= $campo ?>" class="form-control">
              <?php elseif (preg_match('/text/i', $tipo)): ?>
                <textarea name="<?= $campo ?>" id="<?= $campo ?>" rows="3" class="form-control"></textarea>
              <?php elseif (preg_match('/char|varchar/i', $tipo) && str_contains($campo,'email')): ?>
                <input type="email" name="<?= $campo ?>" id="<?= $campo ?>" class="form-control">
              <?php elseif (preg_match('/char|varchar/i', $tipo) && str_contains($campo,'url')): ?>
                <input type="url" name="<?= $campo ?>" id="<?= $campo ?>" class="form-control">
              <?php elseif (preg_match('/char|varchar/i', $tipo) && str_contains($campo,'cor')): ?>
                <input type="color" name="<?= $campo ?>" id="<?= $campo ?>" class="form-control form-control-color" value="#0d6efd">
              <?php else: ?>
                <input type="text" name="<?= $campo ?>" id="<?= $campo ?>" class="form-control">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
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
document.addEventListener('DOMContentLoaded',()=>{ modal = new bootstrap.Modal(document.getElementById('modalForm')); });
function abrirModal(){
  document.querySelectorAll('#modalForm input, #modalForm textarea').forEach(e=>{
    if(e.type==='checkbox') e.checked=false;
    else if(e.type!=='hidden') e.value='';
  });
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
  modal.show();
}
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
