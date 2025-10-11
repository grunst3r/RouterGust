<?php 
// tests/RouterTest.php
namespace GustRouter\Tests;

use GustRouter\Router;
use GustRouter\Request;
use PHPUnit\Framework\TestCase;

// Define the test service class outside the test class
class TestService {
    public function getData(): string {
        return 'injected service';
    }
}

class TestMiddleware1 {
    public function handle($request, $next) {
        $response = $next($request);
        return "[M1]" . $response . "[/M1]";
    }
}

class TestMiddleware2 {
    public function handle($request, $next) {
        $response = $next($request);
        return "[M2]" . $response . "[/M2]";
    }
}

class RouterTest extends TestCase 
{
    public function testBasicRouteMatching(): void 
    {
        // Set server variables before creating request
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/test', fn() => 'test response');
        
        $this->expectOutputString('test response');
        $router->run();
    }
    
    public function testRouteParameters(): void 
    {
        $_SERVER['REQUEST_URI'] = '/user/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/user/{id}', fn($id) => "User $id");
        
        $this->expectOutputString('User 123');
        $router->run();
    }
    
    public function testRouteWithRegexParameter(): void 
    {
        $_SERVER['REQUEST_URI'] = '/user/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/user/{id:\d+}', fn($id) => "User $id");
        
        $this->expectOutputString('User 123');
        $router->run();
    }
    
    public function testRouteWithRegexParameterFailsWithInvalidInput(): void 
    {
        $_SERVER['REQUEST_URI'] = '/user/abc';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/user/{id:\d+}', fn($id) => "User $id");
        $router->get('/user/{name}', fn($name) => "User $name"); // fallback route
        
        // Should match the fallback route since 'abc' doesn't match \d+ pattern
        $this->expectOutputString('User abc');
        $router->run();
    }
    
    public function testPostRoute(): void 
    {
        $_SERVER['REQUEST_URI'] = '/submit';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->post('/submit', fn() => 'post response');
        
        $this->expectOutputString('post response');
        $router->run();
    }
    
    public function testPutRoute(): void 
    {
        $_SERVER['REQUEST_URI'] = '/update';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->put('/update', fn() => 'put response');
        
        $this->expectOutputString('put response');
        $router->run();
    }
    
    public function testPatchRoute(): void 
    {
        $_SERVER['REQUEST_URI'] = '/patch';
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->patch('/patch', fn() => 'patch response');
        
        $this->expectOutputString('patch response');
        $router->run();
    }
    
    public function testDeleteRoute(): void 
    {
        $_SERVER['REQUEST_URI'] = '/delete';
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->delete('/delete', fn() => 'delete response');
        
        $this->expectOutputString('delete response');
        $router->run();
    }
    
    public function testNamedRouteGeneration(): void 
    {
        $_SERVER['REQUEST_URI'] = '/'; // Not important for this test
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/user/{id}', fn($id) => "User $id")->name('user.show');
        
        $url = $router->url('user.show', ['id' => 123]);
        $this->assertStringEndsWith('/user/123', $url);
    }
    
    public function testRouteGroupWithPrefix(): void 
    {
        $_SERVER['REQUEST_URI'] = '/api/users';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->group(['prefix' => '/api'], function($router) {
            $router->get('/users', fn() => 'API Users');
        });
        
        $this->expectOutputString('API Users');
        $router->run();
    }
    
    public function testRouteGroupWithMiddleware(): void 
    {
        $_SERVER['REQUEST_URI'] = '/secure';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        // Create a simple test middleware
        $router->bind('TestMiddleware', function() {
            return new class {
                public function handle($request, $next) {
                    // Add a header to indicate middleware ran
                    header('X-Middleware: test');
                    return $next($request);
                }
            };
        });
        
        $router->group(['prefix' => '/secure', 'middleware' => 'TestMiddleware'], function($router) {
            $router->get('/', fn() => 'Secure content');
        });
        
        $this->expectOutputString('Secure content');
        $router->run();
    }
    
    public function testMultipleMiddleware(): void 
    {
        $_SERVER['REQUEST_URI'] = '/test-middleware';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/test-middleware', fn() => 'content')
            ->middleware('GustRouter\Tests\TestMiddleware1')
            ->middleware('GustRouter\Tests\TestMiddleware2');
        
        $this->expectOutputString('[M1][M2]content[/M2][/M1]');
        $router->run();
    }
    
    public function testRouteNotFound(): void 
    {
        $_SERVER['REQUEST_URI'] = '/nonexistent';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->setErrorHandler(function($code) {
            return "Error $code";
        });
        
        $this->expectOutputString('Error 404');
        $router->run();
    }
    
    public function testDependencyInjection(): void 
    {
        $_SERVER['REQUEST_URI'] = '/inject';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        // Register the service in the container
        $router->bind(TestService::class, function() {
            return new TestService();
        });
        
        $router->get('/inject', function(TestService $testService) {
            return $testService->getData();
        });
        
        $this->expectOutputString('injected service');
        $router->run();
    }
    
    public function testRequestInputSanitization(): void 
    {
        $_GET['test'] = '<script>alert("xss")</script>';
        
        $_SERVER['REQUEST_URI'] = '/xss-test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/xss-test', function(Request $request) {
            return $request->get('test');
        });
        
        // The output should be sanitized
        $this->expectOutputString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
        $router->run();
    }
    
    public function testWhereSingleParameter(): void 
    {
        $_SERVER['REQUEST_URI'] = '/user/abc';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/user/{id}', function($id) {
            return "User: $id";
        })->where('id', '[A-Za-z]+');
        
        // Add a fallback route
        $router->get('/fallback', function() {
            return "Fallback";
        });
        
        $this->expectOutputString('User: abc');
        $router->run();
    }
    
    public function testWhereSingleParameterFails(): void 
    {
        $_SERVER['REQUEST_URI'] = '/user/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/user/{id}', function($id) {
            return "User: $id";
        })->where('id', '[A-Za-z]+');
        
        // Add a fallback route that matches the same pattern but without restrictions
        $router->get('/user/{name}', function($name) {
            return "Fallback: $name";
        });
        
        $this->expectOutputString('Fallback: 123');
        $router->run();
    }
    
    public function testWhereMultipleParameters(): void 
    {
        $_SERVER['REQUEST_URI'] = '/product/tech/123/product-name_123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->get('/product/{category}/{id}/{slug}', function($category, $id, $slug) {
            return "Product: $category, $id, $slug";
        })->where([
            'category' => '[a-zA-Z]+',
            'id' => '\d+',
            'slug' => '[a-zA-Z0-9_-]+'
        ]);
        
        // Add a fallback route
        $router->get('/fallback', function() {
            return "Fallback";
        });
        
        $this->expectOutputString('Product: tech, 123, product-name_123');
        $router->run();
    }
    
    public function testGroupWithWhereCondition(): void 
    {
        $_SERVER['REQUEST_URI'] = '/es/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->group(['prefix' => "/{lang}", 'where' => ['lang' => '[a-z]{2}']], function($r) {
            $r->get('/', fn($lang) => "Language: $lang");
        });
        
        $router->get('/fallback', fn() => 'Fallback');
        
        $this->expectOutputString('Language: es');
        $router->run();
    }
    
    public function testGroupWithWhereConditionFails(): void 
    {
        $_SERVER['REQUEST_URI'] = '/en-US/';  // Doesn't match [a-z]{2} pattern for lang
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->group(['prefix' => "/{lang}", 'where' => ['lang' => '[a-z]{2}']], function($r) {
            $r->get('/', fn($lang) => "Language: $lang");
        });
        
        // Add a route that would match the non-compliant pattern
        $router->get('/{lang2}/', fn($lang2) => "Fallback: $lang2");
        
        $this->expectOutputString('Fallback: en-US');
        $router->run();
    }
    
    public function testMultipleRoutesInGroupWithWhere(): void 
    {
        $_SERVER['REQUEST_URI'] = '/admin/users/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        $router = new Router($request);
        
        $router->group(['prefix' => '/admin', 'where' => ['id' => '\d+']], function($r) {
            $r->get('/users/{id}', function($id) {
                return "User ID: $id";
            });
            
            $r->get('/products/{id}', function($id) {
                return "Product ID: $id";
            });
        });
        
        $router->get('/fallback', fn() => 'Fallback');
        
        $this->expectOutputString('User ID: 123');
        $router->run();
    }
    
    public function testSubdomainRouting(): void 
    {
        // Set server variables BEFORE creating Request object
        $_SERVER['REQUEST_URI'] = '/users';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        
        // Create a test router class that allows setting test domain
        $testRouter = new class($request) extends Router {
            private $testDomain = null;
            
            public function setTestDomain(string $domain) {
                $this->testDomain = $domain;
            }
            
            public function detectCurrentDomain(): string {
                if ($this->testDomain) {
                    return $this->testDomain;
                }
                return parent::detectCurrentDomain();
            }
        };
        
        $testRouter->setTestDomain('http://api.example.com');
        
        // Set up routes with domain
        $testRouter->domain('http://api.example.com', function($r) {
            $r->get('/users', function() {
                return "API Users";
            });
        });
        
        $testRouter->get('/users', function() {
            return "Main Site Users";
        });
        
        $this->expectOutputString('API Users');
        $testRouter->run();
    }
    
    public function testParameterizedSubdomain(): void 
    {
        // Set server variables BEFORE creating Request object
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $request = new Request();
        
        // Create a test router class that allows setting test domain
        $testRouter = new class($request) extends Router {
            private $testDomain = null;
            
            public function setTestDomain(string $domain) {
                $this->testDomain = $domain;
            }
            
            public function detectCurrentDomain(): string {
                if ($this->testDomain) {
                    return $this->testDomain;
                }
                return parent::detectCurrentDomain();
            }
        };
        
        $testRouter->setTestDomain('http://tenant1.example.com');
        
        // Set up routes with parameterized domain
        $testRouter->domain('http://{tenant}.example.com', function($r) {
            $r->get('/dashboard', function($tenant) {
                return "Dashboard for tenant: $tenant";
            });
        });
        
        $this->expectOutputString('Dashboard for tenant: tenant1');
        $testRouter->run();
    }
}