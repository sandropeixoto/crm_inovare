<?php
require_once __DIR__ . '/../../config/db.php';
require_auth();
require_role(['admin', 'gestor']);

$page_title = 'Classificações';
$breadcrumb = 'Início > Auxiliares > Classificações';

$mensagem = '';
$erro = '';

// Verificar se tabela existe, senão criar
try {
    run_query("SELECT 1 FROM classificacoes LIMIT 1");
} catch (Exception $e) {
    try {
        run_query("
            CREATE TABLE IF NOT EXISTS classificacoes (
                id SERIAL PRIMARY KEY,
                nome VARCHAR(100) NOT NULL UNIQUE,
                descricao TEXT,
                cor VARCHAR(20) DEFAULT '#007bff',
                ativo BOOLEAN DEFAULT TRUE,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (Exception $e2) {}
}

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    validate_csrf_token($_POST['_token'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    
    try {
        run_query("DELETE FROM classificacoes WHERE id = ?", [$id]);
        log_user_action($user['id'] ?? null, 'Excluiu classificação', 'classificacoes', $id);
        $mensagem = 'Classificação excluída com sucesso!';
    } catch (Exception $e) {
        $erro = 'Erro ao excluir: ' . $e->getMessage();
    }
}

// Processar cadastro/edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    validate_csrf_token($_POST['_token'] ?? null);
    
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $cor = trim($_POST['cor'] ?? '#007bff');
    $ativo = isset($_POST['ativo']) ? TRUE : FALSE;
    
    if (empty($nome)) {
        $erro = 'O nome é obrigatório.';
    } else {
        try {
            if ($id > 0) {
                run_query("UPDATE classificacoes SET nome = ?, descricao = ?, cor = ?, ativo = ? WHERE id = ?", 
                         [$nome, $descricao, $cor, $ativo, $id]);
                log_user_action($user['id'] ?? null, 'Atualizou classificação', 'classificacoes', $id);
                $mensagem = 'Classificação atualizada com sucesso!';
            } else {
                run_query("INSERT INTO classificacoes (nome, descricao, cor, ativo) VALUES (?, ?, ?, ?)", 
                         [$nome, $descricao, $cor, $ativo]);
                log_user_action($user['id'] ?? null, 'Criou classificação', 'classificacoes', null);
                $mensagem = 'Classificação criada com sucesso!';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

// Buscar registros
$registros = run_query("SELECT * FROM classificacoes ORDER BY nome");

// Preparar dados para edição
$editando = null;
if (isset($_GET['editar'])) {
    $id_editar = (int)$_GET['editar'];
    $result = run_query("SELECT * FROM classificacoes WHERE id = ?", [$id_editar]);
    if ($result) {
        $editando = $result[0];
    }
}

$user = current_user();

ob_start();
?>

<?php if ($mensagem): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= e($mensagem) ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= e($erro) ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><?= $editando ? 'Editar' : 'Nova' ?> Classificação</h3>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="salvar">
                <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="nome" class="form-control" 
                               value="<?= e($editando['nome'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"><?= e($editando['descricao'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Cor</label>
                        <input type="color" name="cor" class="form-control" 
                               value="<?= e($editando['cor'] ?? '#007bff') ?>">
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="ativo" class="custom-control-input" id="ativo" 
                                   <?= (!$editando || ($editando['ativo'] ?? false)) ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="ativo">Ativo</label>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <?php if ($editando): ?>
                    <a href="<?= app_url('auxiliares/classificacoes.php') ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Classificações Cadastradas</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th width="80">ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th width="80">Cor</th>
                            <th width="80">Status</th>
                            <th width="150" class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                Nenhuma classificação cadastrada
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $reg): ?>
                            <tr>
                                <td><?= $reg['id'] ?></td>
                                <td><?= e($reg['nome']) ?></td>
                                <td><?= e($reg['descricao'] ?? '') ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?= e($reg['cor']) ?>">
                                        <?= e($reg['cor']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reg['ativo']): ?>
                                        <span class="badge badge-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <a href="?editar=<?= $reg['id'] ?>" class="btn btn-sm btn-info" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Confirma a exclusão?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= $reg['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
