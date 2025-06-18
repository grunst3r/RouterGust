<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/src/Request.php';
require_once __DIR__ . '/src/Response.php';
require_once __DIR__ . '/src/Route.php';
require_once __DIR__ . '/src/Router.php';

use GustRouter\Request;
use GustRouter\Router;

$request = new Request();
$router = new Router($request);


function route(string $name, array $params = []): string {
    global $router;

    foreach ($router->getRoutes() as $r) {
        if ($r->routeName === $name) {
            $url = $r->path;

            // Reemplazar parámetros en la ruta si hay
            foreach ($params as $key => $value) {
                $url = str_replace("{" . $key . "}", $value, $url);
            }

            return $url;
        }
    }

    throw new Exception("Ruta con nombre '$name' no encontrada.");
}

// Middleware simple de autenticación
class AuthMiddleware {
    public function handle($request): void {
        if (empty($_SESSION['auth'])) {
            header('Location: /login');
            exit;
        }
    }
}

// Página de inicio
$router->get('/', function () {
    return '<h1>Inicio</h1><a href="'.route('login').'">Login</a>';
})->name('home');

// Login (formulario)
$router->get('/login', function () {
    return '
        <h2>Login</h2>
        <form method="POST" action="/login">
            <input type="text" name="user" placeholder="usuario"><br>
            <input type="password" name="pass" placeholder="clave"><br>
            <button type="submit">Ingresar</button>
        </form>
    ';
})->name('login');

// Login (procesar)
$router->post('/login', function () {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if ($user === 'admin' && $pass === '123') {
        $_SESSION['auth'] = true;
        header('Location: /admin/dashboard');
        exit;
    }

    return 'Credenciales inválidas. <a href="/login">Volver</a>';
})->name('login.post');

// Logout
$router->get('/logout', function () {
    session_destroy();
    header('Location: /');
    exit;
})->name('logout');

// Área protegida
$router->group([
    'prefix' => '/admin',
    'middleware' => AuthMiddleware::class
], function($r) {
    $r->get('/dashboard', function () {
        return 'Bienvenido al Panel de administrador. <a href="/logout">Cerrar sesión</a>';
    })->name('admin.dashboard');
});

// Rutas adicionales de prueba
$router->put('/editar', fn() => 'PUT recibido');
$router->any('/cualquiera', fn() => 'Esto responde a cualquier método HTTP');

// Error 404 personalizado
$router->setNotFoundHandler(function($request) {
    http_response_code(404);
    return '😢 Página no encontrada: ' . $request->getPath();
});

$router->run();
