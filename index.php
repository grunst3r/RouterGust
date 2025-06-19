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
    return $router->url($name, $params);
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
    return '<h1>Inicio</h1>
    <a href="'.route('login').'">Login</a>
    <a href="'.route('profile', ['slug' => 'mi-perfil']).'">Perfil</a>
    <a href="'.route('post', ['slug' => 'mi-post']).'">Post</a>
    <a href="'.route('admin.dashboard').'">Panel de administrador</a>
    <a href="'.route('home', ['lang' => 'en']).'">Inicio en inglés</a>
    <a href="'.route('home', ['lang' => 'fr']).'">Inicio en francés</a>
    <a href="'.route('home', ['lang' => 'es']).'">Inicio en español</a>
    <a href="'.route('contacto', ['lang' => 'es']).'">Contacto en español</a>
    ';
});


$router->get('/profile/{slug}', fn() => 'Perfil')->middleware(AuthMiddleware::class)->name('profile');

//post
$router->get('/post/{slug}', function ($slug) {
    return [
        'title' => 'Post: ' . $slug,
        'content' => 'Contenido del post con slug: ' . $slug
    ];
})->name('post');

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
$router->post('/login', function (Request $request) {
    $user = $request->post('user') ?? '';
    $pass = $request->post('pass') ?? '';

    if ($user === 'admin' && $pass === '123') {
        $_SESSION['auth'] = true;
        header('Location: ' . route('admin.dashboard'));
        exit;
    }
    return 'Credenciales inválidas. <a href="' . route('login') . '">Inténtalo de nuevo</a>';
})->name('login.post');

// Logout
$router->get('/logout', function () {
    session_destroy();
    header('Location: /');
    exit;
})->name('logout');

// Área protegida
$router->group([
    'prefix' => '/dashboard',
    'middleware' => AuthMiddleware::class
], function($r) {
    $r->get('/', function () {
        return 'Bienvenido al Panel de administrador. <a href="/logout">Cerrar sesión</a>';
    })->name('admin.dashboard');
});

// Rutas adicionales de prueba
$router->put('/editar', fn() => 'PUT recibido');
$router->any('/cualquiera', fn() => 'Esto responde a cualquier método HTTP');


//// O con un prefijo dinámico
$router->group(['prefix' => "/{lang}"], function($r) {
    $r->get('/', fn($lang) => '<b>Inicio en ' . $lang. '</b>')->name('home');
    $r->get('/contacto', fn() => '<b>Contacto</b>')->name('contacto');
});


// Error 404 personalizado
/* $router->setErrorHandler(function($code) {
    switch ($code) {
        case 404:
            return '❌ Página no encontrada';
        case 500:
            return '💥 Error interno del servidor';
        default:
            return '⚠️ Error desconocido';
    }
}); */

$url = $router->url('home', ['lang' => 'en']);
//echo "URL generada: $url\n";

$router->run();