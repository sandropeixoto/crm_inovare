<?php
require_once __DIR__ . '/../../config/db.php';
require_auth();
require_role(['admin', 'gestor']);

$page_title = 'Tipos de Interação';
$breadcrumb = 'Início > Auxiliares > Tipos de Interação';

$mensagem = '';
$erro = '';

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    validate_csrf_token($_POST['_token'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    
    try {
        run_query("DELETE FROM interacoes_tipos WHERE id = ?", [$id]);
        log_user_action($user['id'] ?? null, 'Excluiu tipo de interação', 'interacoes_tipos', $id);
        $mensagem = 'Tipo de interação excluído com sucesso!';
    } catch (Exception $e) {
        $erro = 'Erro ao excluir: ' . $e->getMessage();
    }
}

// Processar cadastro/edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    validate_csrf_token($_POST['_token'] ?? null);
    
    $id = (int)($_POST['id'] ?? 0);
    $tipo_interacao = trim($_POST['tipo_interacao'] ?? '');
    
    if (empty($tipo_interacao)) {
        $erro = 'O tipo de interação é obrigatório.';
    } else {
        try {
            if ($id > 0) {
                // Atualizar
                run_query("UPDATE interacoes_tipos SET tipo_interacao = ? WHERE id = ?", [$tipo_interacao, $id]);
                log_user_action($user['id'] ?? null, 'Atualizou tipo de interação', 'interacoes_tipos', $id);
                $mensagem = 'Tipo de interação atualizado com sucesso!';
            } else {
                // Inserir
                run_query("INSERT INTO interacoes_tipos (tipo_interacao) VALUES (?)", [$tipo_interacao]);
                log_user_action($user['id'] ?? null, 'Criou tipo de interação', 'interacoes_tipos', null);
                $mensagem = 'Tipo de interação criado com sucesso!';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

// Buscar registros
$registros = run_query("SELECT * FROM interacoes_tipos ORDER BY tipo_interacao");

// Preparar dados para edição
$editando = null;
if (isset($_GET['editar'])) {
    $id_editar = (int)$_GET['editar'];
    $result = run_query("SELECT * FROM interacoes_tipos WHERE id = ?", [$id_editar]);
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
                <h3 class="card-title"><?= $editando ? 'Editar' : 'Novo' ?> Tipo de Interação</h3>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="salvar">
                <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="form-group">
                        <label>Tipo de Interação *</label>
                        <input type="text" name="tipo_interacao" class="form-control" 
                               value="<?= e($editando['tipo_interacao'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <?php if ($editando): ?>
                    <a href="<?= app_url('auxiliares/tipos_interacao.php') ?>" class="btn btn-secondary">
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
                <h3 class="card-title">Tipos de Interação Cadastrados</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th width="80">ID</th>
                            <th>Tipo de Interação</th>
                            <th width="150" class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">
                                Nenhum tipo de interação cadastrado
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $reg): ?>
                            <tr>
                                <td><?= $reg['id'] ?></td>
                                <td><?= e($reg['tipo_interacao']) ?></td>
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
