# RouterGust 🚀

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

RouterGust es un sistema de enrutamiento PHP avanzado, rápido y expresivo diseñado para construir aplicaciones web y APIs modernas.

## Características principales ✨

- 🛣️ Enrutamiento RESTful (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
- 🧩 Parámetros de ruta dinámicos (`/user/{id}`)
- 🔤 Validación de parámetros con regex (`/user/{id:\d+}`)
- 🏗️ Grupos de rutas con prefijos, middleware y validación
- 🛡️ Sistema de middleware flexible con encadenamiento
- 🌐 Soporte para subdominios (estáticos y parametrizados)
- 🔄 Generación de URLs inteligente
- 📦 Estructura PSR-4 compatible con Composer
- 🔌 Contenedor de Inyección de Dependencias
- 🛡️ Protección XSS y sanitización de entradas
- 🧪 Cobertura de tests completa

## Instalación ⚙️

Instala el paquete vía Composer:

```bash
composer require luigu/router-gust
```

## 🚀 Primeros pasos

### Configuración básica

```php
require 'vendor/autoload.php';

use GustRouter\Router;
use GustRouter\Request;

// Crear instancia del router
$router = new Router(new Request());

// Definir rutas
$router->get('/', function() {
    return '¡Bienvenido a mi aplicación!';
});

// Iniciar el router
$router->run();
```

## 📚 Características

### 1. Definición de rutas

```php
// Métodos HTTP soportados
$router->get('/ruta', $callback);
$router->post('/ruta', $callback);
$router->put('/ruta', $callback);
$router->patch('/ruta', $callback);
$router->delete('/ruta', $callback);
$router->head('/ruta', $callback);
$router->options('/ruta', $callback);
$router->any('/ruta', $callback); // Todos los métodos

// Parámetros en rutas
$router->get('/user/{id}', function($id) {
    return "Usuario ID: $id";
});

// Parámetros con validación de regex
$router->get('/user/{id:\d+}', function($id) {
    return "Usuario ID: $id (solo números)";
});

// Parámetros con validación de regex compleja
$router->get('/product/{category}/{id:\d+}/{slug:[a-zA-Z0-9_-]+}', function($category, $id, $slug) {
    return "Producto: $category, ID $id, Slug $slug";
});
```

### 2. Controladores

```php
class UserController {
    public function show($id) {
        return "Mostrando usuario $id";
    }
    
    public function store() {
        return "Usuario creado";
    }
}

// Usar controladores
$router->get('/users/{id}', [UserController::class, 'show']);
$router->post('/users', [UserController::class, 'store']);
```

### 3. Grupos de rutas

```php
$router->group(['prefix' => '/admin', 'middleware' => AuthMiddleware::class], function($router) {
    $router->get('/dashboard', fn() => 'Panel de administración');
    $router->get('/users', [AdminController::class, 'users']);
    $router->get('/settings', [AdminController::class, 'settings']);
});

// Grupos con validación de parámetros
$router->group(['prefix' => '/api/v1', 'where' => ['id' => '\d+']], function($r) {
    $r->get('/users/{id}', function($id) {
        return "Usuario API: $id";
    });
    $r->get('/posts/{id}', function($id) {
        return "Post API: $id";
    });
});
```

### 4. Validación de parámetros con `where`

```php
// Validación individual
$router->get('/user/{id}', function($id) {
    return "Usuario: $id";
})->where('id', '[0-9]+');

// Validación múltiple
$router->get('/product/{category}/{id}/{slug}', function($category, $id, $slug) {
    return "Producto: $category, $id, $slug";
})->where([
    'category' => '[a-zA-Z]+',
    'id' => '\d+',
    'slug' => '[a-zA-Z0-9_-]+'
]);

// En grupos
$router->group(['prefix' => '/{lang}', 'where' => ['lang' => '[a-z]{2}']], function($r) {
    $r->get('/', fn($lang) => "Inicio en $lang");
    $r->get('/about', fn($lang) => "Acerca de en $lang");
});
```

### 5. Middleware

```php
class AuthMiddleware {
    public function handle($request, $next) {
        if (!isset($_SESSION['user'])) {
            return Response::redirect('/login')->send();
        }
        return $next($request); // Encadenamiento correcto
    }
}

class CorsMiddleware {
    public function handle($request, $next) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        return $next($request);
    }
}

// Aplicar middleware
$router->get('/profile', fn() => 'Perfil')->middleware(AuthMiddleware::class);

// Múltiples middleware con encadenamiento
$router->get('/api/data', fn() => 'Datos')
    ->middleware(AuthMiddleware::class)
    ->middleware(CorsMiddleware::class);
```

### 6. Subdominios

```php
// Subdominios estáticos
$router->domain('http://api.example.com', function($r) {
    $r->get('/users', fn() => 'Usuarios API');
    $r->get('/posts', fn() => 'Posts API');
});

// Subdominios parametrizados (ideal para aplicaciones multi-tenant)
$router->domain('http://{tenant}.example.com', function($r) {
    $r->get('/dashboard', function($tenant) {
        return "Dashboard para: $tenant";
    });
    
    $r->get('/settings', function($tenant) {
        return "Configuración para: $tenant";
    });
});

// Subdominios con validación
$router->domain('http://{tenant:\w+}.example.com', function($r) {
    $r->get('/dashboard', function($tenant) {
        return "Dashboard para: $tenant";
    });
});
```

### 7. Inyección de Dependencias

```php
// Registrar servicios en el contenedor
$router->bind('ValidatorInterface', function() {
    return new Validator();
});

// O con interfaces
interface LoggerInterface {
    public function log(string $message);
}

class FileLogger implements LoggerInterface {
    public function log(string $message) {
        file_put_contents('app.log', $message . PHP_EOL, FILE_APPEND);
    }
}

$router->bind(LoggerInterface::class, function() {
    return new FileLogger();
});

// Usar en rutas
$router->post('/login', function(Request $request, ValidatorInterface $validator, LoggerInterface $logger) {
    $user = $request->post('user');
    if ($validator->required($user)) {
        $logger->log("Login intentado por: $user");
        // Procesar login
    }
});
```

### 8. Generación de URLs

```php
$router->get('/post/{slug}', fn($slug) => "Post: $slug")->name('post.show');

// Generar URL
$url = $router->url('post.show', ['slug' => 'mi-post']);
// Resultado: http://localhost/post/mi-post
```

## 🛠️ Configuración avanzada

### Personalizar manejo de errores

```php
$router->setErrorHandler(function($code) {
    switch ($code) {
        case 404:
            return 'Página no encontrada';
        case 403:
            return 'Acceso denegado';
        case 500:
            return 'Error del servidor';
        default:
            return 'Error desconocido';
    }
});
```

### Configuración de base path

```php
$router->setBasePath('/mi-app');
```

## 🧪 Pruebas

RouterGust incluye una suite completa de pruebas:

```bash
vendor/bin/phpunit tests/
```

## 📜 Licencia

RouterGust es software de código abierto licenciado bajo MIT License.

## 🌟 Créditos

Desarrollado por Luis Gustavo
