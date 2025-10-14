<?php
require_once __DIR__ . '/../config/db.php';

ensure_session_security(false);

if (!empty($_SESSION['user'])) {
  redirect(app_url('index.php'));
}

// Busca configurações da empresa
$config = run_query("SELECT * FROM configuracoes WHERE ativo=1 LIMIT 1")[0] ?? [
  'empresa_nome' => 'CRM Inovare',
  'logotipo_url' => '/inovare/public/assets/logo.png',
  'rodape' => 'Sistema Inovare - Todos os direitos reservados.'
];

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validate_csrf_token($_POST['_token'] ?? null);

  $email = trim($_POST['email'] ?? '');
  $senha = trim($_POST['senha'] ?? '');

  if (!$email || !$senha) {
    $erro = 'Informe usuário e senha.';
  } else {
    $user = run_query("SELECT * FROM usuarios WHERE email=? AND ativo=1", [$email]);
    if ($user && password_verify($senha, $user[0]['senha_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user'] = [
        'id' => (int)$user[0]['id'],
        'nome' => $user[0]['nome'],
        'email' => $user[0]['email'],
        'perfil' => $user[0]['perfil']
      ];
      run_query("UPDATE usuarios SET ultimo_login=NOW() WHERE id=?", [$user[0]['id']]);
      redirect(app_url('index.php'));
    } else {
      $erro = "Usuário ou senha inválidos.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Login - <?= htmlspecialchars($config['empresa_nome']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8f9fa url('/inovare/public/assets/bg-login.svg') no-repeat center/cover;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; margin: 0;
    }
    .login-card {
      background: white; padding: 2rem; border-radius: 12px;
      box-shadow: 0 3px 15px rgba(0,0,0,.1); width: 100%; max-width: 380px;
    }
    .login-card img { max-height: 70px; margin-bottom: 10px; }
    .btn-primary { background-color: #0d6efd; border: none; }
    .form-control { border-radius: 10px; }
    .small-link { font-size: 0.9rem; }
  </style>
</head>
<body>
  <div class="login-card text-center">
    <img src="<?= htmlspecialchars($config['logotipo_url']) ?>" alt="Logo">
    <h5 class="mb-4"><?= htmlspecialchars($config['empresa_nome']) ?></h5>

    <?php if($erro): ?>
      <div class="alert alert-danger py-1"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3 text-start">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required autofocus>
      </div>
      <div class="mb-3 text-start">
        <label class="form-label">Senha</label>
        <input type="password" name="senha" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Entrar</button>
    </form>

    <div class="mt-3 small-link text-muted">
      <?= htmlspecialchars($config['rodape'] ?? '') ?>
    </div>
  </div>
</body>
</html>
