<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = "Configurações do Sistema";
$breadcrumb = "Administração > Configurações";

// Caminho base para salvar imagens
$upload_dir = __DIR__ . '/../imagens/';
$upload_url = '/inovare/public/imagens/';

// Busca configuração ativa
$config = run_query("SELECT * FROM configuracoes WHERE ativo=TRUE LIMIT 1")[0] ?? [
  'empresa_nome' => '',
  'logotipo_url' => '',
  'endereco' => '',
  'email_contato' => '',
  'telefone' => '',
  'instagram' => '',
  'rodape' => '',
  'ativo' => 1
];

$saved = false;

// PROCESSAR FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $empresa_nome = trim($_POST['empresa_nome']);
  $endereco = trim($_POST['endereco']);
  $email_contato = trim($_POST['email_contato']);
  $telefone = trim($_POST['telefone']);
  $instagram = trim($_POST['instagram']);
  $rodape = trim($_POST['rodape']);
  $ativo = isset($_POST['ativo']) ? 1 : 0;

  // === UPLOAD DE LOGO ===
  $logotipo_url = $config['logotipo_url'] ?? '';
  if (!empty($_FILES['logotipo_arquivo']['name'])) {
    $ext = strtolower(pathinfo($_FILES['logotipo_arquivo']['name'], PATHINFO_EXTENSION));
    $nome_arquivo = 'logo_inovare_' . date('Y_m_d_His') . '.' . $ext;
    $destino = $upload_dir . $nome_arquivo;

    // Cria pasta se não existir
    if (!is_dir($upload_dir)) {
      mkdir($upload_dir, 0775, true);
    }

    if (move_uploaded_file($_FILES['logotipo_arquivo']['tmp_name'], $destino)) {
      $logotipo_url = $upload_url . $nome_arquivo;
    }
  }

  // Atualiza ou cria registro
  if (!empty($config['id'])) {
    run_query("
      UPDATE configuracoes 
         SET empresa_nome=?, logotipo_url=?, endereco=?, email_contato=?, telefone=?, instagram=?, rodape=?, ativo=?
       WHERE id=?",
      [$empresa_nome, $logotipo_url, $endereco, $email_contato, $telefone, $instagram, $rodape, $ativo, $config['id']]
    );
  } else {
    run_query("
      INSERT INTO configuracoes (empresa_nome, logotipo_url, endereco, email_contato, telefone, instagram, rodape, ativo)
      VALUES (?,?,?,?,?,?,?,1)",
      [$empresa_nome, $logotipo_url, $endereco, $email_contato, $telefone, $instagram, $rodape]
    );
  }

  header("Location: /inovare/public/configuracoes/editar.php?saved=1");
  exit;
}

$saved = isset($_GET['saved']);

ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold text-primary mb-0">Configurações Gerais</h5>
      <span class="small text-muted">Gerencie informações institucionais e identidade visual</span>
    </div>

    <?php if ($saved): ?>
      <div class="alert alert-success py-2">✅ Configurações salvas com sucesso!</div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="max-width:850px;">
      <div class="row">
        <div class="col-md-8 mb-3">
          <label class="form-label">Nome da Empresa / Sistema</label>
          <input type="text" name="empresa_nome" value="<?= htmlspecialchars($config['empresa_nome']) ?>" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3 text-center">
          <?php if(!empty($config['logotipo_url'])): ?>
            <img src="<?= htmlspecialchars($config['logotipo_url']) ?>" class="img-fluid border rounded shadow-sm mt-2" style="max-height:60px;">
          <?php else: ?>
            <span class="text-muted small d-block mt-4">Sem logotipo</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Upload de Logomarca</label>
        <input type="file" name="logotipo_arquivo" accept="image/*" class="form-control">
        <?php if(!empty($config['logotipo_url'])): ?>
          <small class="text-muted">Arquivo atual: <?= basename($config['logotipo_url']) ?></small>
        <?php endif; ?>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Email de Contato</label>
          <input type="email" name="email_contato" value="<?= htmlspecialchars($config['email_contato']) ?>" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Telefone</label>
          <input type="text" name="telefone" value="<?= htmlspecialchars($config['telefone']) ?>" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Instagram</label>
          <input type="text" name="instagram" value="<?= htmlspecialchars($config['instagram']) ?>" class="form-control" placeholder="@empresa">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Endereço</label>
        <textarea name="endereco" rows="2" class="form-control"><?= htmlspecialchars($config['endereco']) ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Rodapé</label>
        <textarea name="rodape" rows="3" class="form-control"><?= htmlspecialchars($config['rodape']) ?></textarea>
      </div>

      <div class="form-check mb-3">
        <input type="checkbox" name="ativo" id="ativo" class="form-check-input" <?= ($config['ativo'] ?? 1) ? 'checked' : '' ?>>
        <label for="ativo" class="form-check-label">Configuração ativa</label>
      </div>

      <div class="mt-3">
        <button class="btn btn-primary">Salvar Alterações</button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
