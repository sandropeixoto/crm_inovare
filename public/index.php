<?php
require_once __DIR__ . '/../config/db.php';
ensure_session_security();

$user = current_user();
$page_title = 'Dashboard';
$breadcrumb = 'Início > Dashboard';

$totalClientes = (int)(run_query("SELECT COUNT(*) AS c FROM clientes")[0]['c'] ?? 0);
$totalPropostas = (int)(run_query("SELECT COUNT(*) AS c FROM propostas")[0]['c'] ?? 0);
$totalPacotes = (int)(run_query("SELECT COUNT(*) AS c FROM pacotes WHERE ativo=TRUE")[0]['c'] ?? 0);
$totalUsuarios = (int)(run_query("SELECT COUNT(*) AS c FROM usuarios WHERE ativo=TRUE")[0]['c'] ?? 0);

ob_start();
?>
<div class="row">
  <div class="col-md-3 mb-3">
    <div class="card text-bg-primary shadow-sm">
      <div class="card-body text-center">
        <h6>Total de Clientes</h6>
        <h3><?= $totalClientes ?></h3>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card text-bg-success shadow-sm">
      <div class="card-body text-center">
        <h6>Propostas Emitidas</h6>
        <h3><?= $totalPropostas ?></h3>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card text-bg-warning shadow-sm">
      <div class="card-body text-center">
        <h6>Pacotes Ativos</h6>
        <h3><?= $totalPacotes ?></h3>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card text-bg-info shadow-sm">
      <div class="card-body text-center">
        <h6>Usuários Ativos</h6>
        <h3><?= $totalUsuarios ?></h3>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mt-4">
  <div class="card-body">
    <h6 class="fw-bold text-primary mb-3">Bem-vindo, <?= e($user['nome'] ?? 'Usuário') ?>!</h6>
    <p>Use o menu lateral para navegar entre os módulos do sistema.
       Os itens são gerenciados dinamicamente a partir da tabela <strong>menus</strong>.</p>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/inc/template_base.php';
