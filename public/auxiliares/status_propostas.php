<?php
require_once __DIR__ . '/../../config/db.php';
require_auth();
require_role(['admin', 'gestor']);

$page_title = 'Status de Propostas';
$breadcrumb = 'Início > Auxiliares > Status de Propostas';

$mensagem = '';
$erro = '';

// Nota: Em PostgreSQL, os status são definidos via CHECK constraint no schema
// Esta é apenas uma interface informativa dos status disponíveis
$status_disponiveis = [
    'rascunho' => 'Rascunho',
    'enviada' => 'Enviada',
    'aceita' => 'Aceita',
    'rejeitada' => 'Rejeitada',
    'expirada' => 'Expirada'
];

// Buscar contagem de propostas por status
$estatisticas = [];
foreach ($status_disponiveis as $codigo => $nome) {
    $result = run_query("SELECT COUNT(*) as total FROM propostas WHERE status = ?", [$codigo]);
    $estatisticas[$codigo] = $result[0]['total'] ?? 0;
}

$user = current_user();

ob_start();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Status de Propostas do Sistema</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Os status de propostas são definidos no schema do banco de dados. 
                    Esta tela mostra os status disponíveis e suas estatísticas.
                </p>
                
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th width="150" class="text-center">Total de Propostas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status_disponiveis as $codigo => $nome): ?>
                        <tr>
                            <td><code><?= e($codigo) ?></code></td>
                            <td>
                                <strong><?= e($nome) ?></strong>
                                <?php if ($codigo === 'rascunho'): ?>
                                    <span class="badge badge-secondary">Padrão</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-primary">
                                    <?= $estatisticas[$codigo] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    <strong>Informação:</strong> Para adicionar novos status, é necessário alterar o schema do banco de dados.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
