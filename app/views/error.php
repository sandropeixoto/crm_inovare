<?php
$page_title = "Erro";
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card text-center">
            <div class="card-header">Ocorreu um Erro</div>
            <div class="card-body">
                <h5 class="card-title">Oops! Algo deu errado.</h5>
                <p class="card-text">Nossa equipe já foi notificada e está trabalhando para corrigir o problema.</p>
                <a href="/" class="btn btn-primary">Voltar para a Página Inicial</a>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
echo $content;
