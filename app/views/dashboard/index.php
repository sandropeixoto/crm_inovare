<?php
$page_title = 'Dashboard';
$breadcrumb = 'Início > Dashboard';

ob_start();
?>
<div class="row">
  <!-- Clientes -->
  <div class="col-lg-3 col-6">
    <div class="small-box bg-info">
      <div class="inner">
        <h3><?= $totalClientes ?></h3>
        <p>Total de Clientes</p>
      </div>
      <div class="icon">
        <i class="fas fa-users"></i>
      </div>
      <a href="/clientes" class="small-box-footer">
        Ver mais <i class="fas fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>

  <!-- Propostas -->
  <div class="col-lg-3 col-6">
    <div class="small-box bg-success">
      <div class="inner">
        <h3><?= $totalPropostas ?></h3>
        <p>Propostas Emitidas</p>
      </div>
      <div class="icon">
        <i class="fas fa-file-invoice"></i>
      </div>
      <a href="/propostas" class="small-box-footer">
        Ver mais <i class="fas fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>

  <!-- Pacotes -->
  <div class="col-lg-3 col-6">
    <div class="small-box bg-warning">
      <div class="inner">
        <h3><?= $totalPacotes ?></h3>
        <p>Pacotes Ativos</p>
      </div>
      <div class="icon">
        <i class="fas fa-box"></i>
      </div>
      <a href="/pacotes" class="small-box-footer">
        Ver mais <i class="fas fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>

  <!-- Usuários -->
  <div class="col-lg-3 col-6">
    <div class="small-box bg-danger">
      <div class="inner">
        <h3><?= $totalUsuarios ?></h3>
        <p>Usuários Ativos</p>
      </div>
      <div class="icon">
        <i class="fas fa-user-shield"></i>
      </div>
      <a href="/users" class="small-box-footer">
        Ver mais <i class="fas fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-chart-line mr-1"></i>
          Bem-vindo ao CRM Inovare
        </h3>
      </div>
      <div class="card-body">
        <p>Olá, <strong><?= e($user['nome']) ?></strong>!</p>
        <p>Use o menu lateral para navegar entre os módulos do sistema.</p>
        <p>Os itens do menu são gerenciados dinamicamente a partir da tabela <strong>menus</strong>.</p>

        <div class="mt-4">
          <h5>Acesso Rápido:</h5>
          <div class="btn-group" role="group">
            <a href="/clientes/create" class="btn btn-primary">
              <i class="fas fa-plus"></i> Novo Cliente
            </a>
            <a href="/propostas/create" class="btn btn-success">
              <i class="fas fa-file-invoice"></i> Nova Proposta
            </a>
            <a href="/relatorios" class="btn btn-info">
              <i class="fas fa-chart-bar"></i> Relatórios
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo $content;
