<?php 
// tests/RouterTest.php
namespace GustRouter\Tests;

use GustRouter\Router;
use GustRouter\Request;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {
    private Router $router;
    private Request $request;
    
    protected function setUp(): void {
        $this->request = new Request();
        $this->router = new Router($this->request);
    }
    
    public function testBasicRouteMatching(): void {
        $this->router->get('/test', fn() => 'test response');
        
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $this->expectOutputString('test response');
        $this->router->run();
    }
    
    public function testRouteParameters(): void {
        $this->router->get('/user/{id}', fn($id) => "User $id");
        
        $_SERVER['REQUEST_URI'] = '/user/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $this->expectOutputString('User 123');
        $this->router->run();
    }
    
    // Más tests...
}