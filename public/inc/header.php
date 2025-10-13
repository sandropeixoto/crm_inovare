<?php
// /public/inc/header.php
if (!isset($page_title)) $page_title = 'Painel de Controle';
?>
<div class="header d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
  <div>
    <h5 class="m-0"><?= htmlspecialchars($page_title) ?></h5>
    <small class="text-muted">
      <a href="../index.php" class="text-decoration-none text-muted">🏠 Início</a>
      <?= isset($breadcrumb) ? ' / ' . htmlspecialchars($breadcrumb) : '' ?>
    </small>
  </div>
  <div class="d-flex align-items-center gap-3">
    <div class="text-muted small"><?= date('d/m/Y H:i') ?></div>
    <?php if(isset($quick_action)): ?>
      <a href="<?= htmlspecialchars($quick_action['url']) ?>" class="btn btn-sm btn-primary">
        <?= htmlspecialchars($quick_action['label']) ?>
      </a>
    <?php endif; ?>
  </div>
</div>
