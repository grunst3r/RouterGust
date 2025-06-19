# RouterGust 🚀

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.2.5-blue)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Build Status](https://github.com/grunst3r/RouterGust/actions/workflows/tests.yml/badge.svg)](https://github.com/grunst3r/RouterGust/actions)

RouterGust es un sistema de enrutamiento PHP ligero, rápido y expresivo diseñado para construir aplicaciones web y APIs modernas.

## Características principales ✨

- 🛣️ Enrutamiento RESTful (GET, POST, PUT, PATCH, DELETE)
- 🧩 Parámetros de ruta dinámicos (`/user/{id}`)
- 🏗️ Grupos de rutas con prefijos y middleware
- 🛡️ Sistema de middleware flexible
- 🌐 Soporte para subdominios
- 🔗 Generación de URLs inteligente
- 📦 Estructura PSR-4 compatible con Composer
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
$router->any('/ruta', $callback); // Todos los métodos

// Parámetros en rutas
$router->get('/user/{id}', function($id) {
    return "Usuario ID: $id";
});

// Parámetros opcionales
$router->get('/blog/{slug}/{page?}', function($slug, $page = 1) {
    return "Post: $slug, Página: $page";
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
```

### 4. Middleware

```php
class AuthMiddleware {
    public function handle($request, $next) {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        return $next($request);
    }
}

// Aplicar middleware
$router->get('/profile', fn() => 'Perfil')->middleware(AuthMiddleware::class);
```

### 5. Subdominios

```php
$router->domain('api.midominio.com', function($router) {
    $router->get('/users', [ApiController::class, 'users']);
    $router->post('/auth', [ApiController::class, 'auth']);
});
```

### 6. Generación de URLs

```php
$router->get('/post/{slug}', fn($slug) => "Post: $slug")->name('post.show');

// Generar URL
$url = $router->url('post.show', ['slug' => 'mi-post']);
```

## 🛠️ Configuración avanzada

### Personalizar manejo de errores

```php
$router->setErrorHandler(function($code) {
    switch ($code) {
        case 404:
            return 'Página no encontrada';
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

## 📜 Licencia

RouterGust es software de código abierto licenciado bajo MIT License.

## 🌟 Créditos

Desarrollado por Luis Gustavo
