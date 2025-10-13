<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor']);

$id = (int)($_GET['id'] ?? 0);
$editando = $id > 0;

if ($editando) {
    $usuario = run_query("SELECT * FROM usuarios WHERE id = ?", [$id])[0] ?? null;
    if (!$usuario) {
        exit('Usuário não encontrado.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'nome' => trim($_POST['nome'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'perfil' => $_POST['perfil'] ?? 'comercial',
        'ativo' => isset($_POST['ativo']) ? 1 : 0
    ];
    $senha = trim($_POST['senha'] ?? '');

    if (!$dados['nome'] || !$dados['email']) {
        $erro = "Nome e e-mail são obrigatórios.";
    } else {
        if ($editando) {
            $campos = [];
            $params = [];
            foreach ($dados as $k=>$v){ $campos[]="$k=?"; $params[]=$v; }
            if ($senha) {
                $campos[]="senha_hash=?";
                $params[] = password_hash($senha, PASSWORD_BCRYPT);
            }
            $params[] = $id;
            run_query("UPDATE usuarios SET ".implode(',', $campos)." WHERE id=?", $params);
            log_user_action($_SESSION['user']['id'], 'Editou usuário', 'usuarios', $id, $usuario, $dados);
            $msg = "Usuário atualizado com sucesso!";
        } else {
            $hash = password_hash($senha ?: '123456', PASSWORD_BCRYPT);
            run_query(
                "INSERT INTO usuarios (nome,email,senha_hash,perfil,ativo) VALUES (?,?,?,?,?)",
                [$dados['nome'],$dados['email'],$hash,$dados['perfil'],$dados['ativo']]
            );
            $novoId = pdo()->lastInsertId();
            log_user_action($_SESSION['user']['id'], 'Criou usuário', 'usuarios', $novoId, null, $dados);
            $msg = "Usuário criado com sucesso! (senha padrão: 123456)";
        }
    }
}

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function selected($a,$b){return $a===$b?'selected':'';}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title><?= $editando?'Editar':'Novo' ?> Usuário - CRM Inovare</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h4><?= $editando?'Editar':'Novo' ?> Usuário</h4>

  <?php if(!empty($msg)): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if(!empty($erro)): ?><div class="alert alert-danger"><?= h($erro) ?></div><?php endif; ?>

  <form method="POST" class="card p-3 shadow-sm bg-white">
    <div class="row mb-2">
      <div class="col-md-6">
        <label class="form-label">Nome *</label>
        <input type="text" name="nome" class="form-control" value="<?= h($usuario['nome'] ?? '') ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">E-mail *</label>
        <input type="email" name="email" class="form-control" value="<?= h($usuario['email'] ?? '') ?>" required>
      </div>
    </div>
    <div class="row mb-2">
      <div class="col-md-4">
        <label class="form-label">Perfil</label>
        <select name="perfil" class="form-select">
          <option value="admin" <?= selected($usuario['perfil'] ?? '', 'admin') ?>>Admin</option>
          <option value="gestor" <?= selected($usuario['perfil'] ?? '', 'gestor') ?>>Gestor</option>
          <option value="comercial" <?= selected($usuario['perfil'] ?? '', 'comercial') ?>>Comercial</option>
          <option value="visualizador" <?= selected($usuario['perfil'] ?? '', 'visualizador') ?>>Visualizador</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Senha <?= $editando?'(deixe em branco para manter)':'' ?></label>
        <input type="password" name="senha" class="form-control">
      </div>
      <div class="col-md-4 d-flex align-items-center">
        <div class="form-check mt-4">
          <input type="checkbox" name="ativo" class="form-check-input" <?= ($usuario['ativo'] ?? 1)?'checked':'' ?>>
          <label class="form-check-label">Ativo</label>
        </div>
      </div>
    </div>

    <div>
      <button type="submit" class="btn btn-primary">Salvar</button>
      <a href="listar.php" class="btn btn-secondary">Voltar</a>
    </div>
  </form>
</div>
</body>
</html>
