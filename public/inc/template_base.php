<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/../../config/db.php';

// Usuário logado
$user = $_SESSION['user'] ?? ['nome'=>'Convidado','perfil'=>'usuario'];
$perfil_atual = $user['perfil'] ?? 'usuario';

// Configurações da empresa
$config = run_query("SELECT * FROM configuracoes WHERE ativo=1 LIMIT 1")[0] ?? [
  'empresa_nome' => 'CRM Inovare',
  'logotipo_url' => '/inovare/public/assets/logo.png'
];

// Carrega menus ativos
$menus = run_query("SELECT * FROM menus WHERE ativo=1 ORDER BY parent_id, ordem, titulo");
$menu_tree = [];
foreach ($menus as $m) {
  if ($m['parent_id']) {
    $menu_tree[$m['parent_id']]['filhos'][] = $m;
  } else {
    $menu_tree[$m['id']] = $m;
  }
}

function menu_permitido($menu, $perfil) {
  $permitidos = array_map('trim', explode(',', $menu['perfis_permitidos'] ?? ''));
  return in_array($perfil, $permitidos);
}

$current_url = $_SERVER['REQUEST_URI'];
$base_path = '/inovare/public/'; // caminho fixo para todos os links
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($page_title ?? 'Painel Inovare') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { margin:0; background:#f6f8fa; font-family: "Segoe UI", sans-serif; }
    .layout { display:flex; min-height:100vh; }
    .sidebar {
      width:260px; background:#212529; color:white; position:fixed;
      top:0; left:0; bottom:0; overflow-y:auto; padding:10px 0;
    }
    .sidebar a {
      display:block; color:white; padding:8px 18px; text-decoration:none;
      border-radius:5px; margin:2px 0; font-size:0.94rem;
    }
    .sidebar a:hover, .sidebar a.active { background:#343a40; }
    .accordion-button { color:white; background:none!important; font-size:0.95rem; }
    .accordion-button:not(.collapsed) { background:#343a40!important; }
    .content {
      flex-grow:1; margin-left:260px; padding:25px; transition:margin .3s;
    }
    .disabled { pointer-events:none; opacity:.6; }
  </style>
</head>
<body>
<div class="layout">
  <div class="sidebar">
    <div class="text-center mb-3 border-bottom pb-2">
      <img src="<?= htmlspecialchars($config['logotipo_url']) ?>" style="max-height:55px;">
      <div class="fw-bold mt-2"><?= htmlspecialchars($config['empresa_nome']) ?></div>
    </div>

    <?php foreach ($menu_tree as $menu): ?>
      <?php
        $permitido = menu_permitido($menu, $perfil_atual);
        $temFilhos = isset($menu['filhos']);
        $classeDesativado = $permitido ? '' : 'disabled opacity-50';
      ?>

      <?php if (!$temFilhos): ?>
        <?php 
          $link_corrigido = $menu['link'] ? (str_starts_with($menu['link'], '/') ? $menu['link'] : $base_path . ltrim($menu['link'], './')) : '#';
        ?>
        <a href="<?= $permitido ? $link_corrigido : '#' ?>" class="<?= $classeDesativado ?> <?= str_contains($current_url, basename($menu['link']))?'active':'' ?>">
          <?= $menu['icone'] ?> <?= $menu['titulo'] ?>
        </a>
      <?php else: ?>
        <div class="accordion mb-1" id="menu-<?= $menu['id'] ?>">
          <div class="accordion-item bg-transparent border-0">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed shadow-none p-2 ps-3"
                      type="button" data-bs-toggle="collapse"
                      data-bs-target="#submenu-<?= $menu['id'] ?>">
                <?= $menu['icone'] ?> <?= $menu['titulo'] ?>
              </button>
            </h2>
            <div id="submenu-<?= $menu['id'] ?>" class="accordion-collapse collapse">
              <div class="accordion-body p-0">
                <?php foreach ($menu['filhos'] as $sub): 
                  $permitidoSub = menu_permitido($sub, $perfil_atual);
                  $classeDesativadoSub = $permitidoSub ? '' : 'disabled opacity-50';
                  $link_corrigido_sub = $sub['link'] ? (str_starts_with($sub['link'], '/') ? $sub['link'] : $base_path . ltrim($sub['link'], './')) : '#';
                ?>
                  <a href="<?= $permitidoSub ? $link_corrigido_sub : '#' ?>"
                     class="ps-5 py-2 d-block text-white <?= $classeDesativadoSub ?> <?= str_contains($current_url, basename($sub['link']))?'active':'' ?>">
                     <?= $sub['icone'] ?> <?= $sub['titulo'] ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="mt-auto border-top small text-center pt-3">
      Logado como:<br>
      <strong><?= htmlspecialchars($user['nome']) ?></strong><br>
      <a href="<?= $base_path ?>logout.php" class="text-warning text-decoration-none">Sair</a>
    </div>
  </div>

  <!-- CONTEÚDO -->
  <div class="content">
    <?php if (!empty($breadcrumb)): ?>
      <div class="small text-muted mb-2"><?= $breadcrumb ?></div>
    <?php endif; ?>
    <?= $content ?? '' ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
