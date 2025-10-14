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

function str_inovare_starts_with($haystack, $needle) {
  if ($needle === '') {
    return true;
  }
  return substr($haystack, 0, strlen($needle)) === $needle;
}

function str_inovare_contains($haystack, $needle) {
  if ($needle === '') {
    return true;
  }
  return strpos($haystack, $needle) !== false;
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
    :root { --sidebar-width:260px; --sidebar-width-collapsed:72px; }
    body { margin:0; background:#f6f8fa; font-family: "Segoe UI", sans-serif; }
    .layout { display:flex; min-height:100vh; transition:margin .3s ease; }
    .sidebar {
      width:var(--sidebar-width); background:#212529; color:white; position:fixed;
      top:0; left:0; bottom:0; overflow-y:auto; padding:10px 0; transition:width .3s ease;
    }
    .sidebar a,
    .sidebar .accordion-button {
      display:flex; align-items:center; gap:10px; color:white; padding:8px 18px;
      text-decoration:none; border-radius:5px; margin:2px 10px; font-size:0.94rem;
    }
    .sidebar a { border:0; background:none; }
    .sidebar a:hover, .sidebar a.active { background:#343a40; }
    .accordion-button { color:white; background:none!important; font-size:0.95rem; box-shadow:none!important; }
    .accordion-button:not(.collapsed) { background:#343a40!important; }
    .accordion-button::after { filter:invert(1); }
    .accordion-item { background:none; }
    .toggle-sidebar-btn { width:calc(100% - 20px); margin:0 auto 10px; }
    .toggle-sidebar-btn .menu-icon { font-size:1.1rem; }
    .sidebar .menu-text { flex:1; }
    .company-info { padding:0 18px 12px; }
    .content {
      flex-grow:1; margin-left:var(--sidebar-width); padding:25px; transition:margin .3s ease;
    }
    .disabled { pointer-events:none; opacity:.6; }

    body.sidebar-collapsed .sidebar { width:var(--sidebar-width-collapsed); }
    body.sidebar-collapsed .content { margin-left:var(--sidebar-width-collapsed); }
    body.sidebar-collapsed .sidebar .menu-text,
    body.sidebar-collapsed .sidebar .company-info,
    body.sidebar-collapsed .sidebar .sidebar-footer { display:none!important; }
    body.sidebar-collapsed .sidebar a,
    body.sidebar-collapsed .sidebar .accordion-button { justify-content:center; padding:10px 0; margin:2px 6px; }
    body.sidebar-collapsed .sidebar .accordion-button::after { display:none; }
    body.sidebar-collapsed .sidebar .accordion-collapse { display:none!important; }
    body.sidebar-collapsed .toggle-sidebar-btn { justify-content:center; }
    body.sidebar-collapsed .toggle-sidebar-btn .menu-text { display:none; }
  </style>
</head>
<body>
<div class="layout">
  <div class="sidebar">
    <div class="d-flex justify-content-end">
      <button id="toggleSidebar" type="button" class="btn btn-outline-light btn-sm toggle-sidebar-btn d-flex align-items-center gap-2"
              aria-expanded="true" aria-label="Recolher menu lateral"
              data-label-expandir="Expandir menu" data-label-recolher="Recolher menu">
        <span class="menu-icon">&#9776;</span>
        <span class="menu-text">Recolher menu</span>
      </button>
    </div>
    <div class="text-center mb-3 border-bottom pb-2 company-info">
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
          $link_original = $menu['link'] ?? '';
          $link_corrigido = $link_original ? (str_inovare_starts_with($link_original, '/') ? $link_original : $base_path . ltrim($link_original, './')) : '#';
          $basename_link = $link_original ? basename($link_original) : '';
          $ativo = ($basename_link && str_inovare_contains($current_url, $basename_link)) ? 'active' : '';
        ?>
        <a href="<?= $permitido ? $link_corrigido : '#' ?>" class="<?= $classeDesativado ?> <?= $ativo ?>">
          <?= $menu['icone'] ?>
          <span class="menu-text"><?= $menu['titulo'] ?></span>
        </a>
      <?php else: ?>
        <div class="accordion mb-1" id="menu-<?= $menu['id'] ?>">
          <div class="accordion-item bg-transparent border-0">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed shadow-none p-2 ps-3"
                      type="button" data-bs-toggle="collapse"
                      data-bs-target="#submenu-<?= $menu['id'] ?>">
                <?= $menu['icone'] ?>
                <span class="menu-text"><?= $menu['titulo'] ?></span>
              </button>
            </h2>
            <div id="submenu-<?= $menu['id'] ?>" class="accordion-collapse collapse">
              <div class="accordion-body p-0">
                <?php foreach ($menu['filhos'] as $sub): 
                  $permitidoSub = menu_permitido($sub, $perfil_atual);
                  $classeDesativadoSub = $permitidoSub ? '' : 'disabled opacity-50';
                  $link_original_sub = $sub['link'] ?? '';
                  $link_corrigido_sub = $link_original_sub ? (str_inovare_starts_with($link_original_sub, '/') ? $link_original_sub : $base_path . ltrim($link_original_sub, './')) : '#';
                  $basename_sub = $link_original_sub ? basename($link_original_sub) : '';
                  $ativo_sub = ($basename_sub && str_inovare_contains($current_url, $basename_sub)) ? 'active' : '';
                ?>
                  <a href="<?= $permitidoSub ? $link_corrigido_sub : '#' ?>"
                     class="ps-5 py-2 text-white <?= $classeDesativadoSub ?> <?= $ativo_sub ?>">
                     <?= $sub['icone'] ?>
                     <span class="menu-text"><?= $sub['titulo'] ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="mt-auto border-top small text-center pt-3 sidebar-footer">
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
<script>
  (function() {
    const body = document.body;
    const toggleBtn = document.getElementById('toggleSidebar');
    if (!toggleBtn) return;

    const canUseStorage = (() => {
      try {
        const testKey = '__sidebar_test__';
        localStorage.setItem(testKey, '1');
        localStorage.removeItem(testKey);
        return true;
      } catch (err) {
        return false;
      }
    })();

    const setCollapsed = (collapsed) => {
      body.classList.toggle('sidebar-collapsed', collapsed);
      toggleBtn.setAttribute('aria-expanded', String(!collapsed));
      const labelExpand = toggleBtn.dataset.labelExpandir || 'Expandir menu';
      const labelCollapse = toggleBtn.dataset.labelRecolher || 'Recolher menu';
      const textEl = toggleBtn.querySelector('.menu-text');
      if (textEl) {
        textEl.textContent = collapsed ? labelExpand : labelCollapse;
      }
      toggleBtn.setAttribute('aria-label', collapsed ? labelExpand : labelCollapse);
      if (canUseStorage) {
        localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
      }
      if (collapsed && typeof bootstrap !== 'undefined') {
        document.querySelectorAll('.sidebar .accordion-collapse.show')
          .forEach(el => new bootstrap.Collapse(el, { toggle: true }));
      }
    };

    const stored = canUseStorage && localStorage.getItem('sidebarCollapsed') === '1';
    if (stored) {
      setCollapsed(true);
    }

    toggleBtn.addEventListener('click', () => {
      const collapsed = !body.classList.contains('sidebar-collapsed');
      setCollapsed(collapsed);
    });
  })();
</script>
</body>
</html>
