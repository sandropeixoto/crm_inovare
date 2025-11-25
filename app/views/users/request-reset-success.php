<?php
$page_title = "Link Enviado";
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Link Enviado com Sucesso</div>
            <div class="card-body">
                <p>Se um usuário com o e-mail fornecido existir em nosso sistema, um link de redefinição de senha foi enviado.</p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
echo $content;
