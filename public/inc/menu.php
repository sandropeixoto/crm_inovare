<?php
if (!function_exists('h')) {
  function h($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}
}

// Buscar configuraÃ§Ã£o ativa
$config = run_query("SELECT empresa_nome, logotipo_url FROM configuracoes WHERE ativo=TRUE ORDER BY id DESC LIMIT 1")[0] ?? [
  'empresa_nome' => 'CRM Inovare',
  'logotipo_url' => ''
];

$user = $_SESSION['user'] ?? null;
$current = basename($_SERVER['PHP_SELF']);
$path = $_SERVER['REQUEST_URI'];
?>
<div class="sidebar d-flex flex-column">
  <div class="p-3 text-center border-bottom">
    <?php if(!empty($config['logotipo_url'])): ?>
      <img src="<?= h($config['logotipo_url']) ?>" alt="Logo" style="max-height:55px;max-width:100%;">
    <?php endif; ?>
    <div class="fw-bold mt-2 fs-6 text-white"><?= h($config['empresa_nome']) ?></div>
  </div>

  <!-- MENU PRINCIPAL -->
  <a href="../index.php" class="<?= $current=='index.php'?'active':'' ?>">ðŸ  Dashboard</a>
  <a href="../clientes/listar.php" class="<?= str_contains($path,'clientes')?'active':'' ?>">ðŸ‘¥ Clientes</a>
  <a href="../propostas/listar.php" class="<?= str_contains($path,'propostas')?'active':'' ?>">ðŸ“„ Propostas</a>

  <?php if(in_array($user['perfil'], ['admin','gestor'])): ?>
    <a href="../usuarios/listar.php" class="<?= str_contains($path,'usuarios')?'active':'' ?>">ðŸ§‘â€ðŸ’¼ UsuÃ¡rios</a>
    <a href="../configuracoes/editar.php" class="<?= str_contains($path,'configuracoes')?'active':'' ?>">âš™ï¸ ConfiguraÃ§Ãµes</a>

    <!-- SUBMENU AUXILIARES -->
    <div class="accordion mt-2" id="menuAuxiliares">
      <div class="accordion-item border-0 bg-transparent">
        <h2 class="accordion-header" id="headingAux">
          <button class="accordion-button collapsed p-2 ps-3 text-white bg-transparent shadow-none" 
                  type="button" data-bs-toggle="collapse" data-bs-target="#collapseAux"
                  aria-expanded="false" aria-controls="collapseAux"
                  style="font-size:0.95rem;">
            âš™ï¸ MÃ³dulos Auxiliares
          </button>
        </h2>
        <div id="collapseAux" class="accordion-collapse collapse <?= str_contains($path,'auxiliares')?'show':'' ?>" aria-labelledby="headingAux">
          <div class="accordion-body p-0">
            <a href="../auxiliares/pacotes/listar.php" class="ps-5 py-2 d-block text-white <?= str_contains($path,'auxiliares/pacotes/')?'active':'' ?>">�Y"� Pacotes</a>
            <a href="../auxiliares/pacotes_servicos/listar.php" class="ps-5 py-2 d-block text-white <?= str_contains($path,'pacotes_servicos')?'active':'' ?>">�Y"S Serviços</a>
            <a href="../auxiliares/status_proposta.php" class="ps-5 py-2 d-block text-white <?= str_contains($path,'status_proposta')?'active':'' ?>">�Y"S Status Proposta</a>
            <a href="../auxiliares/classificacoes.php" class="ps-5 py-2 d-block text-white <?= str_contains($path,'classificacoes')?'active':'' ?>">�Y?���? Classifica����es</a>
            <a href="../auxiliares/unidades_medida.php" class="ps-5 py-2 d-block text-white <?= str_contains($path,'unidades_medida')?'active':'' ?>">�Y"? Unidades de Medida</a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- RODAPÃ‰ MENU -->
  <div class="mt-auto border-top p-3 small">
    Logado como:<br>
    <span class="fw-semibold"><?= h($user['nome'] ?? '') ?></span><br>
    <a href="../login.php" class="text-warning text-decoration-none">Sair</a>
  </div>
</div>

<!-- Script para funcionamento do acordeÃ£o -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>



