<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php'; // Use Composer autoloader

use GustRouter\Request;
use GustRouter\Router;
use GustRouter\Response;
use GustRouter\Contracts\ValidatorInterface;

$request = new Request();
$router = new Router($request);

// Register a service in the container
$router->bind(ValidatorInterface::class, function() {
    return new class implements ValidatorInterface {
        public function required($value): bool {
            return !empty($value);
        }

        public function email($value): bool {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        public function minLength($value, $length): bool {
            return strlen($value) >= $length;
        }
    };
});

function route(string $name, array $params = []): string {
    global $router;
    return $router->url($name, $params);
}

// Advanced middleware with proper chaining
class AuthMiddleware {
    public function handle($request, $next) {
        if (empty($_SESSION['auth'])) {
            return Response::redirect('/login')->send();
        }
        return $next($request);
    }
}

class CorsMiddleware {
    public function handle($request, $next) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        return $next($request);
    }
}

// Home page
$router->get('/', function () {
    return '<h1>Inicio</h1>
    <p><a href="'.route('login',['q' => 'holaa']).'">Login</a></p>
    <p>Rutas disponibles:</p>
    <a href="'.route('login').'">Login</a><br>
    <a href="'.route('profile', ['slug' => 'mi-perfil']).'">Perfil</a><br>
    <a href="'.route('post', ['slug' => 'mi-post']).'">Post</a><br>
    <a href="'.route('admin.dashboard').'">Panel de administrador</a><br>
    <a href="'.route('home', ['lang' => 'en']).'">Inicio en inglés</a><br>
    <a href="'.route('home', ['lang' => 'fr']).'">Inicio en francés</a><br>
    <a href="'.route('home', ['lang' => 'es']).'">Inicio en español</a><br>
    <a href="'.route('contacto', ['lang' => 'es']).'">Contacto en español</a><br>
    <a href="'.route('api.users').'">API Users</a><br>
    <a href="'.route('user.edit', ['id' => 123]).'">Edit User 123</a><br>
    ';
});

// Profile route with middleware
$router->get('/profile/{slug}', fn($slug) => "Perfil: $slug")->middleware(AuthMiddleware::class)->name('profile');

// Post route
$router->get('/post/{slug}', function ($slug) {
    return [
        'title' => 'Post: ' . $slug,
        'content' => 'Contenido del post con slug: ' . $slug
    ];
})->name('post');

// Login form
$router->get('/login', function () {
    return '
        <h2>Login</h2>
        <form method="POST" action="/login">
            <input type="text" name="user" placeholder="usuario"><br>
            <input type="password" name="pass" placeholder="clave"><br>
            <button type="submit">Ingresar</button>
        </form>
        <p><a href="/">Volver al inicio</a></p>
    ';
})->name('login');

// Login processing
$router->post('/login', function (Request $request, ValidatorInterface $validator) {
    $user = $request->post('user') ?? '';
    $pass = $request->post('pass') ?? '';

    // Validation
    if (!$validator->required($user) || !$validator->required($pass)) {
        return 'Usuario y contraseña son obligatorios. <a href="' . route('login') . '">Inténtalo de nuevo</a>';
    }

    if ($user === 'admin' && $pass === '123') {
        $_SESSION['auth'] = true;
        return Response::redirect(route('admin.dashboard'))->send();
    }
    return 'Credenciales inválidas. <a href="' . route('login') . '">Inténtalo de nuevo</a>';
})->name('login.post');

// Logout
$router->get('/logout', function () {
    session_destroy();
    return Response::redirect('/')->send();
})->name('logout');

// Protected admin area
$router->group([
    'prefix' => '/dashboard',
    'middleware' => [AuthMiddleware::class]
], function($r) {
    $r->get('/', function () {
        return 'Bienvenido al Panel de administrador. <a href="/logout">Cerrar sesión</a>';
    })->name('admin.dashboard');
    
    $r->get('/settings', function () {
        return 'Configuración del panel de administrador';
    })->name('admin.settings');
});

