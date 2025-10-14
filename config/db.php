<?php
declare(strict_types=1);

/**
 * Bootstrap de infraestrutura: conexão PDO, sessão, helpers e segurança. v2
 */

date_default_timezone_set(getenv('CRM_TZ') ?: 'America/Sao_Paulo');

define('APP_ENV', getenv('CRM_ENV') ?: 'development');
define('APP_BASE_PATH', rtrim(getenv('CRM_BASE_PATH') ?: '/inovare/public', '/') . '/');

if (APP_ENV !== 'development') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Strict',
        'path' => '/',
    ]);
    session_start();
}

define('DB_HOST', getenv('CRM_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('CRM_DB_NAME') ?: 'crm_inovare');
define('DB_USER', getenv('CRM_DB_USER') ?: 'root');
define('DB_PASS', getenv('CRM_DB_PASS') ?: 'SENHA_FORTE_AQUI');
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

function pdo(): PDO
{
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
        log_system('critical', 'Falha na conexão PDO: ' . $e->getMessage(), __FILE__, __LINE__);
        http_response_code(500);
        exit('Erro interno ao conectar ao banco de dados.');
    }

    return $pdo;
}

function client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function client_ua(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}

function log_system(string $nivel, string $mensagem, string $arquivo = '', int $linha = 0, string $stack = ''): void
{
    try {
        $sql = "INSERT INTO sistema_logs (nivel, mensagem, arquivo, linha, stack) VALUES (?,?,?,?,?)";
        pdo()->prepare($sql)->execute([$nivel, $mensagem, $arquivo, $linha, $stack]);
    } catch (Throwable $e) {
        error_log('[CRM-Inovare] log_system fail: ' . $e->getMessage());
    }
}

function log_user_action(
    ?int $id_usuario,
    string $acao,
    ?string $tabela = null,
    ?int $id_registro = null,
    $dados_anteriores = null,
    $dados_novos = null
): void {
    try {
        $sql = "INSERT INTO logs_usuarios (id_usuario, acao, tabela_afetada, id_registro_afetado, dados_anteriores, dados_novos, ip, user_agent)"
             . " VALUES (?,?,?,?,?,?,?,?)";

        $antes = $dados_anteriores ? json_encode($dados_anteriores, JSON_UNESCAPED_UNICODE) : null;
        $depois = $dados_novos ? json_encode($dados_novos, JSON_UNESCAPED_UNICODE) : null;

        pdo()->prepare($sql)->execute([
            $id_usuario,
            $acao,
            $tabela,
            $id_registro,
            $antes,
            $depois,
            client_ip(),
            client_ua(),
        ]);
    } catch (Throwable $e) {
        log_system('error', 'Falha ao registrar log de usuário: ' . $e->getMessage(), __FILE__, __LINE__);
    }
}

function run_query(string $sql, array $params = []): array
{
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

function app_url(string $path = ''): string
{
    return APP_BASE_PATH . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function abort(int $statusCode, string $message = 'Operação não permitida.'): void
{
    http_response_code($statusCode);
    exit($message);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function ensure_session_security(bool $requireAuth = true): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $fingerprint = hash('sha256', client_ip() . '|' . client_ua());

    if (empty($_SESSION['__fingerprint'])) {
        session_regenerate_id(true);
        $_SESSION['__fingerprint'] = $fingerprint;
        $_SESSION['__last_activity'] = time();
    } else {
        if (!hash_equals($_SESSION['__fingerprint'], $fingerprint)) {
            logout_user();
            abort(403, 'Sessão inválida.');
        }

        $timeout = 1800; // 30 minutos
        if (!empty($_SESSION['__last_activity']) && (time() - (int)$_SESSION['__last_activity']) > $timeout) {
            logout_user();
            abort(440, 'Sessão expirada.');
        }

        $_SESSION['__last_activity'] = time();
    }

    if ($requireAuth && empty($_SESSION['user'])) {
        redirect(app_url('login.php'));
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_auth(): array
{
    ensure_session_security(true);
    $user = current_user();
    if (!$user) {
        redirect(app_url('login.php'));
    }

    return $user;
}

function require_role(array $roles): void
{
    $user = require_auth();
    $perfil = $user['perfil'] ?? 'visualizador';

    if (!in_array($perfil, $roles, true)) {
        abort(403, 'Sem permissão.');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf_token(?string $token): void
{
    if (!$token || empty($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $token)) {
        abort(419, 'Token CSRF inválido. Recarregue a página.');
    }
}

function default_app_config(): array
{
    return [
        'empresa_nome' => 'CRM Inovare',
        'logotipo_url' => app_url('assets/logo.png'),
        'rodape' => 'CRM Inovare',
    ];
}

function app_config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $config = default_app_config();

    try {
        $stmt = pdo()->query("SELECT * FROM configuracoes WHERE ativo=1 ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $config = $row + $config;
        }
    } catch (Throwable $e) {
        log_system('warning', 'Configurações indisponíveis: ' . $e->getMessage(), __FILE__, __LINE__);
    }

    return $config;
}

function fallback_menus(): array
{
    return [
        [
            'id' => 1,
            'parent_id' => null,
            'titulo' => 'Dashboard',
            'icone' => '🏠',
            'link' => 'index.php',
            'perfis_permitidos' => 'admin,gestor,comercial,visualizador',
        ],
        [
            'id' => 2,
            'parent_id' => null,
            'titulo' => 'Clientes',
            'icone' => '👥',
            'link' => '#',
            'perfis_permitidos' => 'admin,gestor,comercial',
        ],
        [
            'id' => 3,
            'parent_id' => 2,
            'titulo' => 'Listar Clientes',
            'icone' => '📋',
            'link' => 'clientes/listar.php',
            'perfis_permitidos' => 'admin,gestor,comercial',
        ],
        [
            'id' => 4,
            'parent_id' => 2,
            'titulo' => 'Novo Cliente',
            'icone' => '➕',
            'link' => 'clientes/nova.php',
            'perfis_permitidos' => 'admin,gestor,comercial',
        ],
        [
            'id' => 5,
            'parent_id' => null,
            'titulo' => 'Propostas',
            'icone' => '📄',
            'link' => 'propostas/listar.php',
            'perfis_permitidos' => 'admin,gestor,comercial',
        ],
        [
            'id' => 6,
            'parent_id' => null,
            'titulo' => 'Usuários',
            'icone' => '🛠️',
            'link' => 'usuarios/listar.php',
            'perfis_permitidos' => 'admin,gestor',
        ],
    ];
}

function build_menu_tree(array $menus, string $perfil): array
{
    $index = [];
    static $autoId = 1000;

    foreach ($menus as $menu) {
        $permitidos = array_filter(array_map('trim', explode(',', (string)($menu['perfis_permitidos'] ?? ''))));
        if ($permitidos && !in_array($perfil, $permitidos, true)) {
            continue;
        }

        $id = (int)($menu['id'] ?? 0);
        if ($id === 0) {
            $id = $autoId++;
        }

        $menu['filhos'] = [];
        $index[$id] = $menu;
    }

    $tree = [];
    foreach ($index as $id => &$menu) {
        $parentId = (int)($menu['parent_id'] ?? 0);
        if ($parentId && isset($index[$parentId])) {
            $index[$parentId]['filhos'][] =& $menu;
        } else {
            $tree[$id] =& $menu;
        }
    }
    unset($menu);

    return array_values($tree);
}

function load_menu_tree(string $perfil): array
{
    static $cache = [];
    if (isset($cache[$perfil])) {
        return $cache[$perfil];
    }

    try {
        $stmt = pdo()->query("SELECT * FROM menus WHERE ativo=1 ORDER BY parent_id, ordem, titulo");
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        log_system('warning', 'Falha ao carregar menus dinâmicos: ' . $e->getMessage(), __FILE__, __LINE__);
        $menus = [];
    }

    $tree = build_menu_tree($menus, $perfil);

    if (!$tree) {
        $tree = build_menu_tree(fallback_menus(), $perfil);
    }

    return $cache[$perfil] = $tree;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
