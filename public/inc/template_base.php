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

function normalize_url_path(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    if ($path === '') {
        return '';
    }

    $path = '/' . ltrim($path, '/');
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

function menu_is_active(string $link, string $current_url): bool
{
    if (!$link || $link === '#') {
        return false;
    }

    $linkPath = normalize_url_path($link);
    if ($linkPath === '') {
        return false;
    }

    $currentPath = normalize_url_path($current_url);

    if ($linkPath === $currentPath) {
        return true;
    }

    if ($linkPath !== '/' && str_starts_with($currentPath, $linkPath . '/')) {
        return true;
    }

    return false;
}

function menu_has_active_child(array $children, string $current_url, string $base_path): bool
{
    foreach ($children as $child) {
        $childLink = resolve_menu_link($child['link'] ?? '#', $base_path);
        if (menu_is_active($childLink, $current_url)) {
            return true;
        }

        if (!empty($child['filhos']) && menu_has_active_child($child['filhos'], $current_url, $base_path)) {
            return true;
        }
    }

    return false;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> | <?= e($config['empresa_nome'] ?? 'CRM Inovare') ?></title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- AdminLTE -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

  <style>
    .brand-link {
      border-bottom: 1px solid #4b5563;
    }
    .brand-image {
      max-height: 45px;
      width: auto;
    }
    .sidebar-dark-primary .nav-sidebar>.nav-item>.nav-link.active {
      background-color: #007bff;
      color: #fff;
    }
    .nav-sidebar .nav-link p {
      white-space: normal;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?= e(app_url('index.php')) ?>" class="nav-link">Dashboard</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-user"></i> <?= e($user['nome']) ?>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <a href="<?= e(app_url('usuarios/perfil.php')) ?>" class="dropdown-item">
            <i class="fas fa-user mr-2"></i> Meu Perfil
          </a>
          <div class="dropdown-divider"></div>
          <a href="<?= e(app_url('logout.php')) ?>" class="dropdown-item dropdown-footer text-danger">
            <i class="fas fa-sign-out-alt mr-2"></i> Sair
          </a>
        </div>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?= e(app_url('index.php')) ?>" class="brand-link">
      <?php if (!empty($config['logotipo_url'])): ?>
        <img src="<?= e($config['logotipo_url']) ?>" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <?php else: ?>
        <span class="brand-image"><i class="fas fa-hospital-alt fa-2x text-white"></i></span>
      <?php endif; ?>
      <span class="brand-text font-weight-light"><?= e($config['empresa_nome'] ?? 'CRM Inovare') ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <i class="fas fa-user-circle fa-2x text-white"></i>
        </div>
        <div class="info">
          <a href="<?= e(app_url('usuarios/perfil.php')) ?>" class="d-block"><?= e($user['nome']) ?></a>
          <small class="text-muted"><?= e(ucfirst($user['perfil'] ?? 'Usuário')) ?></small>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <?php foreach ($menus as $menu): ?>
            <?php
              $link = resolve_menu_link($menu['link'] ?? '#', $base_path);
              $hasChildren = !empty($menu['filhos']);
              $isActive = menu_is_active($link, $current_url);

              if (!$isActive && $hasChildren) {
                  $isActive = menu_has_active_child($menu['filhos'], $current_url, $base_path);
              }

              $itemClasses = ['nav-item'];
              if ($hasChildren) {
                  $itemClasses[] = 'has-treeview';
                  if ($isActive) {
                      $itemClasses[] = 'menu-open';
                      $itemClasses[] = 'menu-is-opening';
                  }
              }
            ?>
            <li class="<?= implode(' ', $itemClasses) ?>">
              <a href="<?= $hasChildren ? '#' : e($link) ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                <i class="nav-icon"><?= e($menu['icone'] ?? '•') ?></i>
                <p>
                  <?= e($menu['titulo'] ?? 'Módulo') ?>
                  <?php if ($hasChildren): ?>
                    <i class="right fas fa-angle-left"></i>
                  <?php endif; ?>
                </p>
              </a>
              <?php if ($hasChildren): ?>
                <ul class="nav nav-treeview" <?= $isActive ? 'style="display:block;"' : '' ?>>
                  <?php foreach ($menu['filhos'] as $child): ?>
                    <?php
                      $childLink = resolve_menu_link($child['link'] ?? '#', $base_path);
                      $childHasChildren = !empty($child['filhos']);
                      $childActive = menu_is_active($childLink, $current_url);

                      if (!$childActive && $childHasChildren) {
                          $childActive = menu_has_active_child($child['filhos'], $current_url, $base_path);
                      }
                    ?>
                    <li class="nav-item <?= $childHasChildren ? 'has-treeview' : '' ?>">
                      <a href="<?= e($childLink) ?>" class="nav-link <?= $childActive ? 'active' : '' ?>">
                        <i class="far fa-circle nav-icon"></i>
                        <p><?= e($child['titulo'] ?? 'Opção') ?></p>
                      </a>
                      <?php if ($childHasChildren): ?>
                        <ul class="nav nav-treeview" <?= $childActive ? 'style="display:block;"' : '' ?>>
                          <?php foreach ($child['filhos'] as $grandchild): ?>
                            <?php
                              $grandchildLink = resolve_menu_link($grandchild['link'] ?? '#', $base_path);
                              $grandchildActive = menu_is_active($grandchildLink, $current_url);
                            ?>
                            <li class="nav-item">
                              <a href="<?= e($grandchildLink) ?>" class="nav-link <?= $grandchildActive ? 'active' : '' ?>">
                                <i class="far fa-dot-circle nav-icon"></i>
                                <p><?= e($grandchild['titulo'] ?? 'Opção') ?></p>
                              </a>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0"><?= e($page_title) ?></h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <?php if ($breadcrumb): ?>
                <?php foreach (explode('>', $breadcrumb) as $crumb): ?>
                  <li class="breadcrumb-item"><?= e(trim($crumb)) ?></li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ol>
          </div>
        </div>
      </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <?= $content ?? '' ?>
      </div>
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <footer class="main-footer">
    <strong><?= e($config['rodape'] ?? 'CRM Inovare') ?></strong>
    <div class="float-right d-none d-sm-inline-block">
      <b>Versão</b> 1.0.0
    </div>
  </footer>
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
