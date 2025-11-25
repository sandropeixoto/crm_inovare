<?php
$page_title = "Recuperar Senha";
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Recuperar Senha</div>
            <div class="card-body">
                <form method="POST" action="/users/send-reset-link">
                    <div class="mb-3">
                        <label for="email" class="form-label">Endereço de E-mail</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar Link de Redefinição de Senha</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
echo $content;