// API routes with CORS
$router->group([
    'prefix' => '/api',
    'middleware' => [CorsMiddleware::class]
], function($r) {
    $r->get('/users', function() {
        return ['users' => [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ]];
    })->name('api.users');
    
    $r->get('/users/{id}', function($id) {
        return ['user' => ['id' => $id, 'name' => "User $id"]];
    })->name('api.user.show');
    
    $r->post('/users', function(Request $request) {
        $data = $request->getJson();
        return ['message' => 'User created', 'data' => $data];
    })->name('api.user.create');
});

// PUT and DELETE routes
$router->put('/editar', fn() => 'PUT recibido');
$router->delete('/eliminar/{id}', function($id) {
    return "Registro $id eliminado";
})->name('delete.record');
$router->patch('/actualizar/{id}', function($id) {
    return "Registro $id actualizado parcialmente";
})->name('patch.record');
$router->any('/cualquiera', fn() => 'Esto responde a cualquier método HTTP');

// Route with parameter validation using where
$router->get('/user/{id}', function($id) {
    return "Usuario ID: $id (solo letras permitidas)";
})->name('user.show')->where('id', '[A-Za-z]+');

// Route with complex parameter validation using where
$router->get('/product/{category}/{id}/{slug}', function($category, $id, $slug) {
    return "Producto: Categoría $category, ID $id, Slug $slug";
})->name('product.show')->where([
    'category' => '[a-zA-Z]+',
    'id' => '\d+',
    'slug' => '[a-zA-Z0-9_-]+'
]);

// Another example with single where
$router->get('/code/{value}', function($value) {
    return "Código: $value (solo caracteres hexadecimales)";
})->name('code.show')->where('value', '[0-9A-Fa-f]+');

// Edit user route
$router->get('/user/{id}/edit', function($id) {
    return "Editar usuario $id";
})->name('user.edit');

// Language-specific routes with where condition in group
$router->group(['prefix' => "/{lang}", 'where' => ['lang' => '[a-z]{2}']], function($r) {
    $r->get('/', fn($lang) => '<b>Inicio en ' . htmlspecialchars($lang) . '</b>')->name('home');
    $r->get('/contacto', fn($lang) => '<b>Contacto en ' . htmlspecialchars($lang) . '</b>')->name('contacto');
});

// Admin routes with parameter validation in group
$router->group(['prefix' => '/admin', 'where' => ['id' => '\d+']], function($r) {
    $r->get('/users/{id}', function($id) {
        return "Usuario admin ID: $id";
    })->name('admin.user.show');
    
    $r->get('/products/{id}', function($id) {
        return "Producto admin ID: $id";
    })->name('admin.product.show');
});

// API subdomain routes
$router->domain('http://api.{domain}', function($r) {
    $r->get('/users', function($domain) {
        return ['message' => "API Users for domain: $domain"];
    });
    
    $r->get('/posts', function($domain) {
        return ['message' => "API Posts for domain: $domain"];
    });
});

// Multi-tenant subdomain routes
$router->domain('http://{tenant}.localhost', function($r) {
    $r->get('/dashboard', function($tenant) {
        return "<h2>Dashboard for tenant: $tenant</h2><p>Welcome to your tenant-specific dashboard!</p>";
    });
    
    $r->get('/settings', function($tenant) {
        return "<h2>Settings for tenant: $tenant</h2><p>Configure your tenant settings here.</p>";
    });
});

// Error handler
$router->setErrorHandler(function($code) {
    switch ($code) {
        case 404:
            return '<h1>❌ Página no encontrada</h1><p>La ruta solicitada no existe.</p><a href="/">Volver al inicio</a>';
        case 403:
            return '<h1>🔐 Acceso denegado</h1><p>No tienes permiso para acceder a esta página.</p><a href="/">Volver al inicio</a>';
        case 500:
            return '<h1>💥 Error interno del servidor</h1><p>Ha ocurrido un error inesperado.</p><a href="/">Volver al inicio</a>';
        default:
            return '<h1>⚠️ Error desconocido</h1><p>Código: ' . $code . '</p><a href="/">Volver al inicio</a>';
    }
});

$router->run();