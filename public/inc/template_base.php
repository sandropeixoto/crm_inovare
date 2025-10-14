<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();

$user = current_user() ?? ['nome' => 'Convidado', 'perfil' => 'visualizador'];
$config = app_config();
$menus = load_menu_tree($user['perfil'] ?? 'visualizador');

$current_url = $_SERVER['REQUEST_URI'] ?? '';
$base_path = APP_BASE_PATH;
$page_title = $page_title ?? 'Painel Inovare';
$breadcrumb = $breadcrumb ?? '';

function resolve_menu_link(?string $link, string $base_path): string
{
    if (!$link || $link === '#') {
        return '#';
    }

    if (preg_match('/^https?:/i', $link)) {
        return $link;
    }

    if ($link[0] === '/') {
        return $link;
    }

    return $base_path . ltrim($link, '/');
}

function menu_is_active(string $link, string $current_url): bool
{
    if (!$link || $link === '#') {
        return false;
    }

    $path = parse_url($link, PHP_URL_PATH) ?? $link;
    $basename = basename($path);
    return $basename && strpos($current_url, $basename) !== false;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= e($page_title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --sidebar-width: 260px;
      --primary-color: #2563eb;
    }
    body {
      margin:0;
      background:#f6f8fa;
      font-family: "Inter", "Segoe UI", sans-serif;
      color:#1f2937;
    }
    .layout {
      display:flex;
      min-height:100vh;
    }
    .sidebar {
      width:var(--sidebar-width);
      background:#111827;
      color:#f9fafb;
      position:fixed;
      inset:0 auto 0 0;
      padding:16px 0;
      overflow-y:auto;
      transition:transform .3s ease-in-out;
      z-index:1040;
    }
    .sidebar .brand {
      padding:0 24px 16px 24px;
      border-bottom:1px solid rgba(255,255,255,.08);
      text-align:center;
    }
    .sidebar .brand img {
      max-height:56px;
      max-width:100%;
      object-fit:contain;
    }
    .sidebar .brand .title {
      font-weight:600;
      margin-top:12px;
      font-size:1rem;
    }
    .menu-group {
      display:flex;
      flex-direction:column;
      padding:8px 12px;
      gap:4px;
    }
    .menu-link {
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px 16px;
      border-radius:10px;
      color:inherit;
      text-decoration:none;
      font-size:0.95rem;
      transition:background .2s ease, color .2s ease;
    }
    .menu-link .icon { width:20px; text-align:center; }
    .menu-link:hover,
    .menu-link.active {
      background:rgba(37, 99, 235, 0.2);
      color:#93c5fd;
    }
    .menu-link.disabled { opacity:.5; pointer-events:none; }
    .submenu {
      margin-left:10px;
      border-left:1px solid rgba(255,255,255,.08);
      padding-left:10px;
      display:flex;
      flex-direction:column;
      gap:4px;
    }
    .content {
      flex-grow:1;
      margin-left:var(--sidebar-width);
      padding:28px 32px;
      transition:margin-left .3s ease-in-out;
    }
    .topbar {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:24px;
    }
    .topbar h1 {
      font-size:1.5rem;
      margin:0;
    }
    .breadcrumb small { color:#6b7280; }
    .user-pill {
      display:flex;
      align-items:center;
      gap:12px;
      background:#fff;
      border-radius:999px;
      padding:6px 16px;
      box-shadow:0 1px 4px rgba(15,23,42,.15);
    }
    .logout-link { color:#ef4444; text-decoration:none; font-weight:500; }
    .logout-link:hover { text-decoration:underline; }
    @media (max-width: 992px) {
      :root { --sidebar-width: 220px; }
      .sidebar { transform:translateX(-100%); }
      .sidebar.open { transform:translateX(0); }
      .content { margin-left:0; padding:24px 18px; }
      .topbar { flex-direction:column; align-items:flex-start; gap:12px; }
      .menu-toggle { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px; background:#111827; color:#f9fafb; text-decoration:none; }
    }
    @media (min-width: 993px) {
      .menu-toggle { display:none; }
    }
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <?php if (!empty($config['logotipo_url'])): ?>
          <img src="<?= e($config['logotipo_url']) ?>" alt="Logo">
        <?php endif; ?>
        <div class="title"><?= e($config['empresa_nome'] ?? 'CRM Inovare') ?></div>
      </div>
      <nav class="menu-group">
        <?php foreach ($menus as $menu): ?>
          <?php
            $link = resolve_menu_link($menu['link'] ?? '#', $base_path);
            $isActive = menu_is_active($link, $current_url);
            $hasChildren = !empty($menu['filhos']);
          ?>
          <a href="<?= $hasChildren ? '#' : e($link) ?>"
             class="menu-link <?= $isActive ? 'active' : '' ?>"
             <?= $hasChildren ? 'data-bs-toggle="collapse" data-bs-target="#menu-' . (int)$menu['id'] . '" role="button" aria-expanded="false"' : '' ?>>
            <span class="icon"><?= e($menu['icone'] ?? '📁') ?></span>
            <span><?= e($menu['titulo'] ?? 'Módulo') ?></span>
            <?php if ($hasChildren): ?>
              <span class="ms-auto">▾</span>
            <?php endif; ?>
          </a>
          <?php if ($hasChildren): ?>
            <div class="collapse submenu" id="menu-<?= (int)$menu['id'] ?>">
              <?php foreach ($menu['filhos'] as $child): ?>
                <?php $childLink = resolve_menu_link($child['link'] ?? '#', $base_path); ?>
                <a href="<?= e($childLink) ?>" class="menu-link <?= menu_is_active($childLink, $current_url) ? 'active' : '' ?>">
                  <span class="icon"><?= e($child['icone'] ?? '•') ?></span>
                  <span><?= e($child['titulo'] ?? 'Opção') ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <div class="mt-auto px-4 pt-4 text-center small" style="color:rgba(249,250,251,.65);">
        <div class="fw-semibold">Logado como</div>
        <div><?= e($user['nome'] ?? 'Usuário') ?></div>
        <div class="mt-2">
          <a class="logout-link" href="<?= e(app_url('logout.php')) ?>">Sair</a>
        </div>
      </div>
    </aside>

    <main class="content">
      <a href="#sidebar" class="menu-toggle" id="menuToggle">☰ Menu</a>
      <div class="topbar">
        <div>
          <h1><?= e($page_title) ?></h1>
          <?php if ($breadcrumb): ?>
            <div class="breadcrumb"><small><?= e($breadcrumb) ?></small></div>
          <?php endif; ?>
        </div>
        <div class="user-pill">
          <div>
            <div class="small text-muted">Perfil</div>
            <strong><?= e(ucfirst($user['perfil'] ?? 'Usuário')) ?></strong>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('usuarios/perfil.php')) ?>">Meu Perfil</a>
        </div>
      </div>

      <?= $content ?? '' ?>

      <footer class="mt-5 pt-4 small text-muted text-center">
        <?= e($config['rodape'] ?? 'CRM Inovare') ?>
      </footer>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('menuToggle');
    if (toggle) {
      toggle.addEventListener('click', function (event) {
        event.preventDefault();
        sidebar.classList.toggle('open');
      });
    }
  </script>
</body>
</html>
