<?php
// Example usage of RouterGust with proper callback formats

require_once __DIR__ . '/vendor/autoload.php';

use GustRouter\Router;
use GustRouter\Request;

// Example 1: Basic closure callback (correct usage)
$router = new Router(new Request());

// Correct - using a closure
$router->get('/', function() {
    return 'Welcome to RouterGust!';
});

// Correct - using a closure with parameters
$router->get('/user/{id}', function($id) {
    return "User ID: $id";
});

// Example 2: Controller callback (correct usage)
class UserController {
    public function show($id) {
        return "Showing user: $id";
    }
    
    public function index() {
        return "All users";
    }
}

// Correct - using a controller method
$router->get('/users', [UserController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show']);

// Example 3: Group with middleware
class AuthMiddleware {
    public function handle($request, $next) {
        // Simple authentication check
        $response = $next($request);
        return $response;
    }
}

$router->group(['prefix' => '/admin', 'middleware' => AuthMiddleware::class], function($router) {
    $router->get('/dashboard', function() {
        return 'Admin Dashboard';
    });
});

// Example 4: Named routes
$router->get('/posts/{slug}', function($slug) {
    return "Post: $slug";
})->name('post.show');

// Generate URL for named route
try {
    $url = $router->url('post.show', ['slug' => 'my-first-post']);
    echo "Generated URL: $url\n";
} catch (Exception $e) {
    echo "Error generating URL: " . $e->getMessage() . "\n";
}

// Run the router
$router->run();