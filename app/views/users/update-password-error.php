<?php
$page_title = "Erro ao Redefinir Senha";
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Erro ao Redefinir Senha</div>
            <div class="card-body">
                <p>O token de redefinição de senha é inválido ou expirou. Por favor, solicite um novo link de redefinição de senha.</p>
                <a href="/users/forgot-password" class="btn btn-primary">Solicitar Novo Link</a>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
echo $content;
