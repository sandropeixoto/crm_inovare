<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/controllers/BaseController.php';
require_once __DIR__ . '/../app/controllers/UserController.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/';
$route = str_replace($base_path, '', $request_uri);
$route = trim($route, '/');
$parts = explode('/', $route);

$controllerName = !empty($parts[0]) ? ucfirst($parts[0]) . 'Controller' : 'DashboardController';
$methodName = $parts[1] ?? 'index';
$param = $parts[2] ?? null;

if (class_exists($controllerName)) {
    $controller = new $controllerName();
    if (method_exists($controller, $methodName)) {
        ob_start();
        $controller->$methodName($param);
        $content = ob_get_clean();
        
        // Inclui o template base
        include __DIR__ . '/inc/template_base.php';
    } else {
        http_response_code(404);
        echo "Página não encontrada.";
    }
} else {
    http_response_code(404);
    echo "Página não encontrada.";
}
