<?php
$page_title = "Senha Redefinida";
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Senha Redefinida com Sucesso</div>
            <div class="card-body">
                <p>Sua senha foi redefinida com sucesso. Você já pode fazer login com sua nova senha.</p>
                <a href="/login" class="btn btn-primary">Ir para o Login</a>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
echo $content;
