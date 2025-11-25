<?php
require_once 'BaseModel.php';

class UserModel extends BaseModel {
    protected $table = 'usuarios';

    public function findByEmail(string $email) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createPasswordResetToken(string $email): ?string {
        $user = $this->findByEmail($email);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires]);

        return $token;
    }

    public function validatePasswordResetToken(string $token): ?array {
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > ?");
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            return $this->findByEmail($reset['email']);
        }

        return null;
    }

    public function updatePassword(int $userId, string $password): void {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);

        // Invalida o token
        $user = $this->find($userId);
        if ($user) {
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$user['email']]);
        }
    }
}
