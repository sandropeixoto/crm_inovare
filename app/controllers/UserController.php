<?php
require_once 'BaseController.php';
require_once __DIR__ . '/../models/UserModel.php';

class UserController extends BaseController {
    public function index() {
        $userModel = new UserModel();
        $users = $userModel->findAll();
        $this->view('users/index', ['users' => $users]);
    }

    public function forgotPassword() {
        $this->view('users/forgot-password');
    }

    public function sendResetLink() {
        $email = $_POST['email'];
        $userModel = new UserModel();
        $token = $userModel->createPasswordResetToken($email);

        if ($token) {
            // Simulação de envio de email
            // $resetLink = "http://localhost:8000/reset-password?token=$token";
            // mail($email, "Redefinição de Senha", "Clique aqui para redefinir sua senha: $resetLink");
        }

        $this->view('users/request-reset-success');
    }

    public function resetPassword() {
        $this->view('users/reset-password');
    }

    public function updatePassword() {
        $token = $_POST['token'];
        $password = $_POST['password'];
        $passwordConfirmation = $_POST['password_confirmation'];

        if ($password !== $passwordConfirmation) {
            // Idealmente, isso seria tratado com validação no lado do cliente
            // e uma mensagem de erro na própria página de redefinição de senha.
            // Por simplicidade, vamos redirecionar para a página de erro.
            $this->view('users/update-password-error');
            return;
        }

        $userModel = new UserModel();
        $user = $userModel->validatePasswordResetToken($token);

        if ($user) {
            $userModel->updatePassword($user['id'], $password);
            $this->view('users/update-password-success');
        } else {
            $this->view('users/update-password-error');
        }
    }
}
