<?php
$page_title = "Redefinir Senha";
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Redefinir Senha</div>
            <div class="card-body">
                <form method="POST" action="/users/update-password">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">Nova Senha</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Redefinir Senha</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
echo $content;
