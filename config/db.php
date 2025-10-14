<?php
/**
 * db.php — Conexão PDO e funções de log/auditoria para o CRM Inovare
 */

// declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Carrega variáveis de ambiente a partir do arquivo .env (se existir)
 */
if (!function_exists('load_env_file')) {
    /**
     * Lê um arquivo .env e injeta as chaves em getenv()/$_ENV/$_SERVER.
     */
    function load_env_file(string $filePath): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        if (!is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);

            if ($value !== '') {
                $quoteStart = $value[0];
                $quoteEnd = substr($value, -1);
                if (($quoteStart === '"' || $quoteStart === "'") && $quoteStart === $quoteEnd) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

if (!function_exists('env')) {
    /**
     * Recupera variável de ambiente com fallback.
     */
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

$rootPath = dirname(__DIR__);
load_env_file($rootPath . '/.env');

/**
 * Configuração — ajuste para o seu ambiente
 */
define('DB_HOST', env('CRM_DB_HOST', 'localhost'));
define('DB_NAME', env('CRM_DB_NAME', 'crm_inovare'));
define('DB_USER', env('CRM_DB_USER', 'root'));
define('DB_PASS', env('CRM_DB_PASS', 'SENHA_FORTE_AQUI'));
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna uma instância PDO singleton
 */
function pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (Throwable $e) {
        // Loga no sistema e exibe mensagem genérica
        log_system('critical', 'Falha na conexão PDO: ' . $e->getMessage(), __FILE__, __LINE__);
        http_response_code(500);
        exit('Erro interno ao conectar no banco.');
    }
    return $pdo;
}

/**
 * Dados de contexto (IP e User-Agent)
 */
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function client_ua(): string {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}

/**
 * Log de sistema (erros, avisos, info)
 */
function log_system(string $nivel, string $mensagem, string $arquivo = '', int $linha = 0, string $stack = ''): void {
    try {
        $sql = "INSERT INTO sistema_logs (nivel, mensagem, arquivo, linha, stack) VALUES (?,?,?,?,?)";
        pdo()->prepare($sql)->execute([$nivel, $mensagem, $arquivo, $linha, $stack]);
    } catch (Throwable $e) {
        // Em último caso, evita loop de erro
        error_log('[CRM-Inovare] log_system fail: ' . $e->getMessage());
    }
}

/**
 * Log de ação do usuário (auditoria)
 * Ex.: log_user_action($idUser, 'Criou proposta', 'propostas', $idProposta, $antes, $depois);
 */
function log_user_action(?int $id_usuario, string $acao, ?string $tabela = null, ?int $id_registro = null, $dados_anteriores = null, $dados_novos = null): void {
    try {
        $sql = "INSERT INTO logs_usuarios (id_usuario, acao, tabela_afetada, id_registro_afetado, dados_anteriores, dados_novos, ip, user_agent)
                VALUES (?,?,?,?,?,?,?,?)";
        $antes = $dados_anteriores ? json_encode($dados_anteriores, JSON_UNESCAPED_UNICODE) : null;
        $depois = $dados_novos ? json_encode($dados_novos, JSON_UNESCAPED_UNICODE) : null;
        pdo()->prepare($sql)->execute([
            $id_usuario, $acao, $tabela, $id_registro, $antes, $depois, client_ip(), client_ua()
        ]);
    } catch (Throwable $e) {
        log_system('error', 'Falha ao registrar log de usuário: ' . $e->getMessage(), __FILE__, __LINE__);
    }
}

/**
 * Helper para executar queries com try/catch e log automático de erro
 */
function run_query(string $sql, array $params = []): array {
    try {
        $stmt = pdo()->prepare($sql);
        $stmt->execute($params);
        if (preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql)) {
            return $stmt->fetchAll();
        }
        return ['affected' => $stmt->rowCount()];
    } catch (Throwable $e) {
        log_system('error', 'Query falhou: ' . $e->getMessage() . ' | SQL: ' . $sql, __FILE__, __LINE__);
        http_response_code(500);
        exit('Erro interno ao executar operação.');
    }
}

/**
 * Segurança extra de sessão (fixação de sessão básica)
 
function ensure_session_security(): void {
    if (!isset($_SESSION['__ip'])) {
        $_SESSION['__ip'] = client_ip();
        $_SESSION['__ua'] = client_ua();
        return;
    }
    if ($_SESSION['__ip'] !== client_ip() || $_SESSION['__ua'] !== client_ua()) {
        session_regenerate_id(true);
        $_SESSION = [];
        session_destroy();
        http_response_code(403);
        exit('Sessão inválida.');
    }
} */

// =====================================
// Função de verificação de sessão
// =====================================
function ensure_session_security() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    // Se não estiver logado, redireciona para login
    if (empty($_SESSION['user'])) {
        header("Location: /inovare/public/login.php");
        exit;
    }
}


/**
 * Verificação simples de permissão por perfil
 */
function require_role(array $roles): void {
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        exit('Não autenticado.');
    }
    $perfil = $_SESSION['user']['perfil'] ?? 'visualizador';
    if (!in_array($perfil, $roles, true)) {
        http_response_code(403);
        exit('Sem permissão.');
    }
}
