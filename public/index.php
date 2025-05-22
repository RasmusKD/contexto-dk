<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';

// Rens request URI og fjern query parametre
$requestUri = strtok($_SERVER['REQUEST_URI'], '?');
$route = '/' . trim($requestUri, '/');

// Route konfiguration
$routes = [
    '/' => ['controller' => 'GameController', 'action' => 'index', 'method' => 'GET'],
    '/guess' => ['controller' => 'GameController', 'action' => 'handleGuess', 'method' => 'POST'],
    '/new-game' => ['controller' => 'GameController', 'action' => 'newGame', 'method' => 'GET'],
    '/random-game' => ['controller' => 'GameController', 'action' => 'randomGame', 'method' => 'GET'],
    '/api/top-words' => ['controller' => 'GameController', 'action' => 'getTopWords', 'method' => 'GET'],
    '/api/hint' => ['controller' => 'GameController', 'action' => 'getHint', 'method' => 'GET'],
    '/api/give-up' => ['controller' => 'GameController', 'action' => 'giveUp', 'method' => 'GET'],
];

$controllerNamespace = 'App\\Controllers\\';
$controllerName = null;
$action = null;

if (array_key_exists($route, $routes)) {
    $routeConfig = $routes[$route];

    if ($_SERVER['REQUEST_METHOD'] === $routeConfig['method']) {
        $controllerName = $controllerNamespace . $routeConfig['controller'];
        $action = $routeConfig['action'];
    } else {
        http_response_code(405);
        echo "405 - Metoden er ikke tilladt for denne URL.";
        exit;
    }
}

// Udfør controller action med fejlhåndtering
if ($controllerName && $action) {
    if (class_exists($controllerName)) {
        $controllerInstance = new $controllerName();
        if (method_exists($controllerInstance, $action)) {
            try {
                $controllerInstance->$action();
            } catch (\Exception $e) {
                error_log("Controller fejl: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                http_response_code(500);
                echo "Der opstod en intern serverfejl. Prøv venligst igen senere.";
            }
        } else {
            http_response_code(404);
            error_log("Action '$action' ikke fundet i '$controllerName'. Route: $route");
            echo "404 - Siden blev ikke fundet.";
        }
    } else {
        http_response_code(404);
        error_log("Controller '$controllerName' ikke fundet. Route: $route");
        echo "404 - Siden blev ikke fundet.";
    }
} else {
    if (http_response_code() !== 405) {
        http_response_code(404);
        error_log("Ukendt route: $route");
        echo "404 - Siden blev ikke fundet.";
    }
}
?>
